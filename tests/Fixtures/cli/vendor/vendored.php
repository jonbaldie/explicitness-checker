<?php

// Under a vendor/ directory: excluded by default.
function vendoredReadsGet(): mixed
{
    return $_GET['x'];
}
