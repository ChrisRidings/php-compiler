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
echo "opening directory\n";
$dirHandle = opendir($testDir);
echo $dirHandle . "\n";
echo "opened directory\n";
if ($dirHandle) {
    echo "opened directory\n";
    while (($file = readdir($dirHandle)) !== false) {
        echo "---" . $file . "\n";
        if (preg_match('/^\d+\.php$/', $file)) {
            $testFiles[] = $file;
        }
    }
    closedir($dirHandle);
    echo "closed directory\n";
    // Sort the test files numerically
    natsort($testFiles);
    echo "sorted files\n";
    $testFiles = array_values($testFiles);
}
echo "end\n";
print_r($testFiles);