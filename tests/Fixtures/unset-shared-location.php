<?php

// #136: unsetting the shared location itself is still a write. A mixed unset
// reports only the arguments that are not bare local symbols.

function unset_element(array &$cart): void
{
    unset($cart['sku']);
}

function unset_property(object $product): void
{
    unset($product->price);
}

function unset_superglobal_element(): void
{
    unset($_GET['a']);
    unset($_SESSION['user']);
}

function unset_superglobal(): void
{
    unset($_GET);
    unset($_SESSION);
}

function unset_globals_entry(): void
{
    unset($GLOBALS['cart']);
}

function unset_mixed(array &$cart): void
{
    unset($cart, $cart['sku']);
}

function unset_mixed_global(): void
{
    global $g;
    unset($g, $_GET);
}

class Cached
{
    public function clear(): void
    {
        unset($this->cached);
    }
}

function unset_alias_element(): void
{
    $alias = &$GLOBALS['cart'];
    unset($alias['sku']);
}

function unset_globals_symbol(): void
{
    unset($GLOBALS);
}
