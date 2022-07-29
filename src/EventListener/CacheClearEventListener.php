<?php

namespace Netliva\SymfonyFastSearchBundle\EventListener;

use Crm\DefaultBundle\Services\CacheService;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CacheClearEventListener
{
    private $cacheService;
    private $container;

    public function __construct (CacheService $cacheService, ContainerInterface $container)
    {
        $this->cacheService = $cacheService;
        $this->container    = $container;
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->controlAndClearCache('persist', $args);
    }
    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->controlAndClearCache('update', $args);
    }
    public function preRemove(LifecycleEventArgs $args)
    {
        $this->controlAndClearCache('remove', $args);
    }

    private function controlAndClearCache (string $action, LifecycleEventArgs $args)
    {
        $fss         = $this->container->get('netliva_fastSearchServices');
        $cachePath   = $this->container->getParameter('netliva_fast_search.cache_path');
        $nfsEntities = $this->container->getParameter('netliva_fast_search.entities');
        $entity      = $args->getObject();

        if ($entity->getId())
        {
            foreach ($nfsEntities as $entKey => $entInfo)
            {
                if (get_class($entity) == $entInfo['class'])
                {
                    $filePath = $cachePath.'/'.$entKey.'.json';
                    if(!file_exists($filePath))
                    {
                        $em       = $this->container->get('doctrine')->getManager();
                        $entities = $em->getRepository($entityInfos[$entKey]['class'])->findAll();

                        $data = [];
                        foreach ($entities as $e)
                            $data[] = $fss->getEntObj($e, $entInfo['fields'], $entKey);

                        file_put_contents($filePath, json_encode($data));
                        continue;
                    }



                    $data = json_decode(file_get_contents($filePath), true);
                    switch ($action) {
                        case 'persist':
                            $data[] = $fss->getEntObj($entity, $entInfo['fields'], $entKey);
                        break;
                        case 'update':
                            $data = $fss->sort($data, 'id');
                            $key  = $fss->binarySearch($data, $entity->getId(), 'id', 'strcmp', count($data) - 1, 0, true);
                            $data[$key] = $fss->getEntObj($entity, $entInfo['fields'], $entKey);
                        break;
                        case 'remove':
                            $data = $fss->sort($data, 'id');
                            $key  = $fss->binarySearch($data, $entity->getId(), 'id', 'strcmp', count($data) - 1, 0, true);
                            unset($data[$key]);
                        break;
                    }
                    file_put_contents($filePath, json_encode($data));

                }
            }

        }

    }

}
