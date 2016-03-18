<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\EventListener;

use JavierEguiluz\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use JavierEguiluz\Bundle\EasyAdminBundle\Service\DoctrineDataProxyService;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds some custom attributes to the request object to store information
 * related to EasyAdmin.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class RequestPostInitializeListener
{
    /** @var Request|null */
    private $request;

    /** @var RequestStack|null */
    private $requestStack;

    /**
     * @var DoctrineDataProxyService
     */
    private $dataService;


    /**
     * @param DoctrineDataProxyService|null $doctrine
     * @param RequestStack|null             $requestStack
     */
    public function __construct(DoctrineDataProxyService $dataService = null, RequestStack $requestStack = null)
    {
        $this->dataService  = $dataService;
        $this->requestStack = $requestStack;
    }

    /**
     * BC for SF < 2.4.
     * To be replaced by the usage of the request stack when 2.3 support is dropped.
     *
     * @param Request|null $request
     *
     * @return $this
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
    }

    public function initializeRequest(GenericEvent $event)
    {
        if (null !== $this->requestStack) {
            $this->request = $this->requestStack->getCurrentRequest();
        }

        if (null === $this->request) {
            return;
        }

        if (null === $this->dataService) {
            return;
        }

        $this->request->attributes->set('easyadmin', [
            'entity' => $element = $event->getArgument('entity'),
            'view'   => $this->request->query->get('action', 'list'),
            'item'   => ($id = $this->request->query->get('id')) ? $this->findCurrentItem($element, $id) : null,
        ]);
    }

    /**
     * Looks for the object that corresponds to the selected 'id' of the current entity/document.
     *
     * @param array $entityConfig
     * @param mixed $entityId
     *
     * @return object The entity
     *
     * @throws EntityNotFoundException
     */
    private function findCurrentItem(array $entityConfig, $entityId)
    {
        if (null === $entity = $this->dataService->findOne($entityConfig['class'], $entityId)) {
            throw new EntityNotFoundException(['entity' => $entityConfig, 'entity_id' => $entityId]);
        }

        return $entity;
    }
}
