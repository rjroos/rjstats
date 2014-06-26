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
include("json/getJson.php");
?>
