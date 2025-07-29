<?php

namespace Netliva\SymfonyFastSearchBundle\Services;



use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FastCacheUpdaterServices
{
	public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FastSearchServices $fss,
        private readonly ContainerInterface $container
    ){ }


    private $data = [];
    private $dataChanged = false;
    private $entityInfo  = null;
    private $entityKey  = null;
    private $filePath  = null;
    public function openData ($entKey, $entInfo, $cachePath = null)
    {
        if (!$cachePath)
            $cachePath = $this->container->getParameter('netliva_fast_search.cache_path');
        $this->filePath  = $cachePath.'/'.$entKey.'.json';

        if(!file_exists($this->filePath))
            return false;

        $this->entityKey   = $entKey;
        $this->entityInfo  = $entInfo;
        $this->data        = json_decode(file_get_contents($this->filePath), true);
        $this->dataChanged = false;
        if (!is_array($this->data)) $this->data = [];

        return  true;
    }
    public function addData ($entity)
    {
        if (is_numeric($entity))
            $entity = $this->em->getRepository($this->entityInfo['class'])->find($entity);

        if ($entity && is_object($entity) && $entity instanceof $this->entityInfo['class'])
        {
            $this->data[] = $this->fss->getEntObj($entity, $this->entityInfo['fields'], $this->entityKey);
            $this->dataChanged = true;
        }
    }
    public function updateData ($entity)
    {
        if (is_numeric($entity))
            $entity = $this->em->getRepository($this->entityInfo['class'])->find($entity);

        if ($entity && is_object($entity) && $entity instanceof $this->entityInfo['class'])
        {
            $this->data = $this->fss->sort($this->data, 'id');
            $key  = $this->fss->binarySearch($this->data, $entity->getId(), 'id', 'strcmp', count($this->data) - 1, 0, true);
            if (strlen($key))
            {
                $this->data[$key]  = $this->fss->getEntObj($entity, $this->entityInfo['fields'], $this->entityKey);
                $this->dataChanged = true;
            }
        }
    }
    public function removeData ($entity)
    {
        if (is_numeric($entity))
            $entity = $this->em->getRepository($this->entityInfo['class'])->find($entity);

        if ($entity && is_object($entity) && $entity instanceof $this->entityInfo['class'])
        {
            $this->data = $this->fss->sort($this->data, 'id');
            $key  = $this->fss->binarySearch($this->data, $entity->getId(), 'id', 'strcmp', count($this->data) - 1, 0, true);
            if (strlen($key))
            {
                unset($this->data[$key]);
                $this->dataChanged = true;
            }
        }
    }
    public function saveData (): void
    {
        if ($this->dataChanged)
        {
            if(!file_exists($this->filePath))
                return;

            file_put_contents($this->filePath, json_encode($this->data));
        }
    }

}
