<?php

namespace Netliva\SymfonyFastSearchBundle\Controller;

use Doctrine\ORM\Query;
use Netliva\SymfonyFastSearchBundle\Events\BeforeViewEvent;
use Netliva\SymfonyFastSearchBundle\Events\NetlivaFastSearchEvents;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FastSearchController extends Controller
{
    /**
     * @Route("/list/{key}/{page}", name="netliva_fast_search_list", defaults={"page": 1})
     */
    public function listAction (Request $request, $key, $page)
    {
        $fss          = $this->get('netliva_fastSearchServices');
        $entityInfos  = $this->getParameter('netliva_fast_search.entities');
        $cachePath    = $this->getParameter('netliva_fast_search.cache_path');
        $limitPerPage = $this->getParameter('netliva_fast_search.default_limit_per_page');

        if (!key_exists($key, $entityInfos))
            return new JsonResponse(['records'=>[], 'loaded' => 0, 'total' => 0]);

        $filePath = $cachePath.'/'.$key.'.json';

        if(!file_exists($filePath))
            return new JsonResponse(['records'=>[], 'loaded' => 0, 'total' => 0]);

        $content      = json_decode($request->getContent(), true);

        $limitPerPage = $entityInfos[$key]['limit_per_page'] ?: $limitPerPage;
        $records      = json_decode(file_get_contents($filePath), true);
        if (!$records) $records = [];
        $records      = $fss->filterRecords($records, $content['filters'], $entityInfos[$key]['filters']);
        $records      = $fss->sort($records, $content['sort_field'], $content['sort_direction']);
        $total        = count($records);
        $records      = array_slice($records, $limitPerPage * ($page - 1), $limitPerPage);

        $eventDispatcher = $this->container->get('event_dispatcher');
        $event = new BeforeViewEvent($records, $key, $entityInfos[$key]);
        $eventDispatcher->dispatch(NetlivaFastSearchEvents::BEFORE_VIEW, $event);


        return new JsonResponse(['records'=>$event->getRecords(), 'loaded' => $limitPerPage * $page, 'total' => $total]);
    }


    /**
     * @Route("/remove_cache/{key}/{force}", name="netliva_fast_search_remove_cache", defaults={"force": false})
     */
    public function removeCacheAction ($key, $force)
    {
        $entityInfos = $this->getParameter('netliva_fast_search.entities');
        $cachePath   = $this->getParameter('netliva_fast_search.cache_path');
        $fss         = $this->get('netliva_fastSearchServices');


        /** @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager();

        $infoPath = $cachePath.'/'.$key.'-info.json';
        $tempPath = $cachePath.'/'.$key.'-temp.json';
        $filePath = $cachePath.'/'.$key.'.json';

        $info = null;
        if (!$force && file_exists($infoPath))
        {
            $info = json_decode(file_get_contents($infoPath));
            if ($info->complete)
                $info = null;
        }

        if ($info)
        {
            // eğer kayıt oluşturma işlemi daha önce başlatılmış ve devam ediyorsa, ve üzerinden 2 dk geçmemiş ise hata döndür
            if (isset($info->in_proccess) && $info->in_proccess && new \DateTime($info->in_proccess) > new \DateTime('-2 minutes'))
            {
                return new JsonResponse(['success'=>false, 'info' => $info]);
            }


            $info->in_proccess = (new \DateTime())->format('c');
            $info->last_proccess = (new \DateTime())->format('c');
        }
        else
        {
            // Eğer daha önce info bilgisi oluşturulmamış ise, oluştur
            if(!is_dir($cachePath))
                mkdir($cachePath, 0777, true);

            if(file_exists($tempPath)) unlink($tempPath);
            if(file_exists($infoPath)) unlink($infoPath);

            $qb = $em->createQueryBuilder();
            $qb->select('count(ent.id)');
            $qb->from($entityInfos[$key]['class'],'ent');
            $fss->addWhereToQuery($qb, $entityInfos[$key]['where']);

            $info = (object)[
                'count'         => $qb->getQuery()->getSingleScalarResult(),
                'offset'        => 0,
                'complete'      => false,
                'last_proccess' => (new \DateTime())->format('c'),
                'in_proccess'   => (new \DateTime())->format('c'),
            ];
        }
        file_put_contents($infoPath, json_encode($info));


        $limit = 500;
        $dataFile = fopen($tempPath, 'a+');

        if ($info->offset == 0) fwrite($dataFile, "[".PHP_EOL);

        $qb = $em->getRepository($entityInfos[$key]['class'])->createQueryBuilder('ent');
        $qb->setMaxResults($limit);
        $qb->setFirstResult($info->offset);
        $fss->addWhereToQuery($qb, $entityInfos[$key]['where']);
        $query = $qb->getQuery();
        // $query->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true);
        $say = $info->offset;
        foreach ($query->getResult() as $entity)
        {
            $say++;
            $data = $fss->getEntObj($entity, $entityInfos[$key]['fields'], $key);
            unset($entity);
            fputs($dataFile, json_encode($data).($say==$info->count?'':',').PHP_EOL);
            unset($data);
        }

        $info->offset = $say;
        file_put_contents($infoPath, json_encode($info));


        // kayıtlar bitti
        if ($info->offset >= $info->count)
        {
            fputs($dataFile, "]");
            fclose($dataFile);

            if(file_exists($tempPath))
            {
                if(file_exists($filePath))
                    unlink($filePath);
                rename($tempPath, $filePath);
            }
            $info->complete = true;
        }
        else
        {
            fclose($dataFile);
        }
        $info->in_proccess = null;
        file_put_contents($infoPath, json_encode($info));

        /*
        $filePath = $cachePath.'/'.$key.'.json';
        if(file_exists($filePath))
            unlink($filePath);

        $em = $this->getDoctrine()->getManager();
        $entities = $em->getRepository($entityInfos[$key]['class'])->findAll();

        $data = [];
        foreach ($entities as $entity)
            $data[] = $fss->getEntObj($entity, $entityInfos[$key]['fields'], $key);

        if(!is_dir($cachePath))
            mkdir($cachePath, 0777, true);

        file_put_contents($filePath, json_encode($data));
        */

        return new JsonResponse(['success'=>true, 'info' => $info]);

    }


}
