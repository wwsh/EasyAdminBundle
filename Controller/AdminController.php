<?php

/*
 * This file is part of the EasyAdminBundle.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JavierEguiluz\Bundle\EasyAdminBundle\Controller;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use JavierEguiluz\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use JavierEguiluz\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use JavierEguiluz\Bundle\EasyAdminBundle\Exception\NoEntitiesConfiguredException;
use JavierEguiluz\Bundle\EasyAdminBundle\Exception\UndefinedEntityException;
use JavierEguiluz\Bundle\EasyAdminBundle\Service\DataServiceInterface;
use JavierEguiluz\Bundle\EasyAdminBundle\Service\DoctrineDataProxyService;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The controller used to render all the default EasyAdmin actions.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class AdminController extends Controller
{
    protected $config;

    protected $element = array();

    /** @var Request */
    protected $request;

    /**
     * @var DoctrineDataProxyService
     */
    protected $dataService;

    /**
     * Utility method which initializes the configuration of the entity on which
     * the user is performing the action.
     *
     * @param Request $request
     */
    protected function initialize(Request $request)
    {
        $this->dispatch(EasyAdminEvents::PRE_INITIALIZE);

        $this->config = $this->container->getParameter('easyadmin.config');

        if (0 === count($this->config['entities'])) {
            throw new NoEntitiesConfiguredException();
        }

        // this condition happens when accessing the backend homepage, which
        // then redirects to the 'list' action of the first configured entity
        if (null === $elementName = $request->query->get('element')) {
            return;
        }

        // @todo: Not really consequent: "element" from URL is checked against "entities"

        if (!array_key_exists($elementName, $this->config['entities'])) {
            throw new UndefinedEntityException(array('entity_name' => $elementName));
        }

        $this->element = $this->get('easyadmin.configurator')->getElementConfiguration($elementName);

        if (!$request->query->has('sortField')) {
            $request->query->set('sortField', $this->element['primary_key_field_name']);
        }

        if (!$request->query->has('sortDirection') || !in_array(strtoupper($request->query->get('sortDirection')),
                array('ASC', 'DESC'))
        ) {
            $request->query->set('sortDirection', 'DESC');
        }

        $this->dataService = $this->get('doctrine_data_proxy_service');
        $this->dataService->setEventDispatcherController($this); // the dataService can also dispatch events

        $this->request = $request;

        $this->dispatch(EasyAdminEvents::POST_INITIALIZE);
    }

