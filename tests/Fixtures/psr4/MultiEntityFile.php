<?php declare(strict_types=1);

namespace App\Utils;

interface SortableInterface
{
    public function sort(array $items): array;
}

class ArraySorter implements SortableInterface
{
    public function sort(array $items): array
    {
        sort($items);
        return $items;
    }
}
