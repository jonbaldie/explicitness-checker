<?php

/*
 * Static properties named through `use` imports and relative names in a
 * namespace. The CLI must resolve class names as PHPStan does, so both tools
 * report `App\Sub\Registry::$items` whether it is written `Registry::$items`
 * or `\App\Sub\Registry::$items`, and imported classes under their full names.
 */

namespace App\Sub;

use Other\Thing;
use Other\Config as Settings;

class Registry
{
    public static array $items = [];

    public static int $count = 0;
}

class Consumer
{
    public static int $calls = 0;

    public function reads(): array
    {
        return [Registry::$items, \App\Sub\Registry::$items, Thing::$shared, Settings::$values];
    }

    public function writes(): void
    {
        Registry::$count = 1;
        Nested\Store::$cache = [];
        self::$calls++;
        static::$calls = 0;
    }
}
