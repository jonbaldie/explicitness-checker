<?php

class DynamicProps
{
    public function readDynamic(string $prop): mixed
    {
        return $this->$prop;
    }

    public function writeDynamic(string $prop, mixed $val): void
    {
        $this->$prop = $val;
    }

    public function readBraced(string $prop): mixed
    {
        return $this->{'x' . $prop};
    }

    public function readLiteral(): mixed
    {
        return $this->literal;
    }
}
