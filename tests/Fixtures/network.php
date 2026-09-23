<?php

function fetch_page(\CurlHandle $handle): string|bool {
    return curl_exec($handle);
}
