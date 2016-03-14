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
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata as ODMClassMetadata;
use JavierEguiluz\Bundle\EasyAdminBundle\Service\DoctrineDataProxyService;

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
        if (isset($backendConfig['entities'])) {
            foreach ($backendConfig['entities'] as $entityName => $entityConfig) {
                $entityMetadata = $this->dataService->getMetadata($entityConfig['class']);
                
                $entityConfig['primary_key_field_name'] = $entityMetadata->getSingleIdentifierFieldName();
                $entityConfig['properties']             = $this->processEntityPropertiesMetadata($entityMetadata);

                $backendConfig['entities'][$entityName] = $entityConfig;
            }
        }

        // This is going to handle ODM documents as well
        if (isset($backendConfig['documents'])) {
            foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
                $metadata = $this->dataService->getMetadata($documentConfig['class']);

                $documentConfig['primary_key_field_name'] = $this->processDocumentMetadataForPrimaryKey($metadata);
                $documentConfig['properties']             = $this->processDocumentPropertiesMetadata($metadata);

                $backendConfig['documents'][$documentName] = $documentConfig;
            }
        }

        return $backendConfig;
    }

    /**
     * Takes the entity metadata introspected via Doctrine and completes its
     * contents to simplify data processing for the rest of the application.
     *
     * @param ClassMetadata $entityMetadata The entity metadata introspected via Doctrine
     *
     * @return array The entity properties metadata provided by Doctrine
     */
    private function processEntityPropertiesMetadata(ClassMetadata $entityMetadata)
    {
        $entityPropertiesMetadata = array();

        if ($entityMetadata->isIdentifierComposite) {
            throw new \RuntimeException(sprintf("The '%s' element isn't valid because it contains a composite primary key.",
                $entityMetadata->name));
        }

        // introspect regular entity fields
        foreach ($entityMetadata->fieldMappings as $fieldName => $fieldMetadata) {
            $entityPropertiesMetadata[$fieldName] = $fieldMetadata;
        }

        // introspect fields for entity associations
        foreach ($entityMetadata->associationMappings as $fieldName => $associationMetadata) {
            $entityPropertiesMetadata[$fieldName] = array_merge($associationMetadata, array(
                'type'            => 'association',
                'associationType' => $associationMetadata['type'],
            ));

            // associations different from *-to-one cannot be sorted
            if ($associationMetadata['type'] & ClassMetadata::TO_MANY) {
                $entityPropertiesMetadata[$fieldName]['sortable'] = false;
            }
        }

        return $entityPropertiesMetadata;
    }

    /**
     * Document metadata properties processing.
     *
     * @param $metadata
     * @return array
     */
    private function processDocumentPropertiesMetadata(ODMClassMetadata $metadata)
    {
        $propertiesMetadata = array();

        // introspect regular entity fields
        foreach ($metadata->fieldMappings as $fieldName => $fieldMetadata) {
            $propertiesMetadata[$fieldName] = $fieldMetadata;
        }

        // introspect fields for document associations
        foreach ($metadata->associationMappings as $fieldName => $associationMetadata) {
            $propertiesMetadata[$fieldName] = array_merge($associationMetadata, array(
                'type'            => 'association_odm',
                'associationType' => $associationMetadata['type'],
            ));

            // associations different from *-to-one cannot be sorted
            if ($associationMetadata['type'] & ODMClassMetadata::MANY) {
                $propertiesMetadata[$fieldName]['sortable'] = false;
            }
        }

        return $propertiesMetadata;
    }

    private function processDocumentMetadataForPrimaryKey(
        ODMClassMetadata $metadata
    ) {
        foreach ($metadata->fieldMappings as $field => $fieldData) {
            if ($fieldData['id'] === true) {
                return $field;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Document %s has no single primary key field defined',
                $metadata['name']
            )
        );
    }
}
