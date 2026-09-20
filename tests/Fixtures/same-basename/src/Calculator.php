<?php

namespace App;

function add($a, $b)
{
    global $n;
    --$n;

    return $a + $b + $n;
}
