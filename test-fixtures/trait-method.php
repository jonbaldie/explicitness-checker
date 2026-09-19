<?php

/*
 * Trait methods are checked once, under the trait's name, whether or not a
 * class uses the trait (#16). PHPStan itself analyses a trait method once per
 * using class, so naming it from PHPStan's scope would get this wrong.
 */

namespace App\Traits;

trait ReadsGlobals
{
    public function readsSession(): mixed
    {
        return $_SESSION['user'];
    }
}

trait UnusedTrait
{
    public function readsServer(): mixed
    {
        return $_SERVER['REQUEST_URI'];
    }
}

class FirstUser
{
    use ReadsGlobals;
}

class SecondUser
{
    use ReadsGlobals;
}
