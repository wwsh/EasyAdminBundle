<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Form\Type\Configurator;

use Symfony\Component\Form\FormConfigInterface;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata as ODMClassMetadata;

/**
 * This configurator is applied to any form field of type 'association_odm' 
 * specifically for the ODM documents.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class DocumentTypeConfigurator implements TypeConfiguratorInterface
{

    /**
     * {@inheritdoc}
     */
    public function configure($name, array $options, array $metadata, FormConfigInterface $parentConfig)
    {
        if (!isset($options['class'])) {
            $options['class'] = $metadata['targetDocument'];
        }

        if ($metadata['associationType'] === ODMClassMetadata::MANY) {
            $options['attr']['multiple'] = true;
        }

        // Supported associations are displayed using advanced JavaScript widgets
        $options['attr']['data-widget'] = 'select2';

        // Configure "placeholder" option for document fields
        if (($metadata['associationType'] === ODMClassMetadata::ONE)
            && !isset($options[$placeHolderOptionName = $this->getPlaceholderOptionName()])
            && isset($options['required']) && false === $options['required']
        ) {
            $options[$placeHolderOptionName] = 'label.form.empty_value';
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($type, array $options, array $metadata)
    {
        return 'document' === $type && 'association_odm' === $metadata['type'];
    }

    /**
     * BC for Sf < 2.6
     *
     * The "empty_value" option in the types "choice", "date", "datetime" and "time"
     * was deprecated in 2.6 and replaced by a new option "placeholder".
     *
     * This method is ripped from the EntityTypeConfigurator class.
     *
     * @return string
     */
    private function getPlaceholderOptionName()
    {
        return defined('Symfony\\Component\\Form\\Extension\\Validator\\Constraints\\Form::NOT_SYNCHRONIZED_ERROR')
            ? 'placeholder'
            : 'empty_value';
    }
}