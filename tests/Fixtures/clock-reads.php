<?php

use DateTimeImmutable as ClockDate;

function tomorrow($date, $format) {
    new ClockDate('tomorrow');
}

function january($date, $format) {
    new DateTime('January 1');
}

function relative_anchor($date, $format) {
    new ClockDate('2020-01-01 +1 day');
}

function fixed_date($date, $format) {
    new ClockDate('2020-01-01');
}

function invalid_date($date, $format) {
    new ClockDate('not a valid date');
}

function partial($date, $format) {
    date_create('10:00');
}

function next_month($date, $format) {
    date_create_immutable('first day of next month');
}

function strtotime_partial($date, $format) {
    strtotime('January 1');
}

function strtotime_dynamic($date, $format) {
    strtotime($date);
}

function strtotime_null($date, $format) {
    strtotime($date, null);
}

function strtotime_base($date, $format) {
    strtotime($date, 0);
}

function strtotime_empty($date, $format) {
    strtotime('');
}

function strtotime_fixed($date, $format) {
    strtotime('@0');
}

function partial_format($date, $format) {
    ClockDate::createFromFormat('H:i', '09:00');
}

function date_only_format($date, $format) {
    DateTime::createFromFormat('Y-m-d', '2020-01-01');
}

function escaped_reset($date, $format) {
    date_create_from_format('\\!H:i', '!09:00');
}

function escaped_pipe($date, $format) {
    date_create_immutable_from_format('H:i\\|', '09:00|');
}

function reset_format($date, $format) {
    ClockDate::createFromFormat('!H:i', '09:00');
}

function reset_pipe($date, $format) {
    date_create_from_format('H:i|', '09:00');
}

function complete_format($date, $format) {
    date_create_from_format('Y-m-d H:i:s', '2020-01-01 09:00:00');
}

function timestamp_format($date, $format) {
    DateTime::createFromFormat('U', '0');
}

function dynamic_format($date, $format) {
    DateTime::createFromFormat($format, $date);
}

function dynamic_date($date, $format) {
    date_create_from_format('H:i', $date);
}

function invalid_format_date($date, $format) {
    date_create_from_format('H:i', 'invalid');
}

function named_format($date, $format) {
    ClockDate::createFromFormat(datetime: '09:00', format: 'H:i');
}

function named_base($date, $format) {
    strtotime(baseTimestamp: null, datetime: '+1 day');
}

function case_format($date, $format) {
    \DATETIME::CREATEFROMFORMAT('H:i', '09:00');
}

function lookalike($date, $format) {
    \App\DateTime::createFromFormat('H:i', '09:00');
}

function callable_creation($date, $format) {
    ClockDate::createFromFormat(...);
}

