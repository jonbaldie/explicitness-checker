<?php
namespace Demo;
function c($x) {
    global $shared;
    return $shared + $x;
}
