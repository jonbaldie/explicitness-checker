<?php
class PropertyAction {
    public function __construct($values) { $this->values = $values; }
    public function write($value) { sort($this->values); }
    public function caller($value) { $this->write($value); }
}
