<?php
require_once 'dbConfig.php';
require_once 'models.php';

$sheetId = '1jAxVlt4_tM1s2db22RbBobyxi6Wqla2kHb6UTRZDEJA';
$gid = '1410707098';
$url = "https://docs.google.com/spreadsheets/d/$sheetId/gviz/tq?tqx=out:csv&gid=$gid";

mergeGoogleSheetToDB($pdo, $url);

header("Location: ../../accreditation.php"); // Or wherever your main page is
exit;