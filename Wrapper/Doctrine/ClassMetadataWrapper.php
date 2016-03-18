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
     * Common interface to provide a proper relation type code in the
     * config metadata parser.
     *
     * @param $relationType
     * @return string
     */
    public function getAssociationMetadataTypeFor($relationType)
    {
        switch ($relationType) {
            case ClassMetadataWrapperInterface::RELATION_TYPE_MANY:
                return ClassMetadata::TO_MANY;
        }

        throw new \RuntimeException(sprintf('Association metadata type not implemented: %s', $relationType));
    }

    /**
     * @return bool
     */
    public function isIdentifierComposite()
    {
        return $this->classMetadata->isIdentifierComposite;
    }


}