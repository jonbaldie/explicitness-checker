<?php

// Regression fixture for #6: `global $x;` is a declaration, not a read.

function writeOnly(): void
{
    global $counter;
    $counter = 10;
}

function declaredButUnused(): void
{
    global $unused;
}

function readAndWrite(): void
{
    global $total;
    $total = $total + 1;
}
