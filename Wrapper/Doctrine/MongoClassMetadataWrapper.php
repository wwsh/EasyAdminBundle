<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;

/**
 * Wrapping up the ClassMetadata to provide an unified interface.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */

class MongoClassMetadataWrapper implements ClassMetadataWrapperInterface
{
    /**
     * @var ClassMetadata
     */
    private $classMetadata;

    /**
     * @inheritDoc
     */
    public function __construct($classMetadata)
    {
        $this->classMetadata = $classMetadata;
    }

    /**
     * @return int|string
     */
    public function getSingleIdentifierFieldName()
    {
        foreach ($this->classMetadata->fieldMappings as $field => $fieldData) {
            if ($fieldData['id'] === true) {
                return $field;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Document %s has no single primary key field defined',
                $this->classMetadata['name']
            )
        );
    }

    /**
     * @return string
     */
    public function getType()
    {
        return ClassMetadataWrapperInterface::DOCUMENT;
    }

    /**
     * @return array
     */
    public function getFieldMappings()
    {
        return $this->classMetadata->fieldMappings;
    }

    /**
     * @return array
     */
    public function getAssociationMappings()
    {
        return $this->classMetadata->associationMappings;
    }

    /**
     * Returns an unified relation type.
     * 
     * @param $doctrineType
     * @return string
     */
    public function getEasyAssociationType($doctrineType)
    {
        if ($doctrineType & ClassMetadata::MANY) {
            return self::RELATION_TYPE_MANY;
        }

        if ($doctrineType & ClassMetadata::ONE) {
            return self::RELATION_TYPE_ONE;
        }
        
        throw new \RuntimeException(sprintf('Association metadata type unknown: %s', $doctrineType));
    }

    /**
     * I don't think this is relevant in case of ODM.
     * 
     * @return bool
     */
    public function isIdentifierComposite()
    {
        return false;
    }
}