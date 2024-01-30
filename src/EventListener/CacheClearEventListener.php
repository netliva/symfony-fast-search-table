<?php

namespace Netliva\SymfonyFastSearchBundle\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use Netliva\SymfonyFastSearchBundle\Services\FastCacheUpdaterServices;
use Netliva\SymfonyFastSearchBundle\Services\FastSearchServices;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CacheClearEventListener
{
    public function __construct (
        private readonly ContainerInterface $container,
        private readonly FastSearchServices $fss,
        private readonly FastCacheUpdaterServices $fcu
    ) { }

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
                if (!$this->fcu->openData($entKey, $entInfo))
                    continue;

                if (get_class($entity) == $entInfo['class'])
                {
                    switch ($action) {
                        case 'persist': $this->fcu->addData($entity); break;
                        case 'update': $this->fcu->updateData($entity); break;
                        case 'remove': $this->fcu->removeData($entity); break;
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

                $this->fcu->saveData();
            }

        }

    }


    private function upateCacheByReverseEntities($entity, $field): void
    {
        $aField = explode('.', $field);
        $fKey = array_shift($aField);

        // gelen değer tek bir entity ise
        if (is_object($entity) 
            && !($entity instanceof PersistentCollection)
            && !($entity instanceof ArrayCollection)
            && $subEntity = $this->fss->getEntityValue($entity, $fKey)
        )
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
                    $this->fcu->updateData($item);
                return;
            }
            
            // eğer bulunan değer tek bir entity ise;
            $this->fcu->updateData($subEntity);
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


}
