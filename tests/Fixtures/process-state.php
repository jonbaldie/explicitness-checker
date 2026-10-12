<?php

function state_0($value) {
    json_last_error();
}

function state_1($value) {
    json_last_error_msg();
}

function state_2($value) {
    preg_last_error();
}

function state_3($value) {
    preg_last_error_msg();
}

function state_4($value) {
    error_get_last();
}

function state_5($value) {
    libxml_get_errors();
}

function state_6($value) {
    libxml_get_last_error();
}

function state_7($value) {
    getcwd();
}

function state_8($value) {
    error_clear_last();
}

function state_9($value) {
    libxml_clear_errors();
}

function state_10($value) {
    chdir($value);
}

function state_11($value) {
    libxml_use_internal_errors();
}

function state_12($value) {
    libxml_use_internal_errors(null);
}

function state_13($value) {
    libxml_use_internal_errors(use_errors: $value);
}

function state_14($value) {
    mb_internal_encoding();
}

function state_15($value) {
    mb_internal_encoding(null);
}

function state_16($value) {
    mb_internal_encoding(encoding: $value);
}

function state_17($value) {
    umask();
}

function state_18($value) {
    umask(null);
}

function state_19($value) {
    umask(mask: $value);
}

