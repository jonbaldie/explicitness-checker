<?php

function add_item(array &$cart, string $item): void {
    $cart[] = $item;
}

function set_price(object $product, int $price): void {
    $product->price = $price;
}
