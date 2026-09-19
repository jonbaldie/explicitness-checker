<?php

// An upper-case .PHP extension is still a PHP file.
function nestedReadsPost(): mixed
{
    return $_POST['x'];
}
