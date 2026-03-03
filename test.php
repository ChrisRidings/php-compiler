<?php

// 1. Using isset()
echo "<strong>1. Using isset()</strong><br>";
$var1 = "Hello, World!";
$var2 = null;
echo isset($var1) ? "var1 is set.<br>" : "var1 is not set.<br>";
echo isset($var2) ? "var2 is set.<br>" : "var2 is not set.<br>";
echo "<br>";