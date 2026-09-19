<?php

/*
 * Methods without a body (#16): interface and abstract methods report nothing.
 */

interface BodylessInterface
{
    public function signatureOnly(array $input = []): void;
}

abstract class BodylessAbstract
{
    abstract public function abstractOnly(): void;

    abstract protected function abstractWithDefault(string $key = 'x'): string;
}
