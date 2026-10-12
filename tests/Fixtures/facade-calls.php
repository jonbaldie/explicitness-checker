<?php

use Illuminate\Support\Facades\Cache as Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

function cache_get($key, $value, $args, $method, $class) {
    Store::get($key);
}

function cache_put($key, $value, $args, $method, $class) {
    Store::put(key: $key, value: $value);
}

function db_table($key, $value, $args, $method, $class) {
    DB::table('orders');
}

function log_info($key, $value, $args, $method, $class) {
    Log::info($value);
}

function auth_user($key, $value, $args, $method, $class) {
    Auth::user();
}

function realtime($key, $value, $args, $method, $class) {
    \Facades\App\Services\Pricing::quote($value);
}

function unpacked($key, $value, $args, $method, $class) {
    Store::get(...$args);
}

function mixed_case($key, $value, $args, $method, $class) {
    \iLlUmInAtE\Support\Facades\Cache::get($key);
}

function dynamic_method($key, $value, $args, $method, $class) {
    Store::{$method}($key);
}

function str_slug($key, $value, $args, $method, $class) {
    \Illuminate\Support\Str::slug($value);
}

function money($key, $value, $args, $method, $class) {
    \App\Money::of(5);
}

function global_alias($key, $value, $args, $method, $class) {
    \Cache::get($key);
}

function prefix_lookalike($key, $value, $args, $method, $class) {
    \Illuminate\Support\FacadesExtra\Cache::get($key);
}

function app_facade($key, $value, $args, $method, $class) {
    \App\Facades\Cache::get($key);
}

function dynamic_class($key, $value, $args, $method, $class) {
    $class::get($key);
}

function callable_creation($key, $value, $args, $method, $class) {
    Store::get(...);
}

