<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Factory;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;

/**
 * Class ORMSearchQueryBuilderFactory
 * @package JavierEguiluz\Bundle\EasyAdminBundle\Factory
 *
 * Creates the ORM Query Builder for search.
 * 
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class ORMSearchQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    private $em;

    private $elementClass;

    private $searchQuery;

    private $searchableFields;

    private $page;

    private $maxPerPage;

    private $sortField;

    private $sortDirection;

    /**
     * ORMSearchQueryBuilderFactory constructor.
     * @param Registry $manager
     * @param          $elementClass
     * @param          $searchQuery
     * @param array    $searchableFields
     * @param int      $page
     * @param int      $maxPerPage
     * @param null     $sortField
     * @param null     $sortDirection
     */
    public function __construct(
        Registry $manager,
        $elementClass,
        $searchQuery,
        array $searchableFields,
        $page = 1,
        $maxPerPage = 15,
        $sortField = null,
        $sortDirection = null
    ) {
        $this->em               = $manager->getManager();
        $this->elementClass     = $elementClass;
        $this->searchQuery      = $searchQuery;
        $this->searchableFields = $searchableFields;
        $this->page             = $page;
        $this->maxPerPage       = $maxPerPage;
        $this->sortField        = $sortField;
        $this->sortDirection    = $sortDirection;
    }

    /**
     * @return mixed
     */
    public function create()
    {
        $databaseIsPostgreSql = $this->isPostgreSqlUsedByEntity();
        $queryBuilder         = $this
            ->em
            ->createQueryBuilder()
            ->select('entity')
            ->from($this->elementClass, 'entity');

        $queryConditions = $queryBuilder->expr()->orX();
        $queryParameters = array();
        foreach ($this->searchableFields as $name => $metadata) {
            $isNumericField = in_array($metadata['dataType'],
                array('integer', 'number', 'smallint', 'bigint', 'decimal', 'float'));

            $isTextField = in_array($metadata['dataType'], array('string', 'text', 'guid'));

            if (is_numeric($this->searchQuery) && $isNumericField) {
                $queryConditions->add(sprintf('entity.%s = :exact_query', $name));
                $queryParameters['exact_query'] = 0 + $this->searchQuery; // adding '0' turns the string into a numeric value
            } elseif ($isTextField) {
                $queryConditions->add(sprintf('entity.%s LIKE :fuzzy_query', $name));
                $queryParameters['fuzzy_query'] = '%' . $this->searchQuery . '%';
            } else {
                // PostgreSQL doesn't allow to compare string values with non-string columns (e.g. 'id')
                if ($databaseIsPostgreSql) {
                    continue;
                }

                $queryConditions->add(sprintf('entity.%s IN (:words)', $name));
                $queryParameters['words'] = explode(' ', $this->searchQuery);
            }
        }

        $queryBuilder
            ->add('where', $queryConditions)
            ->setParameters($queryParameters);

        if (null !== $this->sortField) {
            $queryBuilder->orderBy('entity.' . $this->sortField, $this->sortDirection ?: 'DESC');
        }

        return $queryBuilder;
    }

    /**
     * Returns true if the data of the given entity are stored in a database
     * of Type PostgreSQL.
     *
     * @return bool
     */
    private function isPostgreSqlUsedByEntity()
    {
        return $this
            ->em
            ->getConnection()
            ->getDatabasePlatform() instanceof PostgreSqlPlatform;
    }
}