<?
require("rjstats.conf.inc");
$service  = @$_REQUEST['service'];
$computer = @$_REQUEST['computer'];
$start    = @$_REQUEST['start'];
$f = RJSTATS_DATA."/".$computer."/php/"."/$service.php";
if (!file_exists($f)) {
	die("No such file or directory: $f\n");
}
include($f);
?>
