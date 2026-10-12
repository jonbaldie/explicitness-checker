<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;

/**
 * Definite fresh shallow copies, and the joins that discard assignments.
 *
 * Each join stores the root map from the moment it opened. PHP copies that
 * array on write, so later updates do not change the stored map. On close
 * the stored map returns and every name assigned inside the join is removed.
 */
class JoinMap
{
    protected const PREFIX = 'prefix';

    protected const GROUP = 'group';

    protected const EACH = 'each';

    /**
     * Subnode name to prefix, group, or each. The only list of conditional nodes.
     *
     * @var array<string, array<string, string>>
     */
    protected const FORKS = [
        'PhpParser\Node\Stmt\If_' => ['cond' => self::PREFIX, 'stmts' => self::GROUP, 'elseifs' => self::EACH, 'else' => self::EACH],
        'PhpParser\Node\Stmt\While_' => ['cond' => self::PREFIX, 'stmts' => self::GROUP],
        'PhpParser\Node\Stmt\Do_' => ['stmts' => self::GROUP, 'cond' => self::PREFIX],
        'PhpParser\Node\Stmt\For_' => ['init' => self::PREFIX, 'cond' => self::EACH, 'loop' => self::EACH, 'stmts' => self::GROUP],
        'PhpParser\Node\Stmt\Foreach_' => ['expr' => self::PREFIX, 'keyVar' => self::PREFIX, 'valueVar' => self::PREFIX, 'stmts' => self::GROUP],
        'PhpParser\Node\Stmt\Switch_' => ['cond' => self::PREFIX, 'cases' => self::EACH],
        'PhpParser\Node\Stmt\TryCatch' => ['stmts' => self::GROUP, 'catches' => self::EACH, 'finally' => self::EACH],
        'PhpParser\Node\Expr\Ternary' => ['cond' => self::PREFIX, 'if' => self::EACH, 'else' => self::EACH],
        'PhpParser\Node\Expr\BinaryOp\BooleanAnd' => ['left' => self::PREFIX, 'right' => self::EACH],
        'PhpParser\Node\Expr\BinaryOp\BooleanOr' => ['left' => self::PREFIX, 'right' => self::EACH],
        'PhpParser\Node\Expr\BinaryOp\LogicalAnd' => ['left' => self::PREFIX, 'right' => self::EACH],
        'PhpParser\Node\Expr\BinaryOp\LogicalOr' => ['left' => self::PREFIX, 'right' => self::EACH],
        'PhpParser\Node\Expr\BinaryOp\Coalesce' => ['left' => self::PREFIX, 'right' => self::EACH],
        'PhpParser\Node\Expr\Match_' => ['cond' => self::PREFIX, 'arms' => self::EACH],
        'PhpParser\Node\MatchArm' => ['conds' => self::EACH, 'body' => self::EACH],
    ];

    /**
     * Children that rebind a name the way an assignment does.
     *
     * @var array<string, list<string>>
     */
    protected const REBINDS = [
        'PhpParser\Node\Stmt\Foreach_' => ['keyVar', 'valueVar'],
        'PhpParser\Node\Stmt\Catch_' => ['var'],
    ];

    /** @var array<string, FreshShallow> */
    protected array $roots = [];

    /** @var list<array{saved: array<string, FreshShallow>, assigned: array<string, true>}> */
    protected array $joins = [];

    /** @var list<Node> */
    protected array $stack = [];

    /** @var array<int, true> */
    protected array $groupOpen = [];

    /** @var array<int, true> */
    protected array $eachOpen = [];

    /** @var array<string, true> */
    protected array $forkShapeChecked = [];

    public function origin(string $name): ?string
    {
        if (!isset($this->roots[$name])) {
            return null;
        }

        return $this->roots[$name]->getOrigin();
    }

    public function establish(string $name): void
    {
        $this->roots[$name] = new FreshShallow($name);
        $this->touch($name);
    }

    public function killBound(Node $node): void
    {
        foreach ($this->boundNames($node) as $name) {
            unset($this->roots[$name]);
            $this->touch($name);
        }
    }

