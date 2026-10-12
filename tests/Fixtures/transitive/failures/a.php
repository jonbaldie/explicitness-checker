<?php
function missingTarget($x) { return unavailable($x); }
function validAction($x) { global $shared; $shared = $x; }
