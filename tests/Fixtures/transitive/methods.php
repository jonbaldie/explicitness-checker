<?php
namespace Dispatch;
class BaseType {
    public function input($x) { global $shared; return $shared + $x; }
    public function output($x) { global $shared; $shared = $x; }
    public function via($x) { return $this->input($x); }
    public function lexical($x) { return static::input($x); }
}
class ChildType extends BaseType {
    public function input($x) { return $x; }
    public function override($x) { return $this->input($x); }
    public function inheritedOutput($x) { self::output($x); }
    public function explicitParent($x) { return parent::input($x); }
    public function lateStatic($x) { return static::input($x); }
    public function localSelf($x) { return self::input($x); }
    public function inherited($x) { return $this->via($x); }
}