    public function enter(Node $node): void
    {
        $this->checkFork($node);
        if ($this->stack !== []) {
            $parent = $this->stack[array_key_last($this->stack)];
            $this->killRebound($parent, $node);
            $this->enterSlot($parent, $node);
        }
        $this->stack[] = $node;
    }

    public function leave(Node $node): void
    {
        array_pop($this->stack);
        $id = spl_object_id($node);
        if (isset($this->groupOpen[$id])) {
            unset($this->groupOpen[$id]);
            $this->closeJoin();
        }
        if (isset($this->eachOpen[$id])) {
            unset($this->eachOpen[$id]);
            $this->closeJoin();
        }
    }

    /**
     * @return list<string>
     */
    protected function boundNames(Node $node): array
    {
        $name = VariableName::of($node);
        if ($name !== null) {
            return [$name];
        }
        if (!$node instanceof \PhpParser\Node\Expr\List_) {
            return [];
        }

        $names = [];
        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }
            foreach ($this->boundNames($item->value) as $bound) {
                $names[] = $bound;
            }
        }

        return $names;
    }

    protected function killRebound(Node $parent, Node $child): void
    {
        if (!isset(self::REBINDS[$parent::class])) {
            return;
        }
        foreach (self::REBINDS[$parent::class] as $subnode) {
            if ($this->sameChild($parent, $subnode, $child)) {
                $this->killBound($child);

                return;
            }
        }
    }

    protected function enterSlot(Node $parent, Node $child): void
    {
        $slot = $this->slot($parent, $child);
        $id = spl_object_id($parent);
        if ($slot === self::PREFIX || $slot === self::EACH) {
            if (isset($this->groupOpen[$id])) {
                unset($this->groupOpen[$id]);
                $this->closeJoin();
            }
        }
        if ($slot === self::GROUP) {
            if (!isset($this->groupOpen[$id])) {
                $this->groupOpen[$id] = true;
                $this->openJoin();
            }

            return;
        }
        if ($slot !== self::EACH) {
            return;
        }

        $this->openJoin();
        $this->eachOpen[spl_object_id($child)] = true;
    }

    protected function slot(Node $parent, Node $child): ?string
    {
        if (!isset(self::FORKS[$parent::class])) {
            return null;
        }
        foreach (self::FORKS[$parent::class] as $name => $kind) {
            if ($this->sameChild($parent, $name, $child)) {
                return $kind;
            }
        }

        return null;
    }

    protected function checkFork(Node $node): void
    {
        $class = $node::class;
        if (!isset(self::FORKS[$class]) || isset($this->forkShapeChecked[$class])) {
            return;
        }
        $this->forkShapeChecked[$class] = true;
        $known = array_flip($node->getSubNodeNames());
        foreach (array_keys(self::FORKS[$class]) as $name) {
            if (!isset($known[$name])) {
                throw new \LogicException('Fork subnode ' . $name . ' is not on ' . $class);
            }
        }
    }

    protected function sameChild(Node $parent, string $name, Node $child): bool
    {
        $vars = get_object_vars($parent);
        if (!array_key_exists($name, $vars)) {
            return false;
        }
        $value = $vars[$name];
        if ($value === $child) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $element) {
            if ($element === $child) {
                return true;
            }
        }

        return false;
    }

    protected function touch(string $name): void
    {
        foreach (array_keys($this->joins) as $index) {
            $this->joins[$index]['assigned'][$name] = true;
        }
    }

    protected function openJoin(): void
    {
        $this->joins[] = ['saved' => $this->roots, 'assigned' => []];
    }

    protected function closeJoin(): void
    {
        $join = array_pop($this->joins);
        if (!is_array($join)) {
            return;
        }

        // The walk visits both arms, so a root assigned inside this join does not survive it.
        $this->roots = $join['saved'];
        foreach (array_keys($join['assigned']) as $name) {
            unset($this->roots[$name]);
        }
    }
}
