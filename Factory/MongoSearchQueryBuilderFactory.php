<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Factory;

use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Class MongoSearchQueryBuilderFactory
 * @package JavierEguiluz\Bundle\EasyAdminBundle\Factory
 *
 * This class creates the Query Builder for the search.
 * 
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class MongoSearchQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var DocumentManager
     */
    private $dm;

    private $documentClass;

    private $searchQuery;

    private $searchableFields;

    private $page;

    private $maxPerPage;

    private $sortField;

    private $sortDirection;

    /**
     * MongoSearchQueryBuilderFactory constructor.
     * @param ManagerRegistry $manager
     * @param                 $documentClass
     * @param                 $searchQuery
     * @param array           $searchableFields
     * @param int             $page
     * @param int             $maxPerPage
     * @param null            $sortField
     * @param null            $sortDirection
     */
    public function __construct(
        ManagerRegistry $manager,
        $documentClass,
        $searchQuery,
        array $searchableFields,
        $page = 1,
        $maxPerPage = 15,
        $sortField = null,
        $sortDirection = null
    ) {
        $this->dm               = $manager->getManager();
        $this->documentClass    = $documentClass;
        $this->searchQuery      = $searchQuery;
        $this->searchableFields = $searchableFields;
        $this->page             = $page;
        $this->maxPerPage       = $maxPerPage;
        $this->sortField        = $sortField;
        $this->sortDirection    = $sortDirection;
    }

    /**
     * Returns the query builder for searches.
     * Algorithm based on original ORM code.
     * 
     * @return \Doctrine\ODM\MongoDB\Query\Builder
     */
    public function create()
    {
        $queryBuilder = $this
            ->dm
            ->createQueryBuilder($this->documentClass);
        
        foreach ($this->searchableFields as $name => $metadata) {
            $isNumericField = in_array($metadata['dataType'],
                array('integer', 'number', 'smallint', 'bigint', 'decimal', 'float'));

            if (true === $isNumericField ||
                (isset($metadata['id']) && true === $metadata['id'])
            ) {
                $queryBuilder->addOr(
                    $queryBuilder
                        ->expr()
                        ->field($name)
                        ->equals((int)$this->searchQuery)
                );

            } else {
                $queryBuilder->addOr(
                    $queryBuilder
                        ->expr()
                        ->field($name)
                        ->equals(
                            new \MongoRegex(
                                '/.*' . $this->searchQuery . '.*/i'
                            )
                        )
                );

            }
        }

        if (null !== $this->sortField) {
            $queryBuilder->sort($this->sortField, $this->sortDirection);
        }

        return $queryBuilder;
    }
}