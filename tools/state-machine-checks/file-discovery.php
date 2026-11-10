<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Cli\Console;
use JonBaldie\ExplicitnessChecker\Cli\FileFilter;
use JonBaldie\ExplicitnessChecker\Cli\PhpFileFinder;

require __DIR__ . '/bootstrap.php';

$root = sys_get_temp_dir() . '/ec-discovery-' . bin2hex(random_bytes(8));
$files = ['a.php', 'z.php', 'nested/q.PHP', 'nested/notes.txt', 'vendor/skip.php'];
mkdir($root, 0700);
mkdir($root . '/nested', 0700);
mkdir($root . '/vendor', 0700);
$out = fopen('php://memory', 'w+');
$err = fopen('php://memory', 'w+');
if ($out === false || $err === false) {
    throw new RuntimeException('Could not open console streams');
}
$count = 0;
try {
    for ($mask = 0; $mask < (1 << count($files)); $mask++) {
        foreach ($files as $position => $file) {
            $path = $root . '/' . $file;
            if (($mask & (1 << $position)) !== 0) {
                file_put_contents($path, '<?php');
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
        clearstatcache();
        foreach (['default', 'all', 'include-a', 'exclude-z'] as $case) {
            $filter = match ($case) {
                'default' => new FileFilter(['vendor']),
                'all' => new FileFilter([]),
                'include-a' => new FileFilter([], ['a\\.php$']),
                'exclude-z' => new FileFilter([], [], ['z\\.php$']),
            };
            $expected = [];
            foreach ($files as $position => $file) {
                if (($mask & (1 << $position)) === 0 || $file === 'nested/notes.txt') {
                    continue;
                }
                if (($case === 'default' && $file === 'vendor/skip.php')
                    || ($case === 'include-a' && $file !== 'a.php')
                    || ($case === 'exclude-z' && $file === 'z.php')) {
                    continue;
                }
                $expected[] = $root . '/' . $file;
            }
            sort($expected, SORT_STRING);
            $result = (new PhpFileFinder($filter, new Console($out, $err, false)))->find($root);
            expectSame("mask={$mask} filter={$case}", $expected, $result->getFiles());
            expectSame("unchecked mask={$mask} filter={$case}", [], $result->getUnchecked());
            $count++;
        }
    }
} finally {
    fclose($out);
    fclose($err);
    foreach ($files as $file) {
        if (file_exists($root . '/' . $file)) {
            unlink($root . '/' . $file);
        }
    }
    rmdir($root . '/nested');
    rmdir($root . '/vendor');
    rmdir($root);
}
printf("PASS: %d generated directory and filter combinations\n", $count);
