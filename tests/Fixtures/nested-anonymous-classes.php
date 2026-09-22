<?php

namespace App;

class ServiceA
{
    public function client(): object
    {
        return new class {
            public function send(): void
            {
                $_GET['a'];
            }

            public string $value {
                get => $_GET['value'];
            }
        };
    }
}

class ServiceB
{
    public function client(): object
    {
        return new class {
            public function send(): void
            {
                $_GET['b'];
            }

            public string $value {
                get => $_GET['value'];
            }
        };
    }
}
