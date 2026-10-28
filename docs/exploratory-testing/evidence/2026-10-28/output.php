<?php
function pure($x) { return $x + 1; }
function out($x) { echo $x; }
function capture($x) { return print_r($x, true); }
function emit($x) { return print_r($x, false); }
