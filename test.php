<?php
echo "=== Array Tests ===\n";

// Numeric array
$nums = [10, 20, 30];
echo "Numeric array:\n";
echo $nums[0] . " "; // 10
echo $nums[1] . " "; // 20
echo $nums[2] . "\n"; // 30

// Associative array
$assoc = ["a" => 1, "b" => 2, "c" => 3];
echo "Associative array:\n";
echo $assoc["a"] . " "; // 1
echo $assoc["b"] . " "; // 2
echo $assoc["c"] . "\n"; // 3

// Mixed array
$mixed = [0 => "zero", "one" => 1, 2 => "two"];
echo "Mixed array:\n";
echo $mixed[0] . " "; // zero
echo $mixed["one"] . " "; // 1
echo $mixed[2] . "\n"; // two

// Array assignment / update
$nums[1] = 42;
echo "Updated numeric array:\n";
echo $nums[0] . " "; // 10
echo $nums[1] . " "; // 42
echo $nums[2] . "\n"; // 30

$assoc["b"] = 99;
echo "Updated associative array:\n";
echo $assoc["a"] . " "; // 1
echo $assoc["b"] . " "; // 99
echo $assoc["c"] . "\n"; // 3

// Iteration: foreach
echo "Iterating numeric array:\n";
foreach ($nums as $num) {
    echo $num . " ";
}
echo "\n";

echo "Iterating associative array:\n";
foreach ($assoc as $key => $val) {
    echo "$key=$val ";
}
echo "\n";

?>