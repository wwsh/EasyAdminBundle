<?php

/*
 * This file is part of the EasyAdminBundle.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JavierEguiluz\Bundle\EasyAdminBundle\Configuration;


use Doctrine\ORM\Mapping\ClassMetadata;
use JavierEguiluz\Bundle\EasyAdminBundle\Service\DoctrineDataProxyService;
use JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine\ClassMetadataWrapperInterface;

/**
 * Introspects the metadata of the Doctrine entities to complete the
 * configuration of the properties.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class MetadataConfigPass implements ConfigPassInterface
{

    /**
     * @var DoctrineDataProxyService
     */
    private $dataService;

    /**
     * MetadataConfigPass constructor.
     * @param DoctrineDataProxyService $dataService
     */
    public function __construct(DoctrineDataProxyService $dataService)
    {
        $this->dataService = $dataService;
    }

    public function process(array $backendConfig)
    {
        foreach ($backendConfig['entities'] as $entityName => $entityConfig) {
            $entityMetadata = $this->dataService->getMetadata($entityConfig['class']);

            $entityConfig['primary_key_field_name'] = $entityMetadata->getSingleIdentifierFieldName();

            $entityConfig['properties'] = $this->processEntityPropertiesMetadata($entityMetadata);

            $backendConfig['entities'][$entityName] = $entityConfig;
        }

        return $backendConfig;
    }

    /**
     * Takes the entity metadata introspected via Doctrine and completes its
     * contents to simplify data processing for the rest of the application.
     *
     * @param ClassMetadataWrapperInterface $entityMetadata The entity metadata introspected via Doctrine
     * @return array The entity properties metadata provided by Doctrine
     */
    private function processEntityPropertiesMetadata(ClassMetadataWrapperInterface $entityMetadata)
    {
        $entityPropertiesMetadata = [];

        $entityAssociationType = $entityMetadata->getType() === ClassMetadataWrapperInterface::ENTITY
            ? 'association' : 'association_odm';

        if ($entityMetadata->isIdentifierComposite()) {
            throw new \RuntimeException(sprintf("The '%s' entity isn't valid because it contains a composite primary key.",
                                                $entityMetadata->name));
        }

        // introspect regular entity fields
        foreach ($entityMetadata->getFieldMappings() as $fieldName => $fieldMetadata) {
            $entityPropertiesMetadata[$fieldName] = $fieldMetadata;
        }

        // introspect fields for entity associations
        foreach ($entityMetadata->getAssociationMappings() as $fieldName => $associationMetadata) {
            $entityPropertiesMetadata[$fieldName] = array_merge($associationMetadata, [
                'type'            => $entityAssociationType,
                'associationType' => $associationMetadata['type'],
            ]);

            // associations different from *-to-one cannot be sorted
            if ($associationMetadata['type']
                & $entityMetadata->getAssociationMetadataTypeFor(ClassMetadataWrapperInterface::RELATION_TYPE_MANY)
            ) {
                $entityPropertiesMetadata[$fieldName]['sortable'] = false;
            }
        }

        return $entityPropertiesMetadata;
    }
}
