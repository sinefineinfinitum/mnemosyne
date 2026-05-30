<?php declare(strict_types=1);

$globalVar = 42;

function helper(string $name): string
{
    global $globalVar;
    return "Hello, $name!";
}
