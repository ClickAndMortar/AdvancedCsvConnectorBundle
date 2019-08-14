<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

/**
 * Click And Mortar Advanced CSV Connector
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\DependencyInjection
 */
class ClickAndMortarAdvancedCsvConnectorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('jobs.yml');
        $loader->load('steps.yml');
        $loader->load('readers.yml');
        $loader->load('processors.yml');
        $loader->load('writers.yml');
        $loader->load('job_constraints.yml');
        $loader->load('job_defaults.yml');
        $loader->load('providers.yml');
        $loader->load('archiving.yml');
        $loader->load('helpers.yml');
        $loader->load('subscribers.yml');
        $loader->load('normalizers.yml');
        $loader->load('entities.yml');
        $loader->load('repositories.yml');
        $loader->load('validators.yml');
    }
}
