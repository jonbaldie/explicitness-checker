from pathlib import Path
exec(Path(__file__).with_name('probe.py').read_text().split("p=fixture('graph.php'")[0])
p=fixture('closure.php', '<?php\nfunction wrap($x) { return fn($y) => $x + $y; }\n')
for i in range(2):
    run('closure deprecation replay '+str(i+1),[p])
p=fixture('traditional-closure.php', '<?php\nfunction wrap($x) { return function ($y) use ($x) { return $x+$y; }; }\n')
run('traditional closure deprecation',[p])

fixture('imports/actions.php','''<?php
namespace Domain;
function source($key) { return $_GET[$key]; }
function write($key) { $_SESSION[$key] = 1; }
''')
fixture('imports/callers.php',r'''<?php
namespace Entry;
use function Domain\source as read;
use function Domain\{write as save};
function imported($x) { return read($x); }
function grouped($x) { save($x); }
function full($x) { return \Domain\source($x); }
function local($x) { return namespace\sub($x); }
function sub($x) { return $_POST[$x]; }
function fallback($x) { return globalSource($x); }
''')
fixture('imports/global.php', '<?php\nfunction globalSource($x) { return $_COOKIE[$x]; }\n')
run('import propagation',['--transitive','--min-explicitness=100', DATA/'imports'])
run('excluded targets',['--transitive','--exclude-pattern=actions','--min-explicitness=100', DATA/'imports'])

p=fixture('modes.php','''<?php
class Service {
    public function __construct($x) { $this->data = $x; }
    public function change($x) { $this->data = $x; }
    public function caller($x) { $this->change($x); }
    public function read($x) { return $this->data[$x]; }
    public function reader($x) { return $this->read($x); }
}
function clock($x) { return time() + $x; }
function callClock($x) { return clock($x); }
''')
run('mode default',['--transitive','--min-explicitness=100',p])
run('mode strict props',['--strict','--props','--transitive','--min-explicitness=100',p])

# A trait-supplied method shadows an inherited one in real PHP.
p=fixture('trait.php','''<?php
class ParentService { public function calculate($x) { return $_GET[$x]; } }
trait LocalMath { public function calculate($x) { return $x+1; } }
class ChildService extends ParentService {
    use LocalMath;
    public function run($x) { return $this->calculate($x); }
}
''')
run('trait override boundary',['--transitive','--min-explicitness=100',p])

# Re-open selection with absolute, relative, and ./-prefixed roots.
for root in [str(DATA/'app'), str((DATA/'app').relative_to(ROOT)), './'+str((DATA/'app').relative_to(ROOT))]:
    run('shell root spelling '+root,['--strict','--exclude=src/Command','--min-explicitness=100',root])
run('empty selection',['--min-explicitness=100','--include-pattern=nomatch',DATA/'app'])
