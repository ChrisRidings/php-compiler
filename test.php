#!/usr/bin/env php
<?php
declare(strict_types=1);

$phpPath = 'php';
$llvmRunPath = 'llvm/run.php';
$llvmAsPath = 'C:/Program Files/LLVM/bin/llvm-as.exe';
$clangPath = 'C:/Program Files/LLVM/bin/clang.exe';
$testDir = 'tests';

// Get all test files dynamically
echo "start\n";
$testFiles = [];
$dirHandle = opendir($testDir);
if ($dirHandle) {
    echo "opened directory\n";
    while (($file = readdir($dirHandle)) !== false) {
        echo $file . "\n";
        if (preg_match('/^\d+\.php$/', $file)) {
            $testFiles[] = $file;
        }
    }
    closedir($dirHandle);
    // Sort the test files numerically
    natsort($testFiles);
    $testFiles = array_values($testFiles);
}
echo "end\n";
print_r($testFiles);