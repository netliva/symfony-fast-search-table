<?php

namespace Netliva\SymfonyFastSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('netliva_symfony_fast_search');


        $rootNode
            ->children()

                ->scalarNode('cache_path')
                    ->cannotBeEmpty()->isRequired()
                ->end()
                ->scalarNode('default_input_class')
                ->end()
                ->scalarNode('default_limit_per_page')
                    ->cannotBeEmpty()->isRequired()
                ->end()

                ->arrayNode('entities')
                    ->useAttributeAsKey('id')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('limit_per_page')->defaultValue(null)->end()
                            ->scalarNode('default_sort_field')->cannotBeEmpty()->isRequired()->end()
                            ->scalarNode('default_sorting_direction')->defaultValue('asc')->end()
                            ->scalarNode('class')->cannotBeEmpty()->isRequired()->end()
                            ->arrayNode('where')
								->prototype('array')
									->children()
                                        ->scalarNode('field')->cannotBeEmpty()->isRequired()->end()
                                        ->scalarNode('expr')->cannotBeEmpty()->isRequired()->end()
                                        ->scalarNode('value')->defaultValue(null)->end()
                                        ->scalarNode('valueType')->defaultValue(null)->end()
									->end()
								->end()
							->end()
                            ->arrayNode('filters')
                                ->useAttributeAsKey('id')
								->prototype('array')
									->children()
										->scalarNode('title')->cannotBeEmpty()->end()
										->scalarNode('type')->cannotBeEmpty()->isRequired()->end()
										->scalarNode('exp')->defaultValue(null)->end()
										->scalarNode('input_class')->defaultValue("")->end()
                                        ->arrayNode('fields')->defaultValue([])->prototype('scalar')->end()->end()
                                        ->arrayNode('options')->defaultValue([])->prototype('scalar')->end()->end()
									->end()
								->end()
							->end()
                            ->arrayNode('fields')
                                ->useAttributeAsKey('id')
								->prototype('array')
									->children()
										->scalarNode('title')->end()
										->scalarNode('field')->defaultValue(null)->end()
									->end()
								->end()
							->end()
                            ->arrayNode('cache_clear')
                                ->useAttributeAsKey('id')
								->prototype('array')
									->children()
                                        ->arrayNode('reverse_fields')->defaultValue([])->prototype('scalar')->end()->end()
									->end()
								->end()
							->end()
                        ->end()
                    ->end()
                ->end()

            ->end()
        ;

        return $treeBuilder;
    }
}
