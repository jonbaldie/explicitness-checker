<?php

// Regression fixture for #9: foreach key/value targets and catch variables are
// written, not read.

function iterateIntoGlobal(): void
{
    global $items, $item;
    foreach ($items as $item) {
    }
}

function iterateKeysIntoGlobal(): void
{
    global $position;
    foreach ($_POST as $position => $value) {
    }
}

function catchIntoGlobal(): void
{
    global $lastError;
    try {
        throw new RuntimeException('failed');
    } catch (RuntimeException $lastError) {
    }
}
