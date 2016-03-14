<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Factory;

use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Class MongoQueryBuilder
 * @package JavierEguiluz\Bundle\EasyAdminBundle\QueryBuilder
 * 
 * This class creates the ODM Query Builder for listing purposes.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class MongoListQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var DocumentManager
     */
    protected $dm;

    private $documentClass;

    private $sortDirection;

    private $sortField;

    public function __construct(
        ManagerRegistry $manager,
        $documentClass,
        $sortDirection,
        $sortField = null
    ) {
        $this->dm            = $manager->getManager();
        $this->documentClass = $documentClass;
        $this->sortDirection = $sortDirection;
        $this->sortField     = $sortField;
    }

    /**
     * @return mixed
     */
    public function create()
    {
        $queryBuilder = $this->dm->createQueryBuilder($this->documentClass);

        if (null !== $this->sortField) {
            $queryBuilder->sort($this->sortField, $this->sortDirection);
        }

        return $queryBuilder;
    }

}