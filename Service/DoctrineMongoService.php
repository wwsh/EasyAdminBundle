<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Service;

use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use JavierEguiluz\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use JavierEguiluz\Bundle\EasyAdminBundle\Factory\QueryBuilderFactory;
use JavierEguiluz\Bundle\EasyAdminBundle\Wrapper\Doctrine\ODMClassMetadataWrapper;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;
use Pagerfanta\Pagerfanta;

/**
 * Contains MongoDB specific operations.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class DoctrineMongoService extends AbstractDataService implements DataServiceInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $manager;

    /**
     * @param $elementClass
     * @param $elementId
     * @return object
     */
    public function findOne($elementClass, $elementId)
    {
        return $this->manager->getManagerForClass($elementClass)->find($elementClass, $elementId);
    }

    /**
     * Performs a database query to get all the records related to the given
     * document. It supports pagination and field sorting.
     *
     * @param      $elementClass
     * @param int  $page
     * @param int  $maxPerPage
     * @param null $sortField
     * @param null $sortDirection
     * @return Pagerfanta
     */
    public function findAll($elementClass, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null)
    {
        if (empty($sortDirection) || !in_array(strtoupper($sortDirection), array('ASC', 'DESC'))) {
            $sortDirection = 'DESC';
        }

        $queryBuilder = QueryBuilderFactory::createListQueryBuilder(
            $this->manager,
            $elementClass,
            $sortDirection,
            $sortField
        );

        $this->dispatch(EasyAdminEvents::POST_LIST_QUERY_BUILDER, array(
            'query_builder'  => $queryBuilder,
            'sort_field'     => $sortField,
            'sort_direction' => $sortDirection,
        ));

        $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder, false, false));
        $paginator->setMaxPerPage($maxPerPage);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @param       $elementClass
     * @param       $searchQuery
     * @param array $searchableFields
     * @param int   $page
     * @param int   $maxPerPage
     * @param null  $sortField
     * @param null  $sortDirection
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
        
        $queryBuilder = QueryBuilderFactory::createSearchQueryBuilder(
            $this->manager,
            $elementClass,
            $searchQuery,
            $searchableFields,
            $sortField,
            $sortDirection
        );

        $this->dispatch(EasyAdminEvents::POST_SEARCH_QUERY_BUILDER, array(
            'query_builder'     => $queryBuilder,
            'search_query'      => $searchQuery,
            'searchable_fields' => $searchableFields,
        ));

        $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder, false, false));
        $paginator->setMaxPerPage($maxPerPage);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata($elementClass)
    {
        $metadata = parent::getMetadata($elementClass);

        if (null === $metadata) {
            return null;
        }
        
        return new ODMClassMetadataWrapper($metadata);
    }
}