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

    // 4. Compile to object file
    $objectPath = $testDir . '/' . pathinfo($testFile, PATHINFO_FILENAME) . '.o';
    $clangOutput = shell_exec('"' . $clangPath . '" -c "' . $llvmBcPath . '" -o "' . $objectPath . '" -Wno-override-module 2>&1');
    if (!file_exists($objectPath)) {
        echo "  FAILED: Could not compile to object file: $clangOutput\n";
        $failCount++;
        unlink($llvmIrPath);
        unlink($llvmBcPath);
        continue;
    }

    // 5. Link to executable
    $exePath = $testDir . '/' . pathinfo($testFile, PATHINFO_FILENAME) . '.exe';
    $linkOutput = shell_exec('"' . $clangPath . '" "' . $objectPath . '" libphp/php.c -o "' . $exePath . '" -Ilibphp -Wno-deprecated-declarations 2>&1');
    if (!file_exists($exePath)) {
        echo "  FAILED: Could not link to executable: $linkOutput\n";
        $failCount++;
        unlink($llvmIrPath);
        unlink($llvmBcPath);
        unlink($objectPath);
        continue;
    }

    // 6. Run compiled version
    $llvmRuntimeOutput = shell_exec('"' . $exePath . '" 2>&1');

    // 7. Compare outputs
    if ($llvmRuntimeOutput === null) {
        echo "  FAILED: Could not execute compiled executable\n";
        $failCount++;
    } else {
        if (trim($phpOutput) === trim($llvmRuntimeOutput)) {
            echo "  PASSED\n";
            $passCount++;
        } else {
            echo "  FAILED: Output mismatch\n";
            echo "  PHP Output:\n" . str_replace("\n", "\n    ", trim($phpOutput)) . "\n";
            echo "  LLVM Output:\n" . str_replace("\n", "\n    ", trim($llvmRuntimeOutput)) . "\n";
            $failCount++;
        }
    }

    // Cleanup
    $filesToClean = [$llvmIrPath, $llvmBcPath, $objectPath, $exePath];
    foreach ($filesToClean as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    echo "\n";
}

echo str_repeat('-', 70) . "\n";
echo "Test Results: $passCount passed, $failCount failed\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
