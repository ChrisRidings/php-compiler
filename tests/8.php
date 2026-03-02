<?php
echo "=== While Loop Tests ===\n";

// while loop ascending
echo "Ascending 0..4: ";
$i = 0;
while ($i < 5) {
    echo "$i ";
    $i++;
}
echo "\n";

// while loop descending
echo "Descending 5..1: ";
$j = 5;
while ($j > 0) {
    echo "$j ";
    $j--;
}
echo "\n";

// do-while loop
echo "Do-while loop 1..3: ";
$k = 1;
do {
    echo "$k ";
    $k++;
} while ($k <= 3);
echo "\n";

// do-while empty body test
$l = 5;
do {} while ($l < 0); // should execute once