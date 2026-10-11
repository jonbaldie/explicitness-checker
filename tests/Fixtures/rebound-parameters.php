<?php

// #148: a known GLOBALS alias supersedes the original parameter binding.
function reboundScalar(int &$value): void
{
    $value = &$GLOBALS['counter'];
    $value = 7;
}

function reboundObject(object $value): void
{
    $value = &$GLOBALS['obj'];
    $value->n = 7;
}
