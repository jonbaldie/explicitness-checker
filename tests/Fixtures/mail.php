<?php

function send_welcome(string $to): bool {
    return mail($to, 'Welcome', 'Hello');
}
