<?php

namespace Netliva\SymfonyFastSearchBundle\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CacheClearEventListener
{
    private $container;
    private $fss;

    public function __construct (ContainerInterface $container)
    {
        $this->container = $container;
        $this->fss       = $this->container->get('netliva_fastSearchServices');
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
        $nfsEntities = $this->container->getParameter('netliva_fast_search.entities');
        $entity      = $args->getObject();

        if ($entity->getId())
        {
            foreach ($nfsEntities as $entKey => $entInfo)
            {
                if (!$this->openData($entKey, $entInfo))
                    continue;

                if (get_class($entity) == $entInfo['class'])
                {
                    switch ($action) {
                        case 'persist': $this->addData($entity); break;
                        case 'update': $this->updateData($entity); break;
                        case 'remove': $this->removeData($entity); break;
                    }
                }

                foreach ($entInfo['cache_clear'] as $className => $cacheClearInfo)
                {
                    if ($entity instanceof $className)
                    {
                        foreach ($cacheClearInfo['reverse_fields'] as $reversField)
                            $this->upateCacheByReverseEntities($entity, $reversField);
                    }
                }

                $this->saveData();
            }

        }

    }


    private function upateCacheByReverseEntities($entity, $field): void
    {
        $aField = explode('.', $field);
        $fKey = array_shift($aField);

        // gelen değer tek bir entity ise
        if (is_object($entity) && !($entity instanceof PersistentCollection) && !($entity instanceof ArrayCollection) && $subEntity = $this->fss->getEntityValue($entity, $fKey))
        {
            // eğer field birden fazla derinliğe sahip ise içe doğru kontrole devam et
            if (count($aField))
            {
                $this->upateCacheByReverseEntities($subEntity, implode('.', $aField));
                return;
            }

            // bulunan veri bir kolleksiyon ise;
            if (is_array($subEntity) || $subEntity instanceof PersistentCollection || $subEntity instanceof ArrayCollection )
            {
                foreach ($subEntity as $item)
                    $this->updateData($item);
                return;
            }

            // eğer bulunan değer tek bir entity ise;
            $this->updateData($subEntity);
            return;
        }

        // gelen değer entity collection ise
        if ((is_object($entity) && ($entity instanceof PersistentCollection || $entity instanceof ArrayCollection)))
        {
            // her entity için işlemi gerçekleştirmek üzere fonksiyonu yine çağır
            foreach ($entity as $ent)
                $this->upateCacheByReverseEntities($ent, $field);
        }
    }


    private $data = [];
    private $dataChanged = false;
    private $entityInfo  = null;
    private $entityKey  = null;
    private function openData ($entKey, $entInfo)
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
    private function addData ($entity)
    {
        if (get_class($entity) == $this->entityInfo['class'])
        {
            $this->data[] = $this->fss->getEntObj($entity, $this->entityInfo['fields'], $this->entityKey);
            $this->dataChanged = true;
        }
    }
    private function updateData ($entity)
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
    private function removeData ($entity)
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
    private function saveData ()
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
