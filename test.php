<?php
class Counter {
    public int $value = 0;

    public function increment() {
        $this->value = $this->value + 1;
    }

    public function getValue(): int {
        return $this->value;
    }
}

$counter = new Counter();
$counter->increment();
$counter->increment();
echo $counter->getValue() . "\n";