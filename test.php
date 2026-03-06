<?php
function factorial($n) {
    if ($n <= 1) {
        return 1;
    }
    return $n * factorial($n - 1);
}

$start = microtime(true);
$result = factorial(100);
$end = microtime(true);

echo "Factorial of 100 calculated\n";
echo $result . "\n";
echo "Time: " . ($end - $start) . " seconds\n";
?>
