<?php

function use_utc(): bool {
    return date_default_timezone_set('UTC');
}
