<?php

function currentUser(): mixed
{
    return auth();
}

class Orders
{
    public mixed $orders;

    public function load(): mixed
    {
        return $this->orders = auth();
    }
}

function qualifiedExit(): void
{
    \exit();
}

function qualifiedDie(): void
{
    \die();
}
