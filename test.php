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