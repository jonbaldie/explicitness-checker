<?php
namespace Names;
use function rootAction as imported;
function chosen($x) { return $x; }
function takesLocal($x) { return chosen($x); }
function takesImport($x) { return imported($x); }
function usesFallback($x) { return rootAction($x); }
function qualified($x) { return \ROOTACTION($x); }
function namespaceRelative($x) { return namespace\chosen($x); }
