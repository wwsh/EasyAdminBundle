<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\EventListener;

use JavierEguiluz\Bundle\EasyAdminBundle\Exception\ElementNotFoundException;
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

        $this->request->attributes->set('easyadmin', array(
            'element' => $element = $event->getArgument('element'),
            'view'    => $this->request->query->get('action', 'list'),
            'item'    => ($id = $this->request->query->get('id')) ? $this->findCurrentItem($element, $id) : null,
        ));
    }

    /**
     * Looks for the object that corresponds to the selected 'id' of the current entity/document.
     *
     * @param array $elementConfig
     * @param mixed $elementId
     *
     * @return object The entity
     *
     * @throws ElementNotFoundException
     */
    private function findCurrentItem(array $elementConfig, $elementId)
    {
        if (null === $element = $this->dataService->findOne($elementConfig['class'], $elementId)) {
            throw new ElementNotFoundException(array('element' => $elementConfig, 'element_id' => $elementId));
        }

        return $element;
    }
}
