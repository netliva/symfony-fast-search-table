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
    public function getConfigTreeBuilder(): \Symfony\Component\Config\Definition\Builder\TreeBuilder
    {
        $treeBuilder = new TreeBuilder('netliva_symfony_fast_search');
        $rootNode = method_exists(TreeBuilder::class, 'getRootNode')
            ? $treeBuilder->getRootNode()
            : $treeBuilder->root('netliva_symfony_fast_search');


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
                            ->scalarNode('alias')->defaultValue('ent')->end()
                            ->arrayNode('joins')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('table')->cannotBeEmpty()->isRequired()->end()
                                        ->scalarNode('alias')->cannotBeEmpty()->isRequired()->end()
                                        ->booleanNode('is_left')->defaultValue(false)->end()
                                    ->end()
                                ->end()
                            ->end()
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
										->booleanNode('decrease_from_total_count')->defaultFalse()->end()
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
                                        ->booleanNode('clear_all')->defaultValue(false)->end()
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
