<?
include("rjstats.conf.inc");
$service  = @$_REQUEST['service'];
$computer = @$_REQUEST['computer'];
$start    = @$_REQUEST['start'];
$f = RJSTATS_DATA."/".$computer."/php/"."/$service.php";
include($f);
?>
