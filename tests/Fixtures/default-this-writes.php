<?php
class MutableCart {
    public int $amount {
        set { $this->value = $value; }
    }
    public function __CONSTRUCT() {
        $this->items = [];
        global $ctorGlobal;
        $ctorGlobal = 1;
        $later = function () { $this->later = 1; };
        $arrow = fn () => $this->arrow = 1;
        $nested = new class {
            public function change($value) { $this->nested = $value; }
        };
    }
    public function assign($value) { $this->value = $value; }
    public function append($value) { $this->items[] = $value; }
    public function index($value) { $this->items[0] = $value; }
    public function compound($value) { $this->count += $value; }
    public function coalesce($value) { $this->value ??= $value; }
    public function increment() { ++$this->count; }
    public function decrement() { --$this->count; }
    public function remove() { unset($this->value); }
    public function reference($value) { $this->ref = &$value; }
    public function referenceRead() { $local = &$this->ref; }
    public function sortItems() { sort($this->items); }
    public function dynamic($name, $value) { $this->{$name} = $value; }
    public function globalName($value) { $this->{$GLOBALS['field']} = $value; }
    public function owner() { return $this->value; }
    public function withValue($value) { $new = clone $this; $new->value = $value; return $new; }
}
