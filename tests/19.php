<?php

// 1. Using isset()
echo "<strong>1. Using isset()</strong><br>";
$var1 = "Hello, World!";
$var2 = null;
echo isset($var1) ? "var1 is set.<br>" : "var1 is not set.<br>";
echo isset($var2) ? "var2 is set.<br>" : "var2 is not set.<br>";
echo "<br>";

// 2. Using unset()
echo "<strong>2. Using unset()</strong><br>";
$var = "Hello, World!";
echo isset($var) ? "Before unset: var is set.<br>" : "Before unset: var is not set.<br>";
unset($var);
echo isset($var) ? "After unset: var is set.<br>" : "After unset: var is not set.<br>";
echo "<br>";

// 3. Using empty()
echo "<strong>3. Using empty()</strong><br>";
$var1 = "";
$var2 = "PHP";
$var3 = 0;
echo empty($var1) ? "var1 is empty.<br>" : "var1 is not empty.<br>";
echo empty($var2) ? "var2 is empty.<br>" : "var2 is not empty.<br>";
echo empty($var3) ? "var3 is empty.<br>" : "var3 is not empty.<br>";
echo "<br>";

// 4. Using gettype()
echo "<strong>4. Using gettype()</strong><br>";
$var1 = 42;
$var2 = "PHP";
$var3 = true;
echo "The type of var1 is: " . gettype($var1) . "<br>";
echo "The type of var2 is: " . gettype($var2) . "<br>";
echo "The type of var3 is: " . gettype($var3) . "<br>";
echo "<br>";

// 5. Using settype()
echo "<strong>5. Using settype()</strong><br>";
$var = "123"; // Initially a string
echo "Before settype(): Type of var is: " . gettype($var) . "<br>";
settype($var, "integer"); // Change type to integer
echo "After settype(): Type of var is: " . gettype($var) . "<br>";
echo "<br>";

// 6. Using Variable Fun
function greet() {
    echo "Hello, welcome to PHP variable functions!<br>";
}

$functionName = "greet";
$functionName(); // Dynamically calls the greet() function