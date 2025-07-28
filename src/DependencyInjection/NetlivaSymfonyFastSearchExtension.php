<?php

namespace Netliva\SymfonyFastSearchBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class NetlivaSymfonyFastSearchExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('netliva_fast_search.entities', $config['entities']);
        $container->setParameter('netliva_fast_search.cache_path', $config['cache_path']);
        $container->setParameter('netliva_fast_search.default_limit_per_page', $config['default_limit_per_page']);
        $container->setParameter('netliva_fast_search.default_input_class', $config['default_input_class']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
