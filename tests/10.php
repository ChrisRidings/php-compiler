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


// Array assignment / update
$nums[1] = 42;
echo "Updated numeric array:\n";
echo $nums[0] . " "; // 10
echo $nums[1] . " "; // 42
echo $nums[2] . "\n"; // 30

?>