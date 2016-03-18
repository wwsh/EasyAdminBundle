<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine;

/**
 * Wrapping up the ClassMetadata to provide an unified interface.
 * 
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class ClassMetadataWrapper
 * @package JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine
 */
class ClassMetadataWrapper implements ClassMetadataWrapperInterface
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
     * @return string
     */
    public function getSingleIdentifierFieldName()
    {
        return $this->classMetadata->getSingleIdentifierFieldName();
    }

    /**
     * @return string
     */
    public function getType()
    {
        return ClassMetadataWrapperInterface::ENTITY;
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
        if ($doctrineType & ClassMetadata::TO_MANY) {
            return self::RELATION_TYPE_MANY;
        }

        if ($doctrineType & ClassMetadata::TO_ONE) {
            return self::RELATION_TYPE_ONE;
        }

        throw new \RuntimeException(sprintf('Association metadata type unknown: %s', $doctrineType));
    }

    /**
     * @return bool
     */
    public function isIdentifierComposite()
    {
        return $this->classMetadata->isIdentifierComposite;
    }


}