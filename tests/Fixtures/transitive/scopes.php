<?php
namespace Scopes;
function action($x) { global $shared; $shared = $x; }
function outer($x) {
    function nested($v) { action($v); }
    $closure = function ($v) { action($v); };
    $arrow = fn ($v) => action($v);
    $object = new class {
        public function m($v) { action($v); }
    };
    return $x;
}
function callableFactory($x) { return action(...); }
function invoke($callback, $x) { return $callback($x); }
function supplies($x) {
    return invoke(function ($v) { action($v); }, $x);
}
