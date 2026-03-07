<?php

function count_primes($limit) {
    $count = 0;

    for ($i = 2; $i <= $limit; $i++) {
        $prime = true;

        for ($j = 2; $j * $j <= $i; $j++) {
            if ($i % $j == 0) {
                $prime = false;
                break;
            }
        }

        if ($prime) {
            $count++;
        }
    }

    return $count;
}

$start = microtime(true);

$result = count_primes(10000000);

$end = microtime(true);

echo "Primes: $result\n";
echo "Time: " . ($end - $start) . "\n";