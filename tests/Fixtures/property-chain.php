<?php

// #82: writing through a property chain fetches the chain's base first, so
// the base is read as well as written.

class Node
{
    public static ?Node $head = null;

    public ?Node $next = null;

    /** @var list<int> */
    public array $items = [];

    public int $count = 0;

    public function unlink(): void
    {
        $this->next->next = null;
    }

    public function append(): void
    {
        $this->next->items[] = 1;
    }

    public function resetHead(): void
    {
        self::$head->count = 0;
    }

    public function direct(): void
    {
        $this->count = 1;
    }

    public function readChain(): int
    {
        return $this->next->count;
    }
}

function write_through_global(): void
{
    global $config;
    $config->debug = true;
}
