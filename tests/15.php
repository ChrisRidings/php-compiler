<?php

class A {
    public $x;

    public function get_x_plus(int $b) {
        echo $this->x + $b . "\n";
    }
}

$a = new A();

$a->x = 1;
echo $a->get_x_plus(2) . "\n";
