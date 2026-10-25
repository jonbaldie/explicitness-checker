<?php

function wrap($x)
{
    return fn($y) => $x + $y;
}
