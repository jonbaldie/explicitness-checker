<?php

/*
 * PHP 8.4 property hooks (#27). A hook is a function-like with a body, so it
 * is checked on its own, under a name that says which property and which hook
 * it is: `App\Sub\Temperature::$celsius::get`. `{closure}` stays reserved for
 * closures and arrow functions, like the one this file's hooks contain.
 */

namespace App\Sub;

class Temperature
{
    public int $celsius = 0 {
        get => $this->celsius;
        set {
            $this->celsius = $value;
        }
    }

    public string $source {
        get => $_GET['source'];
    }

    public function __construct(public string $label = '' {
        get => $this->label;
    }) {
    }
}

$anonymousHooks = new class {
    public int $reading {
        get {
            $fallback = fn(): mixed => $_POST['reading'];

            return $_SERVER['reading'] ?? $fallback();
        }
    }
};
