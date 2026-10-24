import pathlib, subprocess, json, os
ROOT=pathlib.Path.cwd()
BASE=ROOT/'build/exploratory-2026-10-24'
BASE.mkdir(parents=True, exist_ok=True)
DATA=BASE/'fixtures'
DATA.mkdir(exist_ok=True)
LOG=BASE/'journeys.log'

def fixture(path, code):
    p=DATA/path; p.parent.mkdir(parents=True,exist_ok=True); p.write_text(code); return p

def run(label, args):
    cmd=['php',str(ROOT/'bin/explicitness-checker'),*map(str,args)]
    r=subprocess.run(cmd,capture_output=True,text=True,timeout=30)
    with LOG.open('a') as f:
        f.write('\n### '+label+'\n$ '+ ' '.join(cmd)+'\n'+r.stdout+r.stderr+'\nexit='+str(r.returncode)+'\n')
    print(label, 'exit='+str(r.returncode), r.stdout[-250:].replace('\n',' '))
    return r

p=fixture('graph.php','''<?php
namespace Shop;
function load($x) { return $_GET[$x]; }
function middle($x) { return load($x); }
function entry($x) { return middle($x); }
function pure($x) { return $x; }
function recA($x) { return $x ? recB($x-1) : 0; }
function recB($x) { return $x ? recA($x-1) : 0; }
function cycleA($x) { return $x ? cycleB($x-1) : $_POST['x']; }
function cycleB($x) { return cycleA($x); }
class Base {
    public function read($x) { return $_GET[$x]; }
    public function wrap($x) { return $this->read($x); }
}
class Child extends Base {
    public function read($x) { return $x; }
    public function outer($x) { return $this->wrap($x); }
    public function parentRead($x) { return parent::read($x); }
}
function capture($x) { $f = fn($y) => load($y); return $x; }
function stored($x) { return load(...); }
''')
run('graph direct',[p]); run('graph transitive',['--transitive','--min-explicitness=100',p])

shell=fixture('app/src/Command/Run.php',"<?php\nfunction shell($x) { echo $x; return $_GET['x']; }\n")
fixture('app/src/Billing/Command/Message.php',"<?php\nfunction core($x) { return $_POST[$x]; }\n")
fixture('app/src/Billing/Calculate.php',"<?php\nfunction calculate($x) { return $x+1; }\n")
fixture('app/vendor/ignored.php',"<?php\nfunction vendorCode($x) { return $_ENV[$x]; }\n")
run('whole app',['--strict','--min-explicitness=100', DATA/'app'])
run('precise shell exclude',['--strict','--exclude=src/Command','--min-explicitness=100',DATA/'app'])
run('bare shell exclude',['--strict','--exclude=Command','--min-explicitness=100',DATA/'app'])
run('single excluded file',['--strict','--exclude=src/Command','--min-explicitness=100',shell])
run('relative root exclude',['--strict','--exclude=src/Command','--min-explicitness=100',DATA/'app/src/Command'])

fixture('gate/good.php',"<?php\nfunction good($x) { return $x; }\nfunction another($x) { return $x; }\n")
fixture('gate/bad.php',"<?php\nfunction bad($x) { return $_GET[$x]; }\n")
for threshold in ['66.66','66.67','0','100']:
    run('gate '+threshold,['--min-explicitness='+threshold,DATA/'gate'])
fixture('gate/broken.php',"<?php\nfunction incomplete(\n")
run('gate parse failure',['--min-explicitness=0',DATA/'gate'])
run('exclude broken',['--min-explicitness=0','--exclude-pattern=broken',DATA/'gate'])
run('include only pure',['--min-explicitness=100','--include-pattern=good',DATA/'gate'])
run('invalid filter',['--min-explicitness=0','--include-pattern=(',DATA/'gate'])
