<?php

namespace Netliva\SymfonyFastSearchBundle\Services;


use Doctrine\ORM\QueryBuilder;
use Netliva\SymfonyFastSearchBundle\Events\NetlivaFastSearchEvents;
use Netliva\SymfonyFastSearchBundle\Events\PrepareRecordEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FastSearchServices extends AbstractExtension
{
	private bool $isEnabled = true;

	public function __construct(
        private readonly Environment $environment,
        private readonly RouterInterface $router,
        private readonly ContainerInterface $container
    ){ }

	public function disable(): void
	{
		$this->isEnabled = false;
	}

	public function enable(): void
	{
		$this->isEnabled = true;
	}

	public function isEnabled(): bool
	{
		return $this->isEnabled;
	}

	public function getFunctions(): array
	{
		return array(
			new TwigFunction('get_fast_search_table', $this->getFastSearchTable(...), ['is_safe'=>['html']]),
		);
	}
	public function getFilters(): array
	{
		return array(
			new TwigFilter('count_of_th', $this->countOfTh(...)),
		);
	}

    public function countOfTh ($str)
    {
        return preg_match_all('/<th[\s>]/', $str, $mathes);
    }

	public function getFastSearchTable($key, $options = [])
	{
        $options = array_merge([
           'remove_cache_url'               => $this->router->generate('netliva_fast_search_remove_cache', ['key' => $key, 'force' => '__FRC__']),
           'search_url'                     => $this->router->generate('netliva_fast_search_list', ['key' => $key, 'page' => '__PAGE__']),
           'vue_variables'                  => [],
           'components'                     => [],
           'js_variables'                   => [],
           'js_methods'                     => [],
           'custom_filter_options'          => [],
           'filter_values'                  => [],
           'post_values'                    => [],
           'table_class'                    => 'table table-striped table-hover table-bordered',
           'show_count_with_total'          => true,
           'record_variable_name'           => 'entity',
           'table_thead_cells_vue_template' => '<th>ID</th>',
           'table_tbody_cells_vue_template' => '<td>[[entity.id]]</td>',
           'default_sorting_direction'      => null,
           'default_sort_field'             => null,
        ], $options);


        $entityInfos  = $this->container->getParameter('netliva_fast_search.entities');


        return $this->environment->render('@NetlivaSymfonyFastSearch/fast_table.html.twig', [
            'key'                 => $key,
            'options'             => $options,
            'entityInfos'         => $entityInfos[$key],
            'default_input_class' => $this->container->getParameter('netliva_fast_search.default_input_class'),
        ]);
	}

    public function addWhereToQuery (QueryBuilder $qb, $entityInfo)
    {
        $main_alias = $entityInfo['alias']?:'ent';
        if (!is_null($entityInfo['joins']) && is_array($entityInfo['joins']) && count($entityInfo['joins']))
        {
            foreach ($entityInfo['joins'] as $join)
            {
                if (!!$join['is_left']) $qb->leftJoin($join['table'], $join['alias']);
                else $qb->join($join['table'], $join['alias']);
            }
        }

        // Kayıt limitleme var ise - belli bir tarihten önceki kayıtları işleme alma
        if (count($entityInfo['where']))
        {
            foreach ($entityInfo['where'] as $key => $whereInfo)
            {
                $field = $whereInfo['field'];
                if (strpos($field, '.') === false) {
                    $field = $main_alias.'.'.$whereInfo['field'];
                }
                if (is_null($whereInfo['value']))
                {
                    $qb->andWhere($qb->expr()->{$whereInfo['expr']}($field));
                }
                else
                {
                    switch ($whereInfo['valueType'])
                    {
                        case 'date' : $value = (new \DateTime($whereInfo['value']))->setTime(0,0,0); break;
                        default : $value = $whereInfo['value']; break;
                    }
                    $qb->andWhere($qb->expr()->{$whereInfo['expr']}($field, ':whr'.$key));
                    $qb->setParameter('whr'.$key, $value);
                }
            }
        }

    }

    private $recordCountsForDecreaseFromTotalCount = 0;
    public function getRecordCountsForDecreaseFromTotalCount (): int
    {
        return $this->recordCountsForDecreaseFromTotalCount;
    }
    public function filterRecords ($records, $filter, $filterData)
    {
        $this->recordCountsForDecreaseFromTotalCount = 0;
        return array_filter($records, function ($record) use ($filter, $filterData)
        {
            foreach ($filter as $fKey => $filterValue)
            {
                $fromDate = null;
                $toDate = null;
                if($filterData[$fKey]['type'] == 'date_range')
                {
                    $fromDate = $filterValue['from']??null;
                    $toDate = $filterValue['to']??null;
                }
                if (
                    array_key_exists($fKey, $filterData)
                    && (
                        (is_string($filterValue) && mb_strlen($filterValue)>0)
                        || is_numeric($filterValue)
                        || is_bool($filterValue)
                        || ($filterData[$fKey]['type'] == 'date_range' && is_array($filterValue) && ($fromDate || $toDate))
                    )
                )
                {
                    $find = false;
                    foreach ($filterData[$fKey]['fields'] as $field)
                    {

                        // field..key --> dizi içindeki obje
                        if (preg_match('/^([^.]+)\.\.(.+)/', $field, $matches))
                        {
                            $recValue = $record[$matches[1]];
                            if (is_array($recValue))
                            {
                                $recValue = array_map(function($item) use ($matches){
                                    foreach (explode('.', $matches[2]) as $keys)
                                    {
                                        if (is_array($item) && array_key_exists($keys, $item))
                                            $item = $item[$keys];
                                        else
                                            $item = null;
                                    }
                                    return $item;
                                }, $recValue);
                            }
                        }
                        // field.key.key.key --> nested obje
                        elseif (preg_match('/^([^.]+)\.(.+)/', $field, $matches))
                        {
                            $recValue = $record[$matches[1]];
                            foreach (explode('.', $matches[2]) as $item)
                            {
                                if (is_array($recValue) && array_key_exists($item, $recValue))
                                    $recValue = $recValue[$item];
                                else
                                    $recValue = null;
                            }
                        }
                        else $recValue = $record[$field];

                        if ($filterData[$fKey]['type'] == 'text' || $filterData[$fKey]['type'] == 'hidden')
                            $filterValue = trim($filterValue);

                        if (
                            ($filterData[$fKey]['type'] == 'text' || $filterData[$fKey]['type'] == 'hidden')
                            && (!$filterData[$fKey]['exp'] || in_array($filterData[$fKey]['exp'], ['like']))
                            && is_array($recValue)
                        )
                        {
                            $recValue = implode(', ', $recValue);
                        }

                        if (
                            (
                                // Metin ve gizli arama
                                ($filterData[$fKey]['type'] == 'text' || $filterData[$fKey]['type'] == 'hidden')
                                &&
                                (
                                    (
                                        (!$filterData[$fKey]['exp'] || $filterData[$fKey]['exp'] == 'like') &&
                                        is_string($recValue) &&
                                        mb_stripos(
                                            mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $recValue)),
                                            mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $filterValue)),
                                            0, "utf-8") !== false
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'eq' && $filterValue == 'isNull' && is_null($recValue)
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'eq' && $filterValue == 'isNotNull' && !is_null($recValue)
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'eq' && $filterValue == 'isTrue' && !!$recValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'eq' && $filterValue == 'isFalse' && !$recValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'eq' && $recValue == $filterValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'neq' && $recValue != $filterValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'lt' && $recValue < $filterValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'lte' && $recValue <= $filterValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'gt' && $recValue > $filterValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'gte' && $recValue >= $filterValue
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'date_lt' && (bool)strtotime($recValue) && (bool)strtotime($filterValue) && new \DateTime($recValue) < new \DateTime($filterValue)
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'date_gt' && (bool)strtotime($recValue) && (bool)strtotime($filterValue) && new \DateTime($recValue) > new \DateTime($filterValue)
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'in' &&  in_array($filterValue, $recValue)
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'isNull' && (($filterValue && is_null($recValue)) || (!$filterValue && !is_null($recValue)))
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'isNotNull' && (($filterValue && !is_null($recValue)) || (!$filterValue && is_null($recValue)))
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'isTrue' && (($filterValue && !!$recValue) || (!$filterValue && !$recValue))
                                    ) || (
                                        $filterData[$fKey]['exp'] == 'isFalse' && (($filterValue && !$recValue) || (!$filterValue && !!$recValue))
                                    )
                                )

                            )
                            || // seçim arama
                            (
                                $filterData[$fKey]['type'] == 'select' && (
                                    (is_array($recValue) && in_array($filterValue, $recValue))
                                    || (is_null($recValue) && $filterValue === 'is_null')
                                    || $recValue == $filterValue)
                            )
                            || // tarih aralığı araması
                            ( $filterData[$fKey]['type'] == 'date_range' && $recValue &&
                                (
                                    ($fromDate && $toDate && new \DateTime($fromDate) <= new \DateTime($recValue) && new \DateTime($recValue) <= (new \DateTime($toDate))->modify('tomorrow midnight'))
                                    || ($fromDate && !$toDate && new \DateTime($fromDate) <= new \DateTime($recValue))
                                    || (!$fromDate && $toDate && new \DateTime($recValue) <= (new \DateTime($toDate))->modify('tomorrow midnight'))
                                )
                            )
                        )
                        {
                            $find = true;
                            break;
                        }
                    }

                    if (!$find)
                    {

                        if ($filterData[$fKey]['decrease_from_total_count'])
                            $this->recordCountsForDecreaseFromTotalCount++;
                        return false;
                    }
                }

            }
            return true;
        });
    }

    public function sort($array, $field, $direction = 'asc')
    {
        if (!is_array($array)) return $array;

        $c = new \Collator('tr_TR');
        usort($array, function ($a, $b) use ($field, $direction, $c)
        {
            if (preg_match('/^([^.]+)\.(.+)/', $field, $matches))
            {
                $aVal = $a[$matches[1]];
                $bVal = $b[$matches[1]];
                foreach (explode('.', $matches[2]) as $item)
                {
                    if (is_array($aVal) && array_key_exists($item, $aVal))
                        $aVal = $aVal[$item];
                    elseif ($item == 'length' && is_array($aVal))
                        $aVal = count($aVal);

                    if (is_array($bVal) && array_key_exists($item, $bVal))
                        $bVal = $bVal[$item];
                    elseif ($item == 'length' && is_array($bVal))
                        $bVal = count($bVal);
                }
            }
            else {
                $aVal = $a[$field];
                $bVal = $b[$field];
            }

            if (!is_string($aVal) && !is_numeric($aVal)) $aVal = '';
            if (!is_string($bVal) && !is_numeric($bVal)) $bVal = '';

            $compare = $c->compare($aVal, $bVal);

            if (!$compare)
                return 0;

            if ($direction == 'desc')
                return -$compare;

            return $compare;
        });

        return $array;
    }

    public function quickSort($array, $field, $direction = 'asc')
    {
        if(count($array) < 2)
        {
            return $array;
        }
        $loe       = $gt = [];
        $pivot_key = key($array);
        $pivot     = array_shift($array);
        $c = new \Collator('tr_TR');
        foreach($array as $val)
        {
            $compare = $c->compare($val[$field], $pivot[$field]);
            if(($direction == 'asc' and $compare <= 0) || ($direction == 'desc' and $compare > 0))
            {
                $loe[] = $val;
            }
            elseif (($direction == 'asc' and $compare > 0) || ($direction == 'desc' and $compare <= 0))
            {
                $gt[] = $val;
            }
        }

        return array_merge($this->quickSort($loe, $field, $direction), [$pivot_key => $pivot], $this->quickSort($gt, $field, $direction));
    }


    public function binarySearch(array $haystack, $needle, $field, $compare, $high, $low = 0, $containsDuplicates = false)
    {
        $key = false;
        // Whilst we have a range. If not, then that match was not found.
        while ($high >= $low) {
            // Find the middle of the range.
            $mid = (int)floor(($high + $low) / 2);
            // Compare the middle of the range with the needle. This should return <0 if it's in the first part of the range,
            // or >0 if it's in the second part of the range. It will return 0 if there is a match.
            $cmp = call_user_func($compare, $needle, $haystack[$mid][$field]);
            // Adjust the range based on the above logic, so the next loop iteration will use the narrowed range
            if ($cmp < 0)
                $high = $mid - 1;
            elseif ($cmp > 0)
                $low = $mid + 1;
            else
            {
                // We've found a match
                if ($containsDuplicates) {
                    // Find the first item, if there is a possibility our data set contains duplicates by comparing the
                    // previous item with the current item ($mid).
                    while ($mid > 0 && call_user_func($compare, $haystack[($mid - 1)][$field], $haystack[$mid][$field]) === 0) {
                        $mid--;
                    }
                }
                $key = $mid;
                break;
            }
        }

        return $key;
    }

    public function _getEntityValue ($entity, string $field)
    {
        if (is_object($entity) && method_exists($entity, 'get'.ucfirst($field)))
            return $entity->{'get'.ucfirst($field)}();

        if (is_object($entity) && method_exists($entity, 'is'.ucfirst($field)))
            return $entity->{'is'.ucfirst($field)}();

        if (is_object($entity) && method_exists($entity, $field))
            return $entity->{$field}();

        if (is_array($entity) && array_key_exists($field, $entity))
            return $entity[$field];

        return null;
    }

    public function getEntityValue ($entity, string $field)
    {
        $aField = explode('.', $field);
        $fKey = array_shift($aField);
        $value = $this->_getEntityValue($entity, $fKey);
        if ($value && (is_object($value) || is_array($value)) && count($aField))
            $value = $this->getEntityValue($value, implode('.', $aField));

        return $value;
    }

    public function getEntObj ($entity, $fields, $entityKey)
    {
        $temp = ['id'=>$entity->getId()];
        foreach ($fields as $fKey => $info)
        {
            $temp[$fKey] = $this->getEntityValue($entity, $info['field']??$fKey);
            if ($temp[$fKey] instanceof \DateTime)
                $temp[$fKey] = $temp[$fKey]->format('c');

            $eventDispatcher = $this->container->get('event_dispatcher');
            $event = new PrepareRecordEvent($entity, $fKey, $fields, $entityKey, $temp[$fKey]??null);
            $eventDispatcher->dispatch($event, NetlivaFastSearchEvents::PREPARE_RECORD);

            $temp[$fKey] = $event->getValue();
        }

        return $temp;
    }

}
