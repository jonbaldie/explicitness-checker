<?php

use Random\Randomizer;
use Random\Engine\Secure;
use Random\Engine\Mt19937;
use Random\Engine\Xoshiro256StarStar;
use Random\Engine\PcgOneseq128XslRr64;

function secure($engine, $mode, $seed) {
    new Secure();
}

function nested_secure($engine, $mode, $seed) {
    new Randomizer(
        new Secure()
    );
}

function nested_mt($engine, $mode, $seed) {
    new Randomizer(
        new Mt19937()
    );
}

function seeded_nested($engine, $mode, $seed) {
    new Randomizer(new Mt19937(42));
}

function supplied_engine($engine, $mode, $seed) {
    new Randomizer($engine);
}

function reordered($engine, $mode, $seed) {
    new Mt19937(mode: $mode, seed: 0);
}

function mode_only($engine, $mode, $seed) {
    new Mt19937(mode: $mode);
}

function case_secure($engine, $mode, $seed) {
    new \random\engine\secure();
}

function custom($engine, $mode, $seed) {
    new \App\Secure();
}

function mt19937_0($engine, $mode, $seed) {
    new Mt19937();
}

function mt19937_1($engine, $mode, $seed) {
    new Mt19937(null);
}

function mt19937_2($engine, $mode, $seed) {
    new Mt19937(seed: null);
}

function mt19937_3($engine, $mode, $seed) {
    new Mt19937(0);
}

function mt19937_4($engine, $mode, $seed) {
    new Mt19937(42);
}

function mt19937_5($engine, $mode, $seed) {
    new Mt19937($seed);
}

function mt19937_6($engine, $mode, $seed) {
    new Mt19937(seed: $seed);
}

function xoshiro256starstar_0($engine, $mode, $seed) {
    new Xoshiro256StarStar();
}

function xoshiro256starstar_1($engine, $mode, $seed) {
    new Xoshiro256StarStar(null);
}

function xoshiro256starstar_2($engine, $mode, $seed) {
    new Xoshiro256StarStar(seed: null);
}

function xoshiro256starstar_3($engine, $mode, $seed) {
    new Xoshiro256StarStar(0);
}

function xoshiro256starstar_4($engine, $mode, $seed) {
    new Xoshiro256StarStar(42);
}

function xoshiro256starstar_5($engine, $mode, $seed) {
    new Xoshiro256StarStar($seed);
}

function xoshiro256starstar_6($engine, $mode, $seed) {
    new Xoshiro256StarStar(seed: $seed);
}

function pcgoneseq128xslrr64_0($engine, $mode, $seed) {
    new PcgOneseq128XslRr64();
}

function pcgoneseq128xslrr64_1($engine, $mode, $seed) {
    new PcgOneseq128XslRr64(null);
}

function pcgoneseq128xslrr64_2($engine, $mode, $seed) {
    new PcgOneseq128XslRr64(seed: null);
}

function pcgoneseq128xslrr64_3($engine, $mode, $seed) {
    new PcgOneseq128XslRr64(0);
}

function pcgoneseq128xslrr64_4($engine, $mode, $seed) {
    new PcgOneseq128XslRr64(42);
}

function pcgoneseq128xslrr64_5($engine, $mode, $seed) {
    new PcgOneseq128XslRr64($seed);
}

function pcgoneseq128xslrr64_6($engine, $mode, $seed) {
    new PcgOneseq128XslRr64(seed: $seed);
}

