<?php

class A {
    public $x;

    public function hello() {
        echo "hi\n";
    }
}

class B {
    public $x;

}

$a = new A();
$a->hello();

$b = new A();

$c = new B();

$a->x = 5;
$b->x = 5;
$c->x = 10;

echo $a->x . "\n";
echo $b->x . "\n";
echo $c->x . "\n";

$b->x = 6;
echo $a->x . "\n";
echo $b->x . "\n";
echo $c->x . "\n";
