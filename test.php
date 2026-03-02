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

foreach ($testFiles as $testFile) {
    $testPath = $testDir . '/' . $testFile;

    if (!file_exists($testPath)) {
        echo "WARNING: Test file $testFile not found\n";
        $failCount++;
        continue;
    }

    echo "Testing $testFile...\n";

    // 1. Run with PHP
    $phpOutput = shell_exec("$phpPath $testPath 2>&1");

    // 2. Generate LLVM IR (run.php writes to output.ll)
    $llvmIrPath = $testDir . '/' . pathinfo($testFile, PATHINFO_FILENAME) . '.ll';
    $llvmOutput = shell_exec("$phpPath $llvmRunPath $testPath 2>&1");
    if (file_exists('output.ll')) {
        rename('output.ll', $llvmIrPath);
    }
    if (!file_exists($llvmIrPath)) {
        echo "  FAILED: Could not generate LLVM IR: $llvmOutput\n";
        $failCount++;
        continue;
    }

    // 3. Assemble to bitcode
    $llvmBcPath = $testDir . '/' . pathinfo($testFile, PATHINFO_FILENAME) . '.bc';
    $llvmAsOutput = shell_exec('"' . $llvmAsPath . '" "' . $llvmIrPath . '" -o "' . $llvmBcPath . '" 2>&1');
    if (!file_exists($llvmBcPath)) {
        echo "  FAILED: Could not assemble LLVM bitcode: $llvmAsOutput\n";
        $failCount++;
        unlink($llvmIrPath);
        continue;
    }

}