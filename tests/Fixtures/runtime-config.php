<?php

function use_utc(): bool {
    return date_default_timezone_set('UTC');
}

function alter_ini(): string|false {
    return ini_alter('display_errors', '0');
}

function restore_handlers(): void {
    restore_error_handler();
    \RESTORE_EXCEPTION_HANDLER();
}
