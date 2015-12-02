<?php
namespace League\FactoryMuffin;

use Doctrine\ORM\Event\LifecycleEventArgs;
use League\FactoryMuffin\Facade as FactoryMuffin;

class EntityPersistSubscriber
{
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        FactoryMuffin::saveForTracking($entity);
    }
}