<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Service;

use Doctrine\Common\Persistence\ManagerRegistry;
use JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine\ClassMetadataWrapperInterface;


/**
 * Class DoctrineDataService 
 * 
 * This provides the entire Doctrine data service to all top-level classes.
 * Class is responsible for juggling calls between ORM and ODM.
 
 * @Todo Rewrite into a nice Strategy.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class DoctrineDataProxyService
{
    const ENTITY   = 'entity';
    
    const DOCUMENT = 'document';

    /**
     * This array contains element to type (orm, mongo) associations.
     * For faster processing.
     *
     * @var array
     */
    private $elementTypeCache = [];

    /**
     * @var DoctrineORMService
     */
    private $ormService;

    /**
     * @var DoctrineMongoService
     */
    private $odmService;

    /**
     * DoctrineDataService constructor.
     * @param DataServiceInterface|DoctrineORMService   $ormService
     * @param DataServiceInterface|DoctrineMongoService $odmService
     */
    public function __construct(
        DoctrineORMService $ormService,
        DoctrineMongoService $odmService
    ) {
        $this->ormService = $ormService;
        $this->odmService = $odmService;
    }

    /**
     * Get metadata for entity or document.
     *
     * @param $elementClass
     * @return ClassMetadataWrapperInterface
     */
    public function getMetadata($elementClass)
    {
        $metadata = $this->ormService->getMetadata($elementClass);

        if (null !== $metadata) {
            // cache type for future, faster processing
            $this->elementTypeCache[$elementClass] = self::ENTITY;

            return $metadata;
        }

        $metadata = $this->odmService->getMetadata($elementClass);

        if (null !== $metadata) {
            // cache type for future, faster processing
            $this->elementTypeCache[$elementClass] = self::DOCUMENT;

            return $metadata;
        }

        throw new \RuntimeException(
            'The following element has not been registered by any doctrine manager: '
            . $elementClass
        );
    }

    /**
     * @param $dispatcher
     * @return $this
     */
    public function setEventDispatcherController($dispatcher)
    {
        $this->ormService->setEventDispatcherController($dispatcher);
        $this->odmService->setEventDispatcherController($dispatcher);

        return $this;
    }

    /**
     * @param $elementObject
     */
    public function persistAndFlush($elementObject)
    {
        return $this->elementIsType($elementObject, self::ENTITY)
            ? $this->ormService->persistAndFlush($elementObject)
            : $this->odmService->persistAndFlush($elementObject);
    }

    /**
     * @param $elementObject
     */
    public function flush($elementObject)
    {
        return $this->elementIsType($elementObject, self::ENTITY)
            ? $this->ormService->flush()
            : $this->odmService->flush();

    }

    /**
     * Getting a list of objects.
     * 
     * @Todo Does not look very nice. Should be a Strategy
     *
     * @param      $elementClass
     * @param int  $page
     * @param int  $maxPerPage
     * @param null $sortField
     * @param null $sortDirection
     * @return \Pagerfanta\Pagerfanta
     */
    public function findAll($elementClass, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null)
    {
        return $this->elementIsType($elementClass, self::ENTITY)
            ? $this->ormService->findAll($elementClass, $page, $maxPerPage, $sortField, $sortDirection)
            : $this->odmService->findAll($elementClass, $page, $maxPerPage, $sortField, $sortDirection);
    }

    /**
     * Getting single object.
     *
     * @param $elementClass
     * @param $elementId
     */
    public function findOne($elementClass, $elementId)
    {
        return $this->elementIsType($elementClass, self::ENTITY)
            ? $this->ormService->findOne($elementClass, $elementId)
            : $this->odmService->findOne($elementClass, $elementId);
    }

    /**
     * @param       $elementClass
     * @param       $searchQuery
     * @param array $searchableFields
     * @param int   $page
     * @param int   $maxPerPage
     * @param null  $sortField
     * @param null  $sortDirection
     * @return \Pagerfanta\Pagerfanta|void
     */
    public function findBy(
        $elementClass,
        $searchQuery,
        array $searchableFields,
        $page = 1,
        $maxPerPage = 15,
        $sortField = null,
        $sortDirection = null
    ) {
        return $this->elementIsType($elementClass, self::ENTITY)
            ? $this->ormService->findBy($elementClass, $searchQuery, $searchableFields, $page, $maxPerPage, $sortField, $sortDirection)
            : $this->odmService->findBy($elementClass, $searchQuery, $searchableFields, $page, $maxPerPage, $sortField, $sortDirection);
    }

    /**
     * Removing an entity/object.
     *
     * @param $elementObject
     * @return $this
     */
    public function remove($elementObject)
    {
        $this->elementIsType($elementObject, self::ENTITY)
            ? $this->ormService->remove($elementObject)
            : $this->odmService->remove($elementObject);    
        return $this;
    }
    
    /**
     * @param $elementClass
     * @param $matchesType
     * @return bool|string
     */
    private function elementIsType($elementClass, $matchesType)
    {
        if (is_object($elementClass)) {
            $elementClass = get_class($elementClass);
        }

        if (!is_string($elementClass)) {
            throw new \RuntimeException('Method is accepting strings or objects only');

        }
        if (isset($this->elementTypeCache[$elementClass])) {
            return $this->elementTypeCache[$elementClass] === $matchesType;
        }

        return $this->probeElementClass($elementClass) === $matchesType;
    }

    /**
     * @param $elementClass
     * @return string
     */
    private function probeElementClass($elementClass)
    {
        if (strpos($elementClass, 'Entity\\') !== false) {
            $this->elementTypeCache[$elementClass] = self::ENTITY;

            return self::ENTITY;
        }
        $this->elementTypeCache[$elementClass] = self::DOCUMENT;

        return self::DOCUMENT;
    }
}