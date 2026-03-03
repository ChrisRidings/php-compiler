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