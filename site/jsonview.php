<?
if (isset($_REQUEST['timedelta'])) {
	$delta = $_REQUEST['timedelta'];
} else {
	$delta = 3600;
}
$start = time() - $delta;
require("rjstats.conf.inc");
$service  = @$_REQUEST['service'];
$computer = @$_REQUEST['computer'];
#$start    = @$_REQUEST['start'];
$f = "json/getJson.php";
if (!file_exists($f)) {
	die("No such file or directory: $f\n");
}
include($f);
?>
