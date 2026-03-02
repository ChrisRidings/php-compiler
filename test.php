<?php
function greet_length($name) {
    $greeting = "Hello, ";
    echo $greeting;
    echo $name;
    echo "!";
    return 5;
}

$result = greet_length("Alice");
echo $result;