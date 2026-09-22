<?php

function exit_with_status(): void
{
    exit(1);
}

function exit_without_status(): void
{
    exit;
}

function exit_with_message(): void
{
    exit('bye');
}

function exit_with_dynamic_value($value): void
{
    exit($value);
}

function die_with_status(): void
{
    die(1);
}

function die_without_status(): void
{
    die;
}

function die_with_message(): void
{
    die('bye');
}

function die_with_dynamic_value($value): void
{
    die($value);
}

function qualified_exit_with_status(): void
{
    \exit(1);
}

function qualified_exit_with_message(): void
{
    \exit('bye');
}

function qualified_exit_with_dynamic_value($value): void
{
    \exit($value);
}

function qualified_die_with_status(): void
{
    \die(1);
}

function qualified_die_with_message(): void
{
    \die('bye');
}

function qualified_die_with_dynamic_value($value): void
{
    \die($value);
}
