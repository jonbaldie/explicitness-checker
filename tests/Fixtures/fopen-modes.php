<?php

function fopen_read(): void
{
    fopen('file.txt', 'rb');
}

function fopen_write(): void
{
    fopen('file.txt', 'wb');
}

function fopen_append(): void
{
    fopen('file.txt', 'ab');
}

function fopen_create(): void
{
    fopen('file.txt', 'cb');
}

function fopen_exclusive(): void
{
    fopen('file.txt', 'xb');
}

function fopen_read_write(): void
{
    fopen('file.txt', 'r+b');
}

function fopen_dynamic(string $mode): void
{
    fopen('file.txt', $mode);
}
