<?php
function duplicate($x) { return $x; }
function ambiguousCaller($x) { return duplicate($x); }
function missingCaller($x) { return missing($x); }
class Twice {
    public function seed($x) { return $x; }
}
