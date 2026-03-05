<?php
// 1. array(): Creates an array
$fruits = array("Apple", "Banana", "Cherry");
echo "1. array(): ";
print_r($fruits);
echo "<br><br>";

// 2. array_change_key_case(): Changes all keys to lowercase or uppercase
$assocArray = ["Name" => "John", "AGE" => 25];
$lowerKeys = array_change_key_case($assocArray, CASE_LOWER);
echo "2. array_change_key_case(): ";
print_r($lowerKeys);
echo "<br><br>";

// 3. array_chunk(): Splits an array into chunks
$numbers = [1, 2, 3, 4, 5];
$chunks = array_chunk($numbers, 2);
echo "3. array_chunk(): ";
print_r($chunks);
echo "<br><br>";

// 4. array_column(): Returns values from a column in an array
$students = [
    ["id" => 1, "name" => "Alice"],
    ["id" => 2, "name" => "Bob"]
];
$names = array_column($students, "name");
echo "4. array_column(): ";
print_r($names);
echo "<br><br>";

// 5. array_combine(): Creates an array with keys and values
$keys = ["name", "age"];
$values = ["John", 25];
$combined = array_combine($keys, $values);
echo "5. array_combine(): ";
print_r($combined);
echo "<br><br>";

// 6. array_fill(): Fills an array with values
$filledArray = array_fill(0, 5, "Hello");
echo "6. array_fill(): ";
print_r($filledArray);
echo "<br><br>";

// 7. array_fill_keys(): Fills an array with specified keys
$keys = ["a", "b", "c"];
$fillKeys = array_fill_keys($keys, "value");
echo "7. array_fill_keys(): ";
print_r($fillKeys);
echo "<br><br>";

// 8. array_merge(): Merges one or more arrays
$array1 = [1, 2];
$array2 = [3, 4];
$merged = array_merge($array1, $array2);
echo "8. array_merge(): ";
print_r($merged);
echo "<br><br>";

// 9. array_merge_recursive(): Recursively merges arrays
$array1 = ["a" => [1], "b" => [2]];
$array2 = ["a" => [3]];
$recursiveMerge = array_merge_recursive($array1, $array2);
echo "9. array_merge_recursive(): ";
print_r($recursiveMerge);
echo "<br><br>";

// 10. array_pad(): Pads an array to a specified length with a value
$paddedArray = array_pad([1, 2], 5, 0);
echo "10. array_pad(): ";
print_r($paddedArray);
echo "<br><br>";

// 11. array_push(): Adds elements to the end of an array
$stack = ["Banana", "Cherry"];
array_push($stack, "Apple", "Date");
echo "11. array_push(): ";
print_r($stack);
echo "<br><br>";

// 12. array_pop(): Removes the last element from an array
array_pop($stack);
echo "12. array_pop(): ";
print_r($stack);
echo "<br><br>";