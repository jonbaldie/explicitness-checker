<?php

/*
 * Regression fixture for #8: a `global` declared inside a nested function-like
 * belongs to that function-like only. None of the outer functions below touch
 * global state; each nested function-like reads one global variable.
 */

function outer_with_closure(): int
{
    $x = 1;
    $fn = function () {
        global $x;

        return $x;
    };

    return $x;
}

function outer_with_nested_function(): int
{
    $y = 1;
    function nested_reads_global()
    {
        global $y;

        return $y;
    }

    return $y;
}

function outer_with_anonymous_class(): int
{
    $z = 1;
    $object = new class {
        public function readsGlobal()
        {
            global $z;

            return $z;
        }
    };

    return $z;
}
