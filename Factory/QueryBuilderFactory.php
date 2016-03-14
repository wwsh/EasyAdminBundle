<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Factory;

use Doctrine\Bundle\DoctrineBundle\Registry;
use ReflectionClass;

/**
 * Class QueryBuilderFactory
 * @package JavierEguiluz\Bundle\EasyAdminBundle\QueryBuilder
 *
 * General Query Builder. 
 * Supports two types of Query Builders: Search and List.
 * Each type has its own database driver version.
 * 
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class QueryBuilderFactory
{
    const BASEMETHODNAME = 'create';

    const FACTORYNAMESPACE = 'JavierEguiluz\\Bundle\\EasyAdminBundle\\Factory\\';

    /**
     * Creating and returning the Query Bilder, used by the list engine.
     *
     * @param Registry        $manager
     * @param                 $elementClass
     * @param                 $sortDirection
     * @param                 $sortField
     * @return
     */
    static public function createListQueryBuilder(
        $manager,
        $elementClass,
        $sortDirection,
        $sortField
    ) {
        return self::callBuilderFactory(
            $elementClass,
            'ListQueryBuilderFactory',
            [
                $manager,
                $elementClass,
                $sortDirection,
                $sortField
            ]
        );
    }

    /**
     * Creates and returns the Query Builder, used by the search engine.
     *
     * @param $manager
     * @param $elementClass
     * @param $searchQuery
     * @param $searchableFields
     * @param $sortField
     * @param $sortDirection
     */
    public static function createSearchQueryBuilder(
        $manager,
        $elementClass,
        $searchQuery,
        $searchableFields,
        $sortField,
        $sortDirection
    ) {

        return self::callBuilderFactory(
            $elementClass,
            'SearchQueryBuilderFactory',
            [
                $manager,
                $elementClass,
                $searchQuery,
                $searchableFields,
                $sortField,
                $sortDirection
            ]
        );
    }

    /**
     * @param $elementClass
     * @return string
     */
    private static function probeElementClass($elementClass)
    {
        $isORM = strpos($elementClass, 'Entity\\') !== false;

        switch ($isORM) {
            case false:
                $queryBuilderClassName = 'Mongo';
                break;
            default:
                $queryBuilderClassName = 'ORM';
                break;
        }

        return $queryBuilderClassName;
    }

    /**
     * @param $elementClass
     * @return mixed
     */
    private static function getElementClassShortName($elementClass)
    {
        $parts = preg_split('/[\:\\\\]/', $elementClass);
        $name  = end($parts);

        return $name;
    }

    /**
     * Booting the builder.
     *
     * @param $elementClass
     * @param $queryBuilderClassNamePostfix
     * @param $constructorParams
     * @return array
     */
    private static function callBuilderFactory(
        $elementClass,
        $queryBuilderClassNamePostfix,
        $constructorParams
    ) {
        $queryBuilderClassName = self::probeElementClass($elementClass);

        $name = self::getElementClassShortName($elementClass);

        // You still can put your customized methods (per entity or document) in the
        // specialized factories, as originally intended.
        $queryBuilderMethodName = self::BASEMETHODNAME . ucfirst($name);

        $namespace = self::FACTORYNAMESPACE;

        $queryBuilderClassName .= $queryBuilderClassNamePostfix;

        $queryBuilderClass = new \ReflectionClass(
            $namespace . $queryBuilderClassName
        );

        if (!$queryBuilderClass->isInstantiable()) {
            throw new \RuntimeException(
                sprintf(
                    'Could not find builder %s class',
                    $namespace . $queryBuilderClassName
                )
            );
        }
        
        $queryBuilder = $queryBuilderClass->newInstanceArgs(
            $constructorParams
        );

        if ($queryBuilderClass->hasMethod($queryBuilderMethodName)) {
            return $queryBuilder->$queryBuilderMethodName();
        }

        $queryBuilderBaseMethodName = self::BASEMETHODNAME;

        return $queryBuilder
            ->$queryBuilderBaseMethodName();
    }
}