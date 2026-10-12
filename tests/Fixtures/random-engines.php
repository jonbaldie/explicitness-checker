<?php

use Random\Engine\Mt19937;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Engine\Secure;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

function default_engine()
{
    return new Randomizer();
}

function secure_engine()
{
    return new Randomizer(
        new Secure()
    );
}

function unseeded_mt()
{
    return new Randomizer(new Mt19937());
}

function unseeded_xoshiro()
{
    return new Xoshiro256StarStar();
}

function unseeded_pcg()
{
    return new PcgOneseq128XslRr64();
}

function null_seed()
{
    return new Mt19937(null);
}

function named_null()
{
    return new Mt19937(seed: null);
}

function mode_only()
{
    return new Mt19937(mode: MT_RAND_MT19937);
}

function standalone_secure()
{
    return new Secure();
}

function repeated()
{
    new Secure();
    new Secure();
}

function seeded_zero()
{
    return new Mt19937(0);
}

function seeded_mt()
{
    return new Randomizer(new Mt19937(42));
}

function seeded_string()
{
    return new Xoshiro256StarStar('abc');
}

function variable_seed($seed)
{
    return new Mt19937($seed);
}

function caller_engine($engine)
{
    return new Randomizer($engine);
}

function lookalike()
{
    return new \App\Random\Engine\Secure();
}
