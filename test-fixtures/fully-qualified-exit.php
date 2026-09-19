<?php

/*
 * Since PHP 8.4, exit and die are functions, so `\exit()` and `\die()` parse as
 * function calls rather than language constructs. Strict mode must still report
 * them as writing to standard output.
 */

function quits_as_a_function(): void
{
    \exit(1);
}

function dies_as_a_function(): void
{
    \die('bye');
}
