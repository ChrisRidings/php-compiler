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