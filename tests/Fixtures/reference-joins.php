<?php

function joinedTargets($flag) {
    if ($flag) {
        $r = &$GLOBALS['first'];
    } else {
        $r = &$GLOBALS['second'];
    }
    $r = 7;
}

function siblingUnset($flag) {
    $r = &$GLOBALS['first'];
    if ($flag) {
        unset($r);
    } else {
        $r = 7;
    }
}

function emptyReferenceLoop($items) {
    $r = &$GLOBALS['first'];
    foreach ($items as &$r) {}
    $r = 7;
}

function optionalReferenceParameter(&$p, $flag) {
    if ($flag) {
        $p = &$GLOBALS['first'];
    }
    $p = 1;
}
