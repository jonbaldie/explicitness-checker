<?php

// Regression fixture for #92: assigning an entry of $GLOBALS by reference to
// an object property, static property, or array element reports a write to
// that target and read+write to the globals entry.

class RefBug
{
    public mixed $ref;
    public static mixed $staticRef;

    public function assignThis(): void
    {
        $this->ref = &$GLOBALS['counter'];
    }

    public static function assignStatic(): void
    {
        self::$staticRef = &$GLOBALS['counter'];
    }
}

function mutateParamRef(object $param): void
{
    $param->ref = &$GLOBALS['counter'];
}

function mutateParamArrayRef(array &$arr): void
{
    $arr['key'] = &$GLOBALS['counter'];
}
