<?php

namespace Netliva\SymfonyFastSearchBundle\Services;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FastSearchServices extends AbstractExtension
{
	protected $em;
	protected $container;
	public function __construct($em, ContainerInterface $container){
		$this->em = $em;
		$this->container = $container;
	}

	public function getFunctions()
	{
		return array(
			new TwigFunction('get_fast_search_table', [$this, 'getFastSearchTable'], ['is_safe'=>['html']]),
		);
	}
	public function getFilters()
	{
		return array(
			new TwigFilter('count_of_th', [$this, 'countOfTh']),
		);
	}

    public function countOfTh ($str)
    {
        return preg_match_all('/<th[\s>]/', $str, $mathes);
    }

	public function getFastSearchTable($key, $options = [])
	{
        $options = array_merge([
           'vue_variables'                  => [],
           'custom_filter_options'          => [],
           'table_class'                    => 'table table-striped table-hover table-bordered',
           'record_variable_name'           => 'entity',
           'table_thead_cells_vue_template' => '<th>ID</th>',
           'table_tbody_cells_vue_template' => '<td>[[entity.id]]</td>',
        ], $options);


        $entityInfos  = $this->container->getParameter('netliva_fast_search.entities');


        return $this->container->get('templating')->render('NetlivaSymfonyFastSearchBundle::fast_table.html.twig', [
            'key'                 => $key,
            'options'             => $options,
            'entityInfos'         => $entityInfos[$key],
            'default_input_class' => $this->container->getParameter('netliva_fast_search.default_input_class'),
        ]);
	}

    public function filterRecords ($records, $filter, $filterData)
    {
        return array_filter($records, function ($record) use ($filter, $filterData)
        {
            foreach ($filter as $fKey => $fValue)
            {
                if (mb_strlen($fValue)>0)
                {
                    $find = false;
                    foreach ($filterData[$fKey]['fields'] as $field)
                    {
                        if (
                            (
                                $filterData[$fKey]['type'] == 'text'
                                && mb_stripos(
                                    mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $record[$field])),
                                    mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $fValue)),
                                    0, "utf-8") !== false
                            ) ||
                            ($filterData[$fKey]['type'] == 'select' && $record[$field] == $fValue)
                        )
                        {
                            $find = true;
                            break;
                        }
                    }

                    if (!$find) return false;
                }

            }
            return true;
        });
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
            if(($c->compare($val[$field], $pivot[$field]) <= 0 and $direction == 'asc') || ($c->compare($val[$field], $pivot[$field]) > 0 and $direction == 'desc'))
            {
                $loe[] = $val;
            }
            elseif (($c->compare($val[$field], $pivot[$field]) > 0 and $direction == 'asc') || ($c->compare($val[$field], $pivot[$field]) <= 0 and $direction == 'desc'))
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


    public function getEntObj ($entity, $fields)
    {
        $temp = ['id'=>$entity->getId()];
        foreach ($fields as $fKey => $info)
            if (method_exists($entity, 'get'.ucfirst($fKey)))
                $temp[$fKey] = $entity->{'get'.ucfirst($fKey)}();

        return $temp;
    }

}
