<?php

function zeroReferenceLoop($items) {
    $r = &$GLOBALS['first'];
    foreach ($items as &$r) {}
    $r = 7;
}
