<?php

namespace Netliva\SymfonyFastSearchBundle\Services;



use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FastCacheUpdaterServices
{
	protected $em;
	protected $fss;
	protected $container;
	public function __construct(EntityManagerInterface $em, ContainerInterface $container){
		$this->em = $em;
		$this->container = $container;
        $this->fss       = $this->container->get('netliva_fastSearchServices');
    }


    private $data = [];
    private $dataChanged = false;
    private $entityInfo  = null;
    private $entityKey  = null;
    public function openData ($entKey, $entInfo)
    {
        $cachePath = $this->container->getParameter('netliva_fast_search.cache_path');
        $filePath  = $cachePath.'/'.$entKey.'.json';
        if(!file_exists($filePath))
            return false;

        $this->entityKey   = $entKey;
        $this->entityInfo  = $entInfo;
        $this->data        = json_decode(file_get_contents($filePath), true);
        $this->dataChanged = false;
        if (!is_array($this->data)) $this->data = [];

        return  true;
    }
    public function addData ($entity)
    {
        if (get_class($entity) == $this->entityInfo['class'])
        {
            $this->data[] = $this->fss->getEntObj($entity, $this->entityInfo['fields'], $this->entityKey);
            $this->dataChanged = true;
        }
    }
    public function updateData ($entity)
    {
        if (get_class($entity) == $this->entityInfo['class'])
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
        if (get_class($entity) == $this->entityInfo['class'])
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
    public function saveData ()
    {
        if ($this->dataChanged)
        {
            $cachePath = $this->container->getParameter('netliva_fast_search.cache_path');
            $filePath  = $cachePath.'/'.$this->entityKey.'.json';
            if(!file_exists($filePath))
                return false;

            file_put_contents($filePath, json_encode($this->data));
        }
    }

}
