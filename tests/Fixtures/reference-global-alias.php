<?php

// Regression fixture for #45: writes through a reference alias to a globals
// array entry are writes to that entry, not ordinary local-variable writes.

function writeThroughAlias(): void
{
    $alias = &$GLOBALS['counter'];
    $alias = 5;
}

function readWriteThroughAlias(): void
{
    $alias = &$GLOBALS['total'];
    $alias += 1;
}

function incrementAndDecrementThroughAliases(): void
{
    $up = &$GLOBALS['up'];
    ++$up;
    $down = &$GLOBALS['down'];
    --$down;
}

function unsetThroughAlias(): void
{
    $alias = &$GLOBALS['removed'];
    unset($alias);
}

function rebindAlias(): void
{
    $alias = &$GLOBALS['first'];
    $alias = &$GLOBALS['second'];
    $alias = 1;
}

function dynamicGlobalKey(string $key): void
{
    $alias = &$GLOBALS[$key];
    $alias = 1;
}

function ordinaryReference(): void
{
    $local = 0;
    $alias = &$local;
    $alias = 1;
}

function parameterReference(string &$value): void
{
    $value = 1;
}

function nonGlobalsReference(): void
{
    $alias = &$_SESSION['key'];
    $alias = 1;
}
