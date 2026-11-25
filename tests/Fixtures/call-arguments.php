<?php

// Regression fixture for #207: arguments of a call whose result is a write
// target are evaluated expressions and are read, never written. Only the
// returned value's property or element is written. Genuine by-reference
// built-in argument writes and genuine argument writes stay reported.

class CallArgumentsProbe
{
    public string $value = 'v';

    public function m($x): stdClass
    {
        return new stdClass();
    }

    public static function s($x): stdClass
    {
        return new stdClass();
    }
}

function make_call_arguments($x): stdClass
{
    return new stdClass();
}

function call_argument_function_write(&$r): void
{
    make_call_arguments($r)->p = 1;
}

function call_argument_global_write(): void
{
    global $gg;
    make_call_arguments($gg)->p = 1;
}

function call_argument_function_read(&$r): int
{
    return make_call_arguments($r)->p;
}

function call_argument_method_write(&$r): void
{
    (new CallArgumentsProbe())->m($r)->p = 1;
}

function call_argument_object_method_write(&$r, $d): void
{
    $d->m($r)->p = 1;
}

function call_argument_static_write(&$r): void
{
    CallArgumentsProbe::s($r)->p = 1;
}

function call_argument_array_write(&$r): void
{
    make_call_arguments($r)[0] = 1;
}

function call_argument_increment_write(&$r): void
{
    make_call_arguments($r)->p++;
}

function call_argument_compound_write(&$r): void
{
    make_call_arguments($r)->p += 1;
}

function call_argument_global_method_write(): void
{
    global $gg;
    CallArgumentsProbe::s($gg)->p = 1;
}

function call_argument_builtin_write(&$r): void
{
    sort($r);
}

function call_argument_property_write(&$r): void
{
    $r->p = 1;
}

function call_argument_element_write(&$r): void
{
    $r[0] = 1;
}

function call_argument_new_write(&$r): void
{
    (new CallArgumentsProbe($r))->value = 'x';
}

function call_argument_new_global_write(): void
{
    global $gg;
    (new CallArgumentsProbe($gg))->value = 'x';
}
