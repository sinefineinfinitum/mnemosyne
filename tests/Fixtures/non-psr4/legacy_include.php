<?php

function calculateTotal(array $items, float $taxRate = 0.0): float
{
    $subtotal = array_sum($items);
    return $subtotal + ($subtotal * $taxRate);
}

$GLOBALS['app_name'] = 'LegacyApp';
