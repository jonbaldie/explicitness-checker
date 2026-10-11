<?php

function sort_literal_string(array &$items): void
{
    ('sort')($items);
}

function sort_immediate_first_class(array &$items): void
{
    (sort(...))($items);
}

function sort_call_user_func(array &$items): void
{
    call_user_func('sort', $items);
}

function sort_stored_callable(): mixed
{
    return sort(...);
}

function shuffle_literal_string(array &$items): void
{
    ('shuffle')($items);
}
