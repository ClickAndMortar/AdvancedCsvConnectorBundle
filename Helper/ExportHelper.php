<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Helper;

use Doctrine\ORM\EntityManager;

/**
 * Export helper
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Helper
 */
class ExportHelper
{
    /**
     * Entity manager
     *
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * ExportHelper constructor.
     *
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->loadRepositories();
    }

    /**
     * Load repositories from entity manager if necessary
     *
     * @return void
     */
    protected function loadRepositories()
    {
    }
}
