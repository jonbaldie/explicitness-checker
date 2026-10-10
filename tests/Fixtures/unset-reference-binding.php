<?php

// #136: unset() of a local reference binding destroys the symbol. It does not
// write the shared location.

function unset_param(array &$cart): void
{
    unset($cart);
}

function unset_global(): void
{
    global $g;
    unset($g);
}

function unset_static(): void
{
    static $n = 1;
    unset($n);
}

function unset_alias(): void
{
    $alias = &$GLOBALS['cart'];
    unset($alias);
}

function unset_capture(): void
{
    $outer = 1;
    $fn = function () use (&$outer) {
        unset($outer);
    };
}
