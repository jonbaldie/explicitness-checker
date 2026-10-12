<?php

function clone_append(object $cart): void
{
    $cart = clone $cart;
    $cart->items[] = 1;
}

function new_append(object $cart): void
{
    $cart = new \stdClass();
    $cart->items[] = 1;
}

function separate_local(object $cart): void
{
    $copy = clone $cart;
    $copy->items[] = 1;
}

function write_before(object $cart): void
{
    $cart->items[] = 1;
    $cart = clone $cart;
}

function write_on_rhs(object $cart): void
{
    $cart = clone ($cart->price = 1);
}

function same_arm(object $cart, bool $copy): void
{
    if ($copy) {
        $cart = clone $cart;
        $cart->items[] = 1;
    }
}

function after_branch(object $cart, bool $copy): void
{
    if ($copy) {
        $cart = clone $cart;
    }
    $cart->items[] = 1;
}

function after_unconditional(object $cart, bool $copy): void
{
    if ($copy) {
        $cart = $cart;
    }
    $cart = clone $cart;
    $cart->items[] = 1;
}

function by_reference(object &$cart): void
{
    $cart = clone $cart;
    $cart->items[] = 1;
}

function from_other(object $cart, object $other): void
{
    $cart = $other;
    $cart->items[] = 1;
}

function from_element(object $cart, array $carts): void
{
    $cart = $carts[0];
    $cart->items[] = 1;
}

function from_call(object $cart): void
{
    $cart = id($cart);
    $cart->items[] = 1;
}

function shallow_parent(object $cart): void
{
    $cart = clone $cart;
    $cart->parent->items = [];
}

function shallow_element(object $cart): void
{
    $cart = clone $cart;
    $cart->items[0]->x = 1;
}

function own_unset_and_sort(object $cart): void
{
    $cart = clone $cart;
    unset($cart->items);
    sort($cart->items);
}

function global_wins(object $cart): void
{
    $cart = clone $cart;
    global $cart;
    $cart->items[] = 1;
}

function alias_wins(object $cart): void
{
    $cart = clone $cart;
    $cart = &$GLOBALS['obj'];
    $cart->n = 7;
}

function after_loop(object $cart, array $flags): void
{
    foreach ($flags as $flag) {
        $cart = clone $cart;
    }
    $cart->items[] = 1;
}

function logical_and(object $cart, bool $flag): void
{
    $flag and $cart = clone $cart;
    $cart->items[] = 1;
}

function logical_and_left(object $cart, bool $flag): void
{
    ($cart = clone $cart) and $flag;
    $cart->items[] = 1;
}

function coalesce_assign(object $cart): void
{
    $cart = clone $cart;
    $cart ??= new \stdClass();
    $cart->items[] = 1;
}

function for_init(object $cart): void
{
    for ($cart = clone $cart; false;) {
    }
    $cart->items[] = 1;
}
