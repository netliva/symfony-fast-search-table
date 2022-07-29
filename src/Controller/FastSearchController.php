<?php

namespace Netliva\SymfonyFastSearchBundle\Controller;

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
        $records      = $fss->filterRecords($records, $content['filters'], $entityInfos[$key]['filters']);
        $records      = $fss->sort($records, $content['sort_field'], $content['sort_direction']);
        $total        = count($records);
        $records      = array_slice($records, $limitPerPage * ($page - 1), $limitPerPage);


        return new JsonResponse(['records'=>$records, 'loaded' => $limitPerPage * $page, 'total' => $total]);
    }


    /**
     * @Route("/remove_cache/{key}", name="netliva_fast_search_remove_cache")
     */
    public function removeCacheAction ($key)
    {
        $entityInfos = $this->getParameter('netliva_fast_search.entities');
        $cachePath   = $this->getParameter('netliva_fast_search.cache_path');
        $fss         = $this->get('netliva_fastSearchServices');

        $filePath = $cachePath.'/'.$key.'.json';
        if(file_exists($filePath))
            unlink($filePath);

        /** @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager();
        $entities = $em->getRepository($entityInfos[$key]['class'])->findAll();

        $data = [];
        foreach ($entities as $entity)
            $data[] = $fss->getEntObj($entity, $entityInfos[$key]['fields'], $key);

        if(!is_dir($cachePath))
            mkdir($cachePath, 0777, true);

        file_put_contents($filePath, json_encode($data));

        return new JsonResponse(['success'=>true]);

    }


}
