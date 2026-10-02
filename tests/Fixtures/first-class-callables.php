<?php

function get_time_callable(): \Closure
{
    return time(...);
}

function get_rand_callable(): \Closure
{
    return rand(...);
}

function get_unlink_callable(): \Closure
{
    return unlink(...);
}

function get_fopen_callable(): \Closure
{
    return fopen(...);
}

function get_exit_callable(): \Closure
{
    return exit(...);
}

function get_die_callable(): \Closure
{
    return die(...);
}
