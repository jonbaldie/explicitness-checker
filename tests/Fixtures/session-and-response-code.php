<?php

function get_id(): string
{
    return session_id();
}

function set_id(): string
{
    return session_id('abc');
}

function set_id_named(): string
{
    return session_id(id: 'abc');
}

function set_name(): string
{
    return session_name('APP');
}

function code_get(): int|false
{
    return http_response_code();
}

function code_set(): int|false
{
    return http_response_code(201);
}
