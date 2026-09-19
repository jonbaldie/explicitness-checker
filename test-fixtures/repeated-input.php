<?php

/*
 * The same implicit input and output repeated in one function (#16): each is
 * reported once, on the line where it first occurs.
 */

function repeats_inputs(): string
{
    $first = $_GET['a'];
    $second = $_GET['b'];
    global $counter;
    $counter = 1;
    $counter = 2;

    return $first . $second . $_GET['c'];
}
