<?php

function remove(): void
{
    header_remove();
}

function remove_named(): void
{
    header_remove(name: 'X-Probe');
}

function list_headers(): array
{
    return headers_list();
}

function sent(): bool
{
    return headers_sent();
}

function apache(): array
{
    return apache_response_headers();
}

function register(): bool
{
    return header_register_callback(function () {});
}

function save_path(): string|false
{
    return session_save_path('/tmp');
}

function save_path_read(): string|false
{
    return session_save_path();
}

function module(): string|false
{
    return session_module_name('files');
}

function limiter(): string|false
{
    return session_cache_limiter('nocache');
}

function expire(): int|false
{
    return session_cache_expire(10);
}

function abort(): bool
{
    return session_abort();
}

function reset(): bool
{
    return session_reset();
}

function status(): int
{
    return session_status();
}

function create_id(): string|false
{
    return session_create_id();
}
