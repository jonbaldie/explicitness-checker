<?php

class DynamicClassProbe
{
    public static int $value = 0;
    public string $className = self::class;

    public function writeThroughParam(string &$className): void
    {
        $className::$value = 1;
    }

    public function writeThroughGlobal(): void
    {
        global $className;
        $className::$value = 1;
    }

    public function writeThroughProperty(): void
    {
        $this->className::$value = 1;
    }

    public function writeThroughGlobals(): void
    {
        $GLOBALS['className']::$value = 1;
    }

    public function readThroughParam(string $className): int
    {
        return $className::$value;
    }
}
