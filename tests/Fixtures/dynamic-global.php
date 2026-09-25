<?php

function writes_to_dynamic_global(string $name): void
{
    global $$name;
    $$name = 42;
}

function reads_from_dynamic_global(string $name): mixed
{
    global $$name;
    return $$name;
}

function writes_to_dynamic_global_with_expression(string $suffix): void
{
    global ${'x' . $suffix};
    ${'x' . $suffix} = 1;
}

function reads_name_expression_from_global(): mixed
{
    global $name;
    global $$name;
    return $$name;
}

function dynamic_global_declaration_alone(string $name): void
{
    global $$name;
}

function dynamic_global_does_not_classify_literal_local(string $name): void
{
    global $$name;
    $unrelated = 1;
}

function dynamic_variable_without_global(string $name): void
{
    $$name = 1;
}

function dynamic_global_does_not_leak_into_closure(string $name): \Closure
{
    global $$name;
    return function () use ($name): void {
        $$name = 1;
    };
}
