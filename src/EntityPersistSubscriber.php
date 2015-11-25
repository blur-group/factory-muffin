<?php
namespace League\FactoryMuffin;

use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EntityPersistSubscriber implements EventSubscriber
{

    const postPersist = 'postPersist';

    private $_evm;

    public $postPersistInvoked = false;

    public function __construct(EventManager $evm)
    {
        $evm->addEventListener(array(self::postPersist), $this);
    }

    public function getSubscribedEvents()
    {
        return array(
            'postPersist',
//            'postUpdate',
        );
    }

//    public function postUpdate(LifecycleEventArgs $args)
//    {
//        $this->index($args);
//    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->index($args);
    }

    public function index(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();


        // perhaps you only want to act on some "Product" entity
//        if ($entity instanceof Product) {
//            // ... do something with the Product
//        }
    }
}