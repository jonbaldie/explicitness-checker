<?php

function accesses_static_helper() {
    $data = SomeClass::staticMethod();

    return $data;
}
