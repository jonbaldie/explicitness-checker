<?php
function duplicate($x) { global $shared; return $shared; }
class Twice {
    public function seed($x) { global $shared; return $shared; }
    public function caller($x) { return $this->seed($x); }
}
