<?php

final class OriginCart
{
    public array $items = [];
    public ?OriginCart $parent = null;
}

function origin_direct(array $carts): void { $carts[0]->items = []; }
function origin_local(array $carts): void { $first = $carts[0]; $first->items = []; }
function origin_foreach(array $carts): void { foreach ($carts as $cart) { $cart->items = []; } }
function origin_keyed_foreach(array $carts): void { foreach ($carts as $key => $cart) { $cart->items = []; } }
function origin_destructure(array $carts): void { [$first] = $carts; $first->items = []; }
function origin_list(array $carts): void { list($first) = $carts; $first->items = []; }
function origin_keyed(array $carts): void { ['cart' => $first] = $carts; $first->items = []; }
function origin_nested(array $carts): void { [[$first]] = $carts; $first->items = []; }
function origin_alias(OriginCart $cart): void { $same = $cart; $same->items = []; }
function origin_chain(OriginCart $cart): void { $same = $cart; $other = $same; $other->items = []; }
function origin_clone(OriginCart $cart): OriginCart { $copy = clone $cart; $copy->parent->items = []; return $copy; }
function origin_clone_array(OriginCart $cart): void { $copy = clone $cart; $copy->items[0]->items = []; }
function origin_join(OriginCart $cart, OriginCart $other, bool $flag): void { if ($flag) { $local = $cart; } else { $local = $other; } $local->items = []; $local->items = []; }
function origin_fresh(OriginCart $cart): void { $local = $cart; $local = new OriginCart(); $local->items = []; }
function origin_clone_own(OriginCart $cart): void { $copy = clone $cart; $copy->items = []; }
function origin_array_copy(array $carts): void { $copy = $carts; $copy[] = new OriginCart(); }
function origin_unused(OriginCart $cart): OriginCart { $local = $cart; return $local; }
