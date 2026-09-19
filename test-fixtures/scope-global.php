<?php

/*
 * Function-likes in a file without a namespace (#12). scope-namespaced.php has
 * the same code inside a namespace; the CLI must check both files the same way.
 */

if (!function_exists('scope_conditional')) {
    function scope_conditional(): mixed
    {
        global $conditional;

        return $conditional;
    }
}

$scopeAnonymous = new class {
    public function anonymousMethod(): mixed
    {
        return $_GET['anonymous'];
    }
};

class ScopeNamed
{
    public function namedMethod(): mixed
    {
        global $named;

        return $named;
    }

    public function makesClosures(): array
    {
        $closure = function (): mixed {
            return $_POST['closure'];
        };
        $arrow = fn(): mixed => $_COOKIE['arrow'];

        return [$closure, $arrow];
    }
}

$scopeTopLevelClosure = function (): mixed {
    return $_GET['top'];
};
