<?php

function wrap($x)
{
    return function ($y) use ($x) {
        return $x + $y;
    };
}
