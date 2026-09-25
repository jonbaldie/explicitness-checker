<?php

// Compound assignments and increments both read and write their target, and
// statements inside foreach and catch bodies are walked like any others.

function addsToGlobal(): void
{
    global $total;
    $total += $_GET['amount'];
}

function incrementsAndDecrementsGlobals(): void
{
    global $up, $down;
    ++$up;
    --$down;
}

function readsInsideLoopAndCatchBodies(array $rows): void
{
    foreach ($rows as $row) {
        echo $_POST['a'];
    }
    try {
        throw new RuntimeException('failed');
    } catch (RuntimeException $e) {
        echo $_COOKIE['b'];
    }
}

function globalsArrayKeys(string $key): void
{
    echo $GLOBALS[0];
    echo $GLOBALS[$key];
    echo $GLOBALS[$key . 'suffix'];
    echo $GLOBALS[$$key];
}

// The name expression is read; the variable-variable write stays local without a global declaration.
function variableVariable(): void
{
    ${$_GET['name']} = 1;
}
