<?php

function renderHeader(string $title, ?array $nav = []): void
{
    global $siteName;
    echo "<h1>$title</h1>";
}

function renderFooter(): string
{
    return '<footer>Footer</footer>';
}

$siteName = 'My Site';
$currentUser = null;
