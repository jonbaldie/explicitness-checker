from pathlib import Path
exec(Path(__file__).with_name('probe.py').read_text().split("p=fixture('graph.php'")[0])
fixture('phpstan/src/Command/Shell.php','''<?php
namespace Demo;
class Clock { public static function get(): int { return 1; } }
class Shell {
    public int $value = 1;
    public function execute(): int { return $this->value + Clock::get(); }
}
''')
fixture('phpstan/src/Core.php','''<?php
namespace Demo;
function calculate(int $x): int { return $x+1; }
''')
config=BASE/'probe.neon'
config.write_text('includes:\n    - '+str(ROOT/'extension.neon')+'\nparameters:\n    level: 5\n    tmpDir: '+str(BASE/'phpstan-cache')+'\n    paths:\n        - '+str(DATA/'phpstan/src')+'\n    explicitness:\n        strict: true\n        props: true\n')
def stan(label):
    cmd=['php', '-d', 'memory_limit=1G', str(ROOT/'vendor/bin/phpstan'), 'analyse','-c',str(config),'--no-progress','--error-format=table']
    r=subprocess.run(cmd,capture_output=True,text=True,timeout=60)
    with LOG.open('a') as f: f.write('\n### '+label+'\n$ '+' '.join(cmd)+'\n'+r.stdout+r.stderr+'\nexit='+str(r.returncode)+'\n')
    print(label,r.returncode,r.stdout[-200:])
stan('PHPStan shell ordinary')
config.write_text(config.read_text()+'''    ignoreErrors:
        -
            identifier: explicitness.staticCall
            path: '''+str(DATA/'phpstan/src/Command/*')+'''
        -
            identifier: explicitness.objectProperty
            path: '''+str(DATA/'phpstan/src/Command/*')+'\n')
stan('PHPStan shell exempted')
p=DATA/'phpstan/src/Command/Shell.php'
p.write_text(p.read_text().replace('return $this->value + Clock::get();', '$this->missing(); return $this->value + Clock::get();'))
stan('PHPStan typo still checked')
