<?php
// lead-management/file.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- UNDEFINED VARIABLES ---
echo $x;
echo $notDefined['key'];
echo ${'dynamicVar'}[0];

// --- FILE ERRORS ---
include('missing1.php');
require_once('missing2.php'); // this one will stop if file exists

// --- ARRAY ERRORS ---
$arr = [];
echo $arr['missingKey'];
echo $arr[null];
echo $arr[true];

// --- INVALID OPERATIONS ---
echo 5 / 0;
$array = "string";
echo $array['key']; // treating string as array

// --- INVALID FUNCTION CALLS ---
strlen(); // missing argument
array_merge("oops"); // string instead of array
strpos(123, ['needle']); // wrong type
count(null); // warning

// --- OBJECT ABUSE ---
$std = new stdClass();
echo $std['array']; // object used like array
$std->missingMethod(); // call missing method

// --- BAD FOREACH ---
foreach (42 as $k => $v) { echo $v; }
foreach (null as $n) { echo $n; }

// --- TYPE MISMATCHES ---
$bool = true;
echo $bool + "string";
in_array('value', 123);

// --- UNDEFINED CONSTANTS ---
echo UNDEF_CONST1;
echo UNDEF_CONST2;
echo NOT_DEFINED_YET;

// --- FILE HANDLE ERRORS ---
$fp = fopen('nonexistent-file.txt', 'r');
fwrite($fp, "writing to bad resource");

// --- INVALID JSON ---
json_decode("{bad json");

// --- INVALID FUNCTION RETURNS ---
function broken(): int {
    return "not an int";
}
echo broken();

// --- TRIGGER USER ERRORS ---
trigger_error("User Notice here!", E_USER_NOTICE);
trigger_error("User Warning here!", E_USER_WARNING);

// Keep this LAST or it will halt everything
// trigger_error("User Fatal Error!", E_USER_ERROR); // Uncomment for 1 final kill shot

?>
