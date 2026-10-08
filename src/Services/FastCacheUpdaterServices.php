<?php

namespace Netliva\SymfonyFastSearchBundle\Services;



use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Önbellek dosyasındaki tek tek kayıtları günceller.
 *
 * Değişiklikler hemen dosyaya işlenmez, liste başına bir kuyrukta birikir ve
 * saveData()/saveAll() çağrısında TEK seferde uygulanır: dosya kilitlenir, güncel
 * hâli okunur, kuyruk uygulanır, geçici dosyaya yazılıp yerine taşınır. Böylece
 *  - bir işlemde N kayıt değişse de dosya bir kez çözülür ve bir kez yazılır
 *    (önceden kayıt başına bir çözme + sıralama + yazma vardı),
 *  - okuyanlar yarım yazılmış dosya görmez,
 *  - iki istek aynı anda yazarsa biri diğerinin değişikliğini ezmez.
 */
class FastCacheUpdaterServices
{
	public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FastSearchServices $fss,
        private readonly ContainerInterface $container
    ){ }


    private $entityInfo  = null;
    private $entityKey  = null;
    private $filePath  = null;

    /**
     * Dosya yolu → uygulanmayı bekleyen işlemler.
     *
     * @var array<string, list<array{0: string, 1: mixed, 2?: array}>>
     */
    private array $queue = [];

    public function openData ($entKey, $entInfo, $cachePath = null)
    {
        if (!$cachePath)
            $cachePath = $this->container->getParameter('netliva_fast_search.cache_path');
        $this->filePath  = $cachePath.'/'.$entKey.'.json';

        if(!file_exists($this->filePath))
        {
            // Dosya silinmişse (clear_all ya da yeniden üretim) bekleyen işlemlerin anlamı kalmaz.
            unset($this->queue[$this->filePath]);
            return false;
        }

        $this->entityKey   = $entKey;
        $this->entityInfo  = $entInfo;

        return  true;
    }
    public function addData ($entity)
    {
        if (is_numeric($entity))
            $entity = $this->em->getRepository($this->entityInfo['class'])->find($entity);

        if ($entity && is_object($entity) && $entity instanceof $this->entityInfo['class'])
            $this->queue[$this->filePath][] = ['add', $entity->getId(), $this->fss->getEntObj($entity, $this->entityInfo['fields'], $this->entityKey)];
    }
    public function updateData ($entity)
    {
        if (is_numeric($entity))
            $entity = $this->em->getRepository($this->entityInfo['class'])->find($entity);

        if ($entity && is_object($entity) && $entity instanceof $this->entityInfo['class'])
            $this->queue[$this->filePath][] = ['update', $entity->getId(), $this->fss->getEntObj($entity, $this->entityInfo['fields'], $this->entityKey)];
    }
    public function removeData ($entity)
    {
        if (is_numeric($entity))
            $entity = $this->em->getRepository($this->entityInfo['class'])->find($entity);

        if ($entity && is_object($entity) && $entity instanceof $this->entityInfo['class'])
            $this->queue[$this->filePath][] = ['remove', $entity->getId()];
    }

    /**
     * Açık olan listenin bekleyen işlemlerini dosyaya işler.
     */
    public function saveData (): void
    {
        if ($this->filePath)
            $this->flushFile($this->filePath);
    }

    /**
     * Bütün listelerin bekleyen işlemlerini dosyalarına işler (dinleyici postFlush'ta çağırır).
     */
    public function saveAll (): void
    {
        foreach (array_keys($this->queue) as $filePath)
            $this->flushFile($filePath);
    }

    private function flushFile (string $filePath): void
    {
        $ops = $this->queue[$filePath] ?? [];
        unset($this->queue[$filePath]);

        if (!$ops || !file_exists($filePath))
            return;

        $lock = fopen($filePath.'.lock', 'c');
        if ($lock)
            flock($lock, LOCK_EX);

        try
        {
            // Kilit beklenirken dosya silinmiş olabilir.
            if (!file_exists($filePath))
                return;

            $data = json_decode((string) file_get_contents($filePath), true);
            if (!is_array($data)) $data = [];
            $data = array_values($data);

            // id → dizideki yeri. Aynı id birden çok kez varsa ilki esas alınır.
            $index = [];
            foreach ($data as $i => $row)
            {
                $id = (string) ($row['id'] ?? '');
                if (!isset($index[$id])) $index[$id] = $i;
            }

            $changed = false;
            foreach ($ops as $op)
            {
                $id = (string) $op[1];
                switch ($op[0])
                {
                    case 'add':
                        $data[] = $op[2];
                        if (!isset($index[$id])) $index[$id] = array_key_last($data);
                        $changed = true;
                        break;
                    case 'update':
                        // Listede olmayan kayıt güncellemeyle eklenmez (önceki davranış).
                        if (isset($index[$id]))
                        {
                            $data[$index[$id]] = $op[2];
                            $changed = true;
                        }
                        break;
                    case 'remove':
                        if (isset($index[$id]))
                        {
                            unset($data[$index[$id]], $index[$id]);
                            $changed = true;
                        }
                        break;
                }
            }

            if (!$changed)
                return;

            // Geçici dosyaya yazıp yerine taşı: okuyan ya eski ya yeni dosyayı görür, yarımını değil.
            $tmpPath = $filePath.'.'.getmypid().'.tmp';
            if (false !== file_put_contents($tmpPath, json_encode(array_values($data))))
                rename($tmpPath, $filePath);
        }
        finally
        {
            if ($lock)
            {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

}
