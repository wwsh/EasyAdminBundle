<?php

/*
 * This file is part of the EasyAdminBundle.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JavierEguiluz\Bundle\EasyAdminBundle\Twig;

use Doctrine\ORM\Mapping\ClassMetadata;
use JavierEguiluz\Bundle\EasyAdminBundle\Configuration\Configurator;
use JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine\ClassMetadataWrapperInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Defines the filters and functions used to render the bundle's templates.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class EasyAdminTwigExtension extends \Twig_Extension
{
    private $configurator;
    private $accessor;
    private $debug;

    public function __construct(Configurator $configurator, PropertyAccessor $accessor, $debug = false)
    {
        $this->configurator = $configurator;
        $this->accessor = $accessor;
        $this->debug = $debug;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('easyadmin_render_field_for_*_view', array($this, 'renderEntityField'), array('is_safe' => array('html'), 'needs_environment' => true)),
            new \Twig_SimpleFunction('easyadmin_config', array($this, 'getBackendConfiguration')),
            new \Twig_SimpleFunction('easyadmin_entity', array($this, 'getEntityConfiguration')),
            new \Twig_SimpleFunction('easyadmin_action_is_enabled', array($this, 'isActionEnabled')),
            new \Twig_SimpleFunction('easyadmin_action_is_enabled_for_*_view', array($this, 'isActionEnabled')),
            new \Twig_SimpleFunction('easyadmin_get_action', array($this, 'getActionConfiguration')),
            new \Twig_SimpleFunction('easyadmin_get_action_for_*_view', array($this, 'getActionConfiguration')),
            new \Twig_SimpleFunction('easyadmin_get_actions_for_*_item', array($this, 'getActionsForItem')),
        );
    }

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('easyadmin_truncate', array($this, 'truncateText'), array('needs_environment' => true)),
            new \Twig_SimpleFilter('easyadmin_urldecode', 'urldecode'),
        );
    }

    /**
     * Returns the entire backend configuration or the value corresponding to
     * the provided key. The dots of the key are automatically transformed into
     * nested keys. Example: 'assets.css' => $config['assets']['css'].
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function getBackendConfiguration($key = null)
    {
        return $this->configurator->getBackendConfig($key);
    }

    /**
     * Returns the entire configuration of the given entity.
     *
     * @param string $entityName
     *
     * @return array|null
     */
    public function getEntityConfiguration($entityName)
    {
        return null !== $this->getBackendConfiguration('entities.'.$entityName)
            ? $this->configurator->getEntityConfig($entityName)
            : null;
    }

    /**
     * Renders the value stored in a property/field of the given entity. This
     * function contains a lot of code protections to avoid errors when the
     * property doesn't exist or its value is not accessible. This ensures that
     * the function never generates a warning or error message when calling it.
     *
     * @param string $view          The view in which the item is being rendered
     * @param string $entityName    The name of the entity associated with the item
     * @param object $item          The item which is being rendered
     * @param array  $fieldMetadata The metadata of the actual field being rendered
     *
     * @return string
     */
    public function renderEntityField(\Twig_Environment $twig, $view, $entityName, $item, array $fieldMetadata)
    {
        $entityConfiguration = $this->configurator->getEntityConfig($entityName);
        $fieldName = $fieldMetadata['property'];

        try {
            $value = $this->accessor->getValue($item, $fieldName);
        } catch (\Exception $e) {
            return $twig->render($entityConfiguration['templates']['label_inaccessible'], array('view' => $view));
        }

        try {
            $fieldType = $fieldMetadata['dataType'];
            $templateParameters = array(
                'field_options' => $fieldMetadata,
                'item' => $item,
                'value' => $value,
                'view' => $view,
            );

            if (null === $value) {
                return $twig->render($entityConfiguration['templates']['label_null'], $templateParameters);
            }

            // when a virtual field doesn't define it's type, consider it a string
            if (true === $fieldMetadata['virtual'] && null === $fieldType) {
                $templateParameters['value'] = strval($value);
            }

            if ('image' === $fieldType) {
                // avoid displaying broken images when the entity defines no image
                if (empty($value)) {
                    return $twig->render($entityConfiguration['templates']['label_empty'], $templateParameters);
                }

                // absolute URLs (http or https) and protocol-relative URLs (//) are rendered unmodified
                if (1 === preg_match('/^(http[s]?|\/\/).*/i', $value)) {
                    $imageUrl = $value;
                } else {
                    $imageUrl = isset($fieldMetadata['base_path'])
                        ? rtrim($fieldMetadata['base_path'], '/').'/'.ltrim($value, '/')
                        : '/'.ltrim($value, '/');
                }

                $templateParameters['value'] = $imageUrl;
                $templateParameters['uuid'] = md5($imageUrl);
            }

            if (in_array($fieldType, array('array', 'simple_array')) && empty($value)) {
                return $twig->render($entityConfiguration['templates']['label_empty'], $templateParameters);
            }

            $isAssociation = 'association' === $fieldType || 'association_odm' === $fieldType;
            
            if (true === $isAssociation) {
                $targetEntityConfig = $this->configurator->getEntityConfigByClass($fieldMetadata['targetEntity']);
                if (null === $targetEntityConfig) {
                    // the associated entity is not managed by EasyAdmin
                    return $twig->render($fieldMetadata['template'], $templateParameters);
                }

                $isShowActionAllowed = $this->isActionEnabled($view, 'show', $targetEntityConfig['name']);
            }
            
            if (true === $isAssociation && ($fieldMetadata['associationType'] === ClassMetadataWrapperInterface::RELATION_TYPE_ONE)) {
                // the try..catch block is required because we can't use
                // $accessor->isReadable(), which is unavailable in Symfony 2.3
                try {
                    $primaryKeyValue = $this->accessor->getValue($value, $targetEntityConfig['primary_key_field_name']);
                } catch (\Exception $e) {
                    $primaryKeyValue = null;
                }

                // get the string representation of the associated *-to-one entity
                if (method_exists($value, '__toString')) {
                    $templateParameters['value'] = (string) $value;
                } elseif (null !== $primaryKeyValue) {
                    $templateParameters['value'] = sprintf('%s #%s', $targetEntityConfig['name'], $primaryKeyValue);
                } else {
                    $templateParameters['value'] = $this->getClassShortName($fieldMetadata['targetEntity']);
                }

                // if the associated entity is managed by EasyAdmin, and the "show"
                // action is enabled for the associated entity, display a link to it
                if (null !== $targetEntityConfig && null !== $primaryKeyValue && $isShowActionAllowed) {
                    $templateParameters['link_parameters'] = array('entity' => $targetEntityConfig['name'], 'action' => 'show', 'id' => $primaryKeyValue);
                }
            }

            if (true === $isAssociation && ($fieldMetadata['associationType'] === ClassMetadataWrapperInterface::RELATION_TYPE_MANY)) {
                // if the associated entity is managed by EasyAdmin, and the "show"
                // action is enabled for the associated entity, display a link to it
                if (null !== $targetEntityConfig && $isShowActionAllowed) {
                    $templateParameters['link_parameters'] = array('entity' => $targetEntityConfig['name'], 'action' => 'show', 'primary_key_name' => $targetEntityConfig['primary_key_field_name']);
                }
            }

            return $twig->render($fieldMetadata['template'], $templateParameters);
        } catch (\Exception $e) {
            if ($this->debug) {
                throw $e;
            }

            return $twig->render($entityConfiguration['templates']['label_undefined'], array('view' => $view));
        }
    }

    /**
     * Checks whether the given 'action' is enabled for the given 'entity'.
     *
     * @param string $action
     * @param string $entityName
     *
     * @return bool
     */
    public function isActionEnabled($view, $action, $entityName)
    {
        return $this->configurator->isActionEnabled($entityName, $view, $action);
    }

    /**
     * Returns the full action configuration for the given 'entity' and 'view'.
     *
     * @param string $action
     * @param string $entityName
     *
     * @return bool
     */
    public function getActionConfiguration($view, $action, $entityName)
    {
        return $this->configurator->getActionConfig($entityName, $view, $action);
    }

    /**
     * Returns the actions configured for each item displayed in the given view.
     * This method is needed because some actions are displayed globally for the
     * entire view (e.g. 'new' action in 'list' view).
     *
     * @param string $entityName
     *
     * @return array
     */
    public function getActionsForItem($view, $entityName)
    {
        try {
            $entityConfig = $this->configurator->getEntityConfig($entityName);
        } catch (\Exception $e) {
            return array();
        }

        $disabledActions = $entityConfig['disabled_actions'];
        $viewActions = $entityConfig[$view]['actions'];

        $actionsExcludedForItems = array(
            'list' => array('new', 'search'),
            'edit' => array(),
            'new' => array(),
            'show' => array(),
        );
        $excludedActions = $actionsExcludedForItems[$view];

        return array_filter($viewActions, function ($action) use ($excludedActions, $disabledActions) {
            return !in_array($action['name'], $excludedActions) && !in_array($action['name'], $disabledActions);
        });
    }

    /*
     * Copied from the official Text Twig extension.
     *
     * code: https://github.com/twigphp/Twig-extensions/blob/master/lib/Twig/Extensions/Extension/Text.php
     * author: Henrik Bjornskov <hb@peytz.dk>
     * copyright holder: (c) 2009 Fabien Potencier
     */
    public function truncateText(\Twig_Environment $env, $value, $length = 64, $preserve = false, $separator = '...')
    {
        try {
            $value = (string) $value;
        } catch (\Exception $e) {
            $value = '';
        }

        if (function_exists('mb_get_info')) {
            if (mb_strlen($value, $env->getCharset()) > $length) {
                if ($preserve) {
                    // If breakpoint is on the last word, return the value without separator.
                    if (false === ($breakpoint = mb_strpos($value, ' ', $length, $env->getCharset()))) {
                        return $value;
                    }

                    $length = $breakpoint;
                }

                return rtrim(mb_substr($value, 0, $length, $env->getCharset())).$separator;
            }

            return $value;
        }

        if (strlen($value) > $length) {
            if ($preserve) {
                if (false !== ($breakpoint = strpos($value, ' ', $length))) {
                    $length = $breakpoint;
                }
            }

            return rtrim(substr($value, 0, $length)).$separator;
        }

        return $value;
    }

    /**
     * It returns the last part of the fully qualified class name
     * (e.g. 'AppBundle\Entity\User' -> 'User').
     *
     * @param string $fqcn
     *
     * @return string
     */
    private function getClassShortName($fqcn)
    {
        $classParts = explode('\\', $fqcn);
        $className = end($classParts);

        return $className;
    }

    public function getName()
    {
        return 'easyadmin_extension';
    }
}
