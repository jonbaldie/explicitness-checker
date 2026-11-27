<?php

function c1($a) { $x = (object) $a; $x->p = 1; }
function c2($a) { $x = (array) $a; $x[0]->p = 1; }
function c4($a) { $x = $a->item; $x->p = 1; }
function fresh_object($a) { $a = new stdClass(); $x = (object) $a; $x->p = 1; }
function unrelated($a) { $b = new stdClass(); $x = (object) $b; $x->p = 1; }
function integer_cast($a) { $x = (int) $a; $x->p = 1; }
function string_cast($a) { $x = (string) $a; $x->p = 1; }
function bool_cast($a) { $x = (bool) $a; $x->p = 1; }
function float_cast($a) { $x = (float) $a; $x->p = 1; }
function unset_cast($a) { $x = (unset) $a; $x->p = 1; }
