<?php
function nowSeed($x) { return time(); }
function nowCaller($x) { return nowSeed($x); }
class Box {
    public function seed($x) { return $this->value; }
    public function caller($x) { return $this->seed($x); }
}