//    protected $em;


    /**
     * @Route("/", name="easyadmin")
     * @Route("/", name="admin")
     *
     * The 'admin' route is deprecated since version 1.8.0 and it will be removed in 2.0.
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function indexAction(Request $request)
    {
        $this->initialize($request);

        if (null === $request->query->get('element')) {
            return $this->redirectToBackendHomepage();
        }

        $action = $request->query->get('action', 'list');
        if (!$this->isActionAllowed($action)) {
            throw new ForbiddenActionException(array('action' => $action, 'element' => $this->element['name']));
        }

        return $this->executeDynamicMethod($action . '<EntityName>Action');
    }

    /**
     * It renders the main CSS applied to the backend design. This controller
     * allows to generate dynamic CSS files that use variables without the need
     * to set up a CSS preprocessing toolchain.
     *
     * @Route("/_css/easyadmin.css", name="_easyadmin_render_css")
     *
     * @return Response
     */
    public function renderCssAction()
    {
        $config = $this->container->getParameter('easyadmin.config');

        $cssContent = $this->renderView('@EasyAdmin/css/easyadmin.css.twig', array(
            'brand_color'  => $config['design']['brand_color'],
            'color_scheme' => $config['design']['color_scheme'],
        ));

        return Response::create($cssContent, 200, array('Content-Type' => 'text/css'))
                       ->setPublic()
                       ->setSharedMaxAge(600);
    }

    public function dispatch($eventName, array $arguments = array())
    {
        $arguments = array_replace(array(
            'config'      => $this->config,
            'dataService' => $this->dataService,
            'element'     => $this->element,
            'request'     => $this->request,
        ), $arguments);

        $subject = isset($arguments['paginator']) ? $arguments['paginator'] : $arguments['element'];
        $event   = new GenericEvent($subject, $arguments);

        $this->get('event_dispatcher')->dispatch($eventName, $event);
    }

    /**
     * The method that is executed when the user performs a 'list' action on an entity.
     *
     * @return Response
     */
    protected function listAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_LIST);

        $fields    = $this->element['list']['fields'];
        $paginator = $this->dataService->findAll($this->element['class'], $this->request->query->get('page', 1),
            $this->config['list']['max_results'], $this->request->query->get('sortField'),
            $this->request->query->get('sortDirection'));

        $this->dispatch(EasyAdminEvents::POST_LIST, array('paginator' => $paginator));

        return $this->render($this->element['templates']['list'], array(
            'paginator'            => $paginator,
            'fields'               => $fields,
            'delete_form_template' => $this->createDeleteForm($this->element['name'], '__id__')->createView(),
        ));
    }

    /**
     * The method that is executed when the user performs a 'edit' action on an entity.
     *
     * @return RedirectResponse|Response
     */
    protected function editAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_EDIT);

        $id        = $this->request->query->get('id');
        $easyadmin = $this->request->attributes->get('easyadmin');
        $element   = $easyadmin['item'];

        if ($this->request->isXmlHttpRequest() && $property = $this->request->query->get('property')) {
            $newValue       = 'true' === strtolower($this->request->query->get('newValue'));
            $fieldsMetadata = $this->element['list']['fields'];

            if (!isset($fieldsMetadata[$property]) || 'toggle' != $fieldsMetadata[$property]['dataType']) {
                throw new \Exception(sprintf('The type of the "%s" property is not "toggle".', $property));
            }

            $this->updateEntityProperty($element, $property, $newValue);

            return new Response((string)$newValue);
        }

        $fields = $this->element['edit']['fields'];

        $editForm   = $this->executeDynamicMethod('create<EntityName>EditForm', array($element, $fields));
        $deleteForm = $this->createDeleteForm($this->element['name'], $id);

        $editForm->handleRequest($this->request);
        if ($editForm->isValid()) {
            $this->dispatch(EasyAdminEvents::PRE_UPDATE, array('element' => $element));

            $this->executeDynamicMethod('preUpdate<EntityName>Entity', array($element));

            $this->dataService->flush($element);

            $this->dispatch(EasyAdminEvents::POST_UPDATE, array('element' => $element));

            $refererUrl = $this->request->query->get('referer', '');

            return !empty($refererUrl)
                ? $this->redirect(urldecode($refererUrl))
                : $this->redirect($this->generateUrl('easyadmin',
                    array('action' => 'list', 'element' => $this->element['name'])));
        }

        $this->dispatch(EasyAdminEvents::POST_EDIT);

        return $this->render($this->element['templates']['edit'], array(
            'form'          => $editForm->createView(),
            'entity_fields' => $fields,
            'element'       => $element,
            'delete_form'   => $deleteForm->createView(),
        ));
    }

    /**
     * The method that is executed when the user performs a 'show' action on an entity.
     *
     * @return Response
     */
    protected function showAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_SHOW);

        $id        = $this->request->query->get('id');
        $easyadmin = $this->request->attributes->get('easyadmin');
        $element   = $easyadmin['item'];

        $fields     = $this->element['show']['fields'];
        $deleteForm = $this->createDeleteForm($this->element['name'], $id);

        $this->dispatch(EasyAdminEvents::POST_SHOW, array(
            'deleteForm' => $deleteForm,
            'fields'     => $fields,
            'entity'     => $element,
        ));

        return $this->render($this->element['templates']['show'], array(
            'element'     => $element,
            'fields'      => $fields,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * The method that is executed when the user performs a 'new' action on an entity.
     *
     * @return RedirectResponse|Response
     */
    protected function newAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_NEW);

        $entity = $this->executeDynamicMethod('createNew<EntityName>Entity');

        $easyadmin         = $this->request->attributes->get('easyadmin');
        $easyadmin['item'] = $entity;
        $this->request->attributes->set('easyadmin', $easyadmin);

        $fields = $this->element['new']['fields'];

        $newForm = $this->executeDynamicMethod('create<EntityName>NewForm', array($entity, $fields));

        $newForm->handleRequest($this->request);
        if ($newForm->isValid()) {
            $this->dispatch(EasyAdminEvents::PRE_PERSIST, array('element' => $entity));

            $this->executeDynamicMethod('prePersist<EntityName>Entity', array($entity));

            $this->dataService->persistAndFlush($entity);

            $this->dispatch(EasyAdminEvents::POST_PERSIST, array('element' => $entity));

            $refererUrl = $this->request->query->get('referer', '');

            return !empty($refererUrl)
                ? $this->redirect(urldecode($refererUrl))
                : $this->redirect($this->generateUrl('easyadmin',
                    array('action' => 'list', 'element' => $this->element['name'])));
        }

        $this->dispatch(EasyAdminEvents::POST_NEW, array(
            'entity_fields' => $fields,
            'form'          => $newForm,
            'entity'        => $entity,
        ));

        return $this->render($this->element['templates']['new'], array(
            'form'          => $newForm->createView(),
            'entity_fields' => $fields,
            'entity'        => $entity,
        ));
    }

    /**
     * The method that is executed when the user performs a 'delete' action to
     * remove any entity.
     *
     * @return RedirectResponse
     */
    protected function deleteAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_DELETE);

        if ('DELETE' !== $this->request->getMethod()) {
            return $this->redirect($this->generateUrl('easyadmin',
                array('action' => 'list', 'element' => $this->element['name'])));
        }

        $id   = $this->request->query->get('id');
        $form = $this->createDeleteForm($this->element['name'], $id);
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $easyadmin = $this->request->attributes->get('easyadmin');
            $element   = $easyadmin['item'];

            $this->dispatch(EasyAdminEvents::PRE_REMOVE, array('element' => $element));

            $this->executeDynamicMethod('preRemove<EntityName>Entity', array($element));


            $this->dataService->remove($element)
                              ->flush($element);

            $this->dispatch(EasyAdminEvents::POST_REMOVE, array('element' => $element));
        }

        $refererUrl = $this->request->query->get('referer', '');

        $this->dispatch(EasyAdminEvents::POST_DELETE);

        return !empty($refererUrl)
            ? $this->redirect(urldecode($refererUrl))
            : $this->redirect($this->generateUrl('easyadmin',
                array('action' => 'list', 'element' => $this->element['name'])));
    }

    /**
     * The method that is executed when the user performs a query on an entity.
     *
     * @return Response
     */
    protected function searchAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_SEARCH);

        $searchableFields = $this->element['search']['fields'];
        $paginator        = $this->dataService->findBy(
            $this->element['class'],
            $this->request->query->get('query'),
            $searchableFields,
            $this->request->query->get('page', 1),
            $this->config['list']['max_results'],
            $this->request->query->get('sortField'),
            $this->request->query->get('sortDirection')
        );
        
        $fields = $this->element['list']['fields'];

        $this->dispatch(EasyAdminEvents::POST_SEARCH, array(
            'fields'    => $fields,
            'paginator' => $paginator,
        ));

        return $this->render($this->element['templates']['list'], array(
            'paginator'            => $paginator,
            'fields'               => $fields,
            'delete_form_template' => $this->createDeleteForm($this->element['name'], '__id__')->createView(),
        ));
    }

    /**
     * It updates the value of some property of some entity to the new given value.
     *
     * @param mixed  $element The instance of the entity to modify
     * @param string $property The name of the property to change
     * @param bool   $value The new value of the property
     */
    private function updateEntityProperty($element, $property, $value)
    {
        $entityConfig = $this->element;

        // the method_exists() check is needed because Symfony 2.3 doesn't have isWritable() method
        if (method_exists($this->get('property_accessor'),
                'isWritable') && !$this->get('property_accessor')->isWritable($element, $property)
        ) {
            throw new \Exception(sprintf('The "%s" property of the "%s" entity is not writable.', $property,
                $entityConfig['name']));
        }

        $this->dispatch(EasyAdminEvents::PRE_UPDATE, array('element' => $element, 'newValue' => $value));

        $this->get('property_accessor')->setValue($element, $property, $value);

        $this->dataService->persistAndFlush($element);

        $this->dispatch(EasyAdminEvents::POST_UPDATE, array('entity' => $element, 'newValue' => $value));

        $this->dispatch(EasyAdminEvents::POST_EDIT);
    }

    /**
     * Creates a new object of the current managed entity.
     * This method is mostly here for override convenience, because it allows
     * the user to use his own method to customize the entity instantiation.
     *
     * @return object
     */
    protected function createNewEntity()
    {
        $elementFullyQualifiedClassName = $this->element['class'];

        return new $elementFullyQualifiedClassName();
    }

    /**
     * Allows applications to modify the entity associated with the item being
     * created before persisting it.
     *
     * @param object $entity
     */
    protected function prePersistEntity($entity)
    {
    }

    /**
     * Allows applications to modify the entity associated with the item being
     * edited before persisting it.
     *
     * @param object $entity
     */
    protected function preUpdateEntity($entity)
    {
    }

    /**
     * Allows applications to modify the entity associated with the item being
     * deleted before removing it.
     *
     * @param object $entity
     */
    protected function preRemoveEntity($entity)
    {
    }


    /**
     * Creates the form used to edit an entity.
     *
     * @param object $entity
     * @param array  $entityProperties
     *
     * @return Form
     */
    protected function createEditForm($entity, array $entityProperties)
    {
        return $this->createEntityForm($entity, $entityProperties, 'edit');
    }

    /**
     * Creates the form used to create an entity.
     *
     * @param object $entity
     * @param array  $entityProperties
     *
     * @return Form
     */
    protected function createNewForm($entity, array $entityProperties)
    {
        return $this->createEntityForm($entity, $entityProperties, 'new');
    }

    /**
     * Creates the form builder of the form used to create or edit the given entity.
     *
     * @param object $entity
     * @param string $view The name of the view where this form is used ('new' or 'edit')
     *
     * @return FormBuilder
     */
    protected function createEntityFormBuilder($entity, $view)
    {
        $formOptions = $this->executeDynamicMethod('get<EntityName>EntityFormOptions', array($entity, $view));

        $formType = $this->useLegacyFormComponent() ? 'easyadmin' : 'JavierEguiluz\\Bundle\\EasyAdminBundle\\Form\\Type\\EasyAdminFormType';

        return $this->get('form.factory')->createNamedBuilder(strtolower($this->element['name']), $formType, $entity,
            $formOptions);
    }

    /**
     * Retrieves the list of form options before sending them to the form builder.
     * This allows adding dynamic logic to the default form options.
     *
     * @param object $entity
     * @param string $view
     *
     * @return array
     */
    protected function getEntityFormOptions($entity, $view)
    {
        $formOptions           = $this->element[$view]['form_options'];
        $formOptions['entity'] = $this->element['name'];
        $formOptions['view']   = $view;

        return $formOptions;
    }

    /**
     * Creates the form object used to create or edit the given entity.
     *
     * @param object $entity
     * @param array  $entityProperties
     * @param string $view
     *
     * @return Form
     *
     * @throws \Exception
     */
    protected function createEntityForm($entity, array $entityProperties, $view)
    {
        if (method_exists($this, $customMethodName = 'create' . $this->element['name'] . 'EntityForm')) {
            $form = $this->{$customMethodName}($entity, $entityProperties, $view);
            if (!$form instanceof FormInterface) {
                throw new \Exception(sprintf(
                    'The "%s" method must return a FormInterface, "%s" given.',
                    $customMethodName, is_object($form) ? get_class($form) : gettype($form)
                ));
            }

            return $form;
        }

        $formBuilder = $this->executeDynamicMethod('create<EntityName>EntityFormBuilder', array($entity, $view));

        if (!$formBuilder instanceof FormBuilderInterface) {
            throw new \Exception(sprintf(
                'The "%s" method must return a FormBuilderInterface, "%s" given.',
                'createEntityForm', is_object($formBuilder) ? get_class($formBuilder) : gettype($formBuilder)
            ));
        }

        return $formBuilder->getForm();
    }

    /**
     * Creates the form used to delete an entity. It must be a form because
     * the deletion of the entity are always performed with the 'DELETE' HTTP method,
     * which requires a form to work in the current browsers.
     *
     * @param string $elementName
     * @param int    $entityId
     *
     * @return Form
     */
    protected function createDeleteForm($elementName, $entityId)
    {
        /** @var FormBuilder $formBuilder */
        $formBuilder = $this->get('form.factory')->createNamedBuilder('delete_form')
                            ->setAction($this->generateUrl('easyadmin',
                                array('action' => 'delete', 'element' => $elementName, 'id' => $entityId)))
                            ->setMethod('DELETE');

        $submitButtonType = $this->useLegacyFormComponent() ? 'submit' : 'Symfony\\Component\\Form\\Extension\\Core\\Type\\SubmitType';
        $formBuilder->add('submit', $submitButtonType, array('label' => 'Delete'));

        return $formBuilder->getForm();
    }

    /**
     * Utility method that checks if the given action is allowed for
     * the current entity.
     *
     * @param string $actionName
     *
     * @return bool
     */
    protected function isActionAllowed($actionName)
    {
        return false === in_array($actionName, $this->element['disabled_actions'], true);
    }

    /**
     * Utility shortcut to render an error when the requested action is not allowed
     * for the given entity.
     *
     * @param string $action
     *
     * @deprecated Use the ForbiddenException instead of this method.
     *
     * @return Response
     */
    protected function renderForbiddenActionError($action)
    {
        return $this->render('@EasyAdmin/error/forbidden_action.html.twig', array('action' => $action),
            new Response('', 403));
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
    private function executeDynamicMethod($methodNamePattern, array $arguments = array())
    {
        $methodName = str_replace('<EntityName>', $this->element['name'], $methodNamePattern);
        if (!is_callable(array($this, $methodName))) {
            $methodName = str_replace('<EntityName>', '', $methodNamePattern);
        }

        return call_user_func_array(array($this, $methodName), $arguments);
    }

    /**
     * Returns true if the legacy Form component is being used by the application.
     *
     * @return bool
     */
    private function useLegacyFormComponent()
    {
        return false === class_exists('Symfony\\Component\\Form\\Util\\StringUtil');
    }

    /**
     * Generates the backend homepage and redirects to it.
     */
    private function redirectToBackendHomepage()
    {
        $homepageConfig = $this->config['homepage'];

        $url = isset($homepageConfig['url'])
            ? $homepageConfig['url']
            : $this->get('router')->generate($homepageConfig['route'], $homepageConfig['params']);

        return $this->redirect($url);
    }
}
