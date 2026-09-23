<?php

function make_counter(): callable {
    $count = 0;

    return function () use (&$count): int {
        return ++$count;
    };
}
