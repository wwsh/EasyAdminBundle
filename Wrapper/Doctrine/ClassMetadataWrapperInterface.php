<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine;

/**
 * Wrapping up the ClassMetadata to provide an unified interface.
 * The interface outlook.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
interface ClassMetadataWrapperInterface
{
    const ENTITY = 'entity';
    const DOCUMENT = 'document';
    
    const RELATION_TYPE_MANY = 'MANY';
    
    /**
     * ClassMetadataWrapperInterface constructor.
     * @param $classMetadata
     */
    public function __construct($classMetadata);
    
    public function getSingleIdentifierFieldName();
    
    public function getType();
    
    public function getFieldMappings();
    
    public function getAssociationMappings();
    
    public function getAssociationMetadataTypeFor($relationType);
    
    public function isIdentifierComposite();
}