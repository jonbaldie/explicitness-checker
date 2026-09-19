<?php

// Regression fixture for #5: an array index on the left of an assignment is
// read, not written. Only the array being indexed is written.

function indexBySuperglobal(): void
{
    $arr = [];
    $arr[$_GET['id']] = 1;
}

function writeGlobalAtGlobalIndex(): void
{
    global $map, $key;
    $map[$key] = 1;
}

function nestedIndexes(): void
{
    $_SESSION[$_GET['a']][$_POST['b']] = 1;
}

class IndexedCache
{
    /** @var array<string, int> */
    protected array $items = [];

    public function put(): void
    {
        $this->items[$_COOKIE['k']] = 1;
    }
}
