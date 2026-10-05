<?php

function parse_options(): array|false {
    return getopt('v');
}

function fetch_headers(string $url): array|false {
    return get_headers($url);
}

function connect_ftp(string $host): \FTP\Connection|false {
    return ftp_connect($host);
}

function run_odbc(\Odbc\Connection $connection): \Odbc\Result|false {
    return odbc_exec($connection, 'SELECT 1');
}

function open_socket(): \Socket|false {
    return socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
}

function extend_time_limit(): bool {
    return set_time_limit(60);
}

function prompt(): string|false {
    return readline('> ');
}

function request_headers(): array {
    return getallheaders();
}

function reverse_lookup(string $ip): string|false {
    return gethostbyaddr($ip);
}

function has_mail_exchanger(string $host): bool {
    return checkdnsrr($host);
}

function keep_running(): int {
    return ignore_user_abort(true);
}
