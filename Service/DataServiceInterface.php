<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Service;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;

/**
 * Interface DataServiceInterface.
 * 
 * This interface is available for top-level modules like AdminController.
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
interface DataServiceInterface
{
    /**
     * @param $elementClass
     * @return ClassMetadata
     */
    public function getMetadata($elementClass);

    public function findOne($elementClass, $elementId);

    public function findAll($elementClass, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null);

    public function findBy(
        $elementClass,
        $searchQuery,
        array $searchableFields,
        $page = 1,
        $maxPerPage = 15,
        $sortField = null,
        $sortDirection = null
    );

    public function remove($element);

    public function persistAndFlush($element);

    public function flush();
}