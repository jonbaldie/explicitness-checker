<?php

// Regression fixture for #105: the key of a keyed destructuring item is read
// to look up the element; only the item's value is written.

class KeyedDestructureProbe
{
    public static string $staticKey = 'sk';

    public string $key = 'k';

    public string $value = 'v';

    public function readKeyThroughProp(array $data): void
    {
        [$this->key => $val] = $data;
    }

    public function readKeyThroughParam(object $param, array $data): void
    {
        list($param->key => $val) = $data;
    }

    public function writeValueToProp(array $data): void
    {
        ['k' => $this->value] = $data;
    }

    public function readKeyInNestedList(array $data): void
    {
        [[$this->key => $val]] = $data;
    }
}

function read_global_key(array $data): void
{
    global $key;
    [$key => $val] = $data;
}

function read_globals_array_key(array $data): void
{
    [$GLOBALS['key'] => $val] = $data;
}

function read_static_key(array $data): void
{
    [KeyedDestructureProbe::$staticKey => $val] = $data;
}

function read_foreach_key(array $data): void
{
    global $key;
    foreach ($data as [$key => $val]) {
    }
}

function write_global_value(array $data): void
{
    global $out;
    ['k' => $out] = $data;
}
