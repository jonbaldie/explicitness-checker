<?php

function next_id(): int {
    static $id = 0;

    return ++$id;
}
