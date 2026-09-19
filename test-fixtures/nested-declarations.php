<?php

/*
 * `global` declarations are found however deeply they are nested in a body,
 * after other declarations and after nested function-likes. Methods are named
 * after the right class even when an earlier method declares an anonymous one.
 */

function declaresGlobalsAtSeveralDepths(): int
{
    $callback = function () {
    };
    global $first;
    if ($first) {
        if ($first > 1) {
            if ($first > 2) {
                global $second;
            }
        }
    }

    return $first + $second;
}

class HasAnonymousClass
{
    public function makesAnonymousClass(): object
    {
        return new class {
            public function run(): void
            {
            }
        };
    }

    public function readsGlobalAfterwards(): int
    {
        global $counter;

        return $counter;
    }
}

class DynamicProperties
{
    protected array $items = [];

    public function readsDynamicAndNestedProperties(string $name, object $other): void
    {
        echo $this->items[0]->label;
        echo $this->$name;
        echo static::$$name;
        echo $other::$shared;
    }
}
