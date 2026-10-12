<?php
function pureA($x) { return pureB($x); }
function pureB($x) { return pureA($x); }
function readSeed($x) { global $shared; return $shared + $x; }
function writeSeed($x) { global $shared; $shared = $x; }
function left($x) { right($x); readSeed($x); }
function right($x) { left($x); writeSeed($x); }
function readOnly($x) {
    readSeed($x);
}
function writeOnly($x) {
    writeSeed($x);
}
function repeat($x) {
    readSeed($x);
    readSeed($x);
}
