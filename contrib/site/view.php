<?
/* $Id: view.php,v 1.1 2005/03/06 12:40:23 rjroos Exp $ */
error_reporting(E_ALL);
include("../rjstats.conf.inc");

$computer = $_REQUEST["computer"];
$service  = $_REQUEST["service"];

$f = RJSTATS_DATA . "/$computer/php/$service.php";
include($f);

?>
