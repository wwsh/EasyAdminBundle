<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Factory;

use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Class ORMListQueryBuilderFactory
 * @package JavierEguiluz\Bundle\EasyAdminBundle\Factory
 *
 * This class creates the ORM's Query Builder to list records.
 * 
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class ORMListQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    private $em;
    
    private $entityClass;

    private $sortDirection;

    private $sortField;

    public function __construct(
        ManagerRegistry $manager,
        $entityClass,
        $sortDirection,
        $sortField = null
    ) {
        $this->em            = $manager->getManager();
        $this->entityClass   = $entityClass;
        $this->sortDirection = $sortDirection;
        $this->sortField     = $sortField;
    }

    /**
     * @return mixed
     */
    public function create()
    {
        $queryBuilder = $this->em
            ->createQueryBuilder()
            ->select('entity')
            ->from($this->entityClass, 'entity');

        if (null !== $this->sortField) {
            $queryBuilder->orderBy('entity.' . $this->sortField, $this->sortDirection);
        }

        return $queryBuilder;
    }

}