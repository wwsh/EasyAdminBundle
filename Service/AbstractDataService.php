<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Service;

use JavierEguiluz\Bundle\EasyAdminBundle\Controller\AdminController;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * Class AbstractDataService
 * @package JavierEguiluz\Bundle\EasyAdminBundle\Service
 *
 * Common routines for all Data Services.
 * 
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
abstract class AbstractDataService
{
    /**
     * @var ManagerRegistry
     */
    protected $manager;

    /**
     * @var AdminController
     */
    protected $dispatcherController;

    /**
     * AbstractDataService constructor.
     * @param ManagerRegistry $manager
     */
    public function __construct(ManagerRegistry $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Persisting an entity or object.
     *
     * @param $element
     */
    public function persistAndFlush($element)
    {
        $this->manager->getManager()->persist($element);
        $this->manager->getManager()->flush();
    }

    /**
     * Flushing buffers.
     */
    public function flush()
    {
        $this->manager->getManager()->flush();
    }

    /**
     * @param $element
     */
    public function remove($element)
    {
        $this->manager->getManager()->remove($element);
    }
    
    /**
     * @param $dispatcher
     */
    public function setEventDispatcherController($dispatcher)
    {
        $this->dispatcherController = $dispatcher;
    }

    /**
     * Getting the doctrine metadata for specified class.
     * 
     * @param $elementClass
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadata|null
     */
    public function getMetadata($elementClass)
    {
        $manager = $this->manager->getManagerForClass($elementClass);

        if (null !== $manager) {
            return $manager
                ->getMetadataFactory()
                ->getMetadataFor($elementClass);
        }

        return null;
    }
    /**
     * A bridge to the AdminController's dispatch system.
     *
     * @param $eventName
     * @param $args
     */
    protected function dispatch($eventName, $args)
    {
        if (null === $this->dispatcherController) {
            return;
        }

        $this->dispatcherController->dispatch($eventName, $args);
    }

    /**
     * Given a method name pattern, it looks for the customized version of that
     * method (based on the entity name) and executes it. If the custom method
     * does not exist, it executes the regular method.
     *
     * For example:
     *   executeDynamicMethod('create<EntityName>Entity') and the entity name is 'User'
     *   if 'createUserEntity()' exists, execute it; otherwise execute 'createEntity()'
     *
     * @param string $methodNamePattern The pattern of the method name (dynamic parts are enclosed with <> angle
     *     brackets)
     * @param array  $arguments The arguments passed to the executed method
     *
     * @return mixed
     */
    protected function executeDynamicMethod($methodNamePattern, array $arguments = array())
    {
        $methodName = str_replace('<EntityName>', $this->entity['name'], $methodNamePattern);
        if (!is_callable(array($this, $methodName))) {
            $methodName = str_replace('<EntityName>', '', $methodNamePattern);
        }

        return call_user_func_array(array($this, $methodName), $arguments);
    }
}