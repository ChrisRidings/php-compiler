<?php
echo "=== For Loop Tests ===\n";

// Simple ascending loop
echo "Ascending 0..4: ";
for ($i = 0; $i < 5; $i++) {
    echo $i . " ";
}
echo "\n";

// Descending loop
echo "Descending 5..1: ";
for ($j = 5; $j > 0; $j--) {
    echo $j . " ";
}
echo "\n";

// Custom step
echo "Step 2: ";
for ($k = 0; $k <= 10; $k += 2) {
    echo $k . " ";
}
echo "\n";

// Negative step
echo "Negative step: ";
for ($l = 10; $l > 0; $l -= 3) {
    echo $l . " ";
}
echo "\n";

// Empty body
echo "Empty loop body: ";
for ($m = 0; $m < 3; $m++) {
    // nothing
}
echo "done\n";

// Complex initialization and step
echo "Multiple vars: ";
for ($n = 0, $o = 10; $n < 5; $n++, $o -= 2) {
    echo "i=$n,j=$o ";
}
echo "\n";
