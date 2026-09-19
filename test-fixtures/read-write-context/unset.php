<?php

// Regression fixture for #10: unset() writes to what it unsets.

function logout(): void
{
    unset($_SESSION['user']);
}

function forgetGlobal(): void
{
    global $cache;
    unset($cache);
}

function forgetGlobalsEntry(): void
{
    unset($GLOBALS['registry']);
}

class Memo
{
    /** @var array<string, int>|null */
    protected ?array $cached = null;

    public function clear(): void
    {
        unset($this->cached);
    }
}
