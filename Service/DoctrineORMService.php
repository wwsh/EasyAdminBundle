<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use JavierEguiluz\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use JavierEguiluz\Bundle\EasyAdminBundle\Factory\QueryBuilderFactory;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

/**
 * Contains Doctrine ORM operations.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class DoctrineORMService extends AbstractDataService implements DataServiceInterface
{
    /**
     * @var Registry
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
     * entity. It supports pagination and field sorting.
     *
     * @param string      $elementClass
     * @param int         $page
     * @param int         $maxPerPage
     * @param string|null $sortField
     * @param string|null $sortDirection
     *
     * @return Pagerfanta The paginated query results
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

        $paginator = new Pagerfanta(new DoctrineORMAdapter($queryBuilder, false, false));
        $paginator->setMaxPerPage($maxPerPage);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * Performs a database query based on the search query provided by the user.
     * It supports pagination and field sorting.
     *
     * @param string      $entityClass
     * @param string      $searchQuery
     * @param array       $searchableFields
     * @param int         $page
     * @param int         $maxPerPage
     * @param string|null $sortField
     * @param string|null $sortDirection
     *
     * @return Pagerfanta The paginated query results
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

        $paginator = new Pagerfanta(new DoctrineORMAdapter($queryBuilder, false, false));
        $paginator->setMaxPerPage($maxPerPage);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

}