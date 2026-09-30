<?php

class NullsafeProps
{
    public function readLiteral(): ?string
    {
        return $this?->name;
    }

    public function readDynamic(string $prop): mixed
    {
        return $this?->$prop;
    }

    public function readBraced(string $prop): mixed
    {
        return $this?->{'x' . $prop};
    }
}
