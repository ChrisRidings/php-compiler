#!/usr/bin/env php
<?php
declare(strict_types=1);

$phpPath = 'php';
$llvmRunPath = 'llvm/run.php';
$llvmAsPath = 'C:/Program Files/LLVM/bin/llvm-as.exe';
$clangPath = 'C:/Program Files/LLVM/bin/clang.exe';
$testDir = 'tests';

// Get all test files dynamically
$testFiles = [];
$dirHandle = opendir($testDir);
if ($dirHandle) {
    while (($file = readdir($dirHandle)) !== false) {
        if (preg_match('/^\d+\.php$/', $file)) {
            $testFiles[] = $file;
        }
    }
    closedir($dirHandle);
    // Sort the test files numerically
    natsort($testFiles);
    $testFiles = array_values($testFiles);
}

$passCount = 0;
$failCount = 0;

echo "Running PHP to LLVM tests...\n";
echo str_repeat('-', 70) . "\n";