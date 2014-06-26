<?php
require_once("rjstats.conf.inc");
require_once("lib/Services_JSON.php");

function error($str) {
	echo json_encode(array("error" => $str));
	exit();
}

function rj_exec($cmd) {
	exec($cmd . " 2>&1", $output, $exitCode);
	if ($exitCode !== 0) {
		error(sprintf("exit=%s, cmd=%s, output=%s", $exitCode, $cmd, implode("\n", $output)));
	}
	return $output;
}

function getXportDefs($rrdfile) {
	$output = rj_exec("rrdtool info $rrdfile");

	$datasources = array();
	foreach ($output as $line) {
		$regex = "/ds\[(.*?)\]\.(.*?)\s*=\s*(.*)/";
		if (! preg_match($regex, $line, $matches)) {
			continue;
		}
		$dsname = $matches[1];
		$dskey = $matches[2];
		$dsval = $matches[3];
		if (! isset($datasources[$dsname])) {
			$datasources[$dsname] = array();
		}
		$datasources[$dsname][$dskey] = $dsval;
	}

	$rrdtoolXportDefs = array();
	foreach ($datasources as $dsname => $hash) {
		$rrdtoolXportDefs[] = sprintf("DEF:%s=%s:%s:AVERAGE", $dsname, $rrdfile, $dsname);
		$rrdtoolXportDefs[] = sprintf("XPORT:%s:%s", $dsname, $dsname);
	}
	return implode(" ", $rrdtoolXportDefs);
}

function isStackedGraph($rrdfile) {
	# Naast de .rrd worden er door de rjstats-ontvanger daemons ook .php files
	# geschreven met het commando om 1 plaatje te doen.
	# In die .php staan zaken als welke kleur, welk lijntype, max en min, etc
	# die je niet met rrdtool info uit de grafiek kan halen.
	# De kleuren boeien niet zo momenteel, en met deze uberhack weten we of de
	# grafiek zou moeten stacken of niet.
	#
	# Een toffe oplossing zou zijn die metadata in een .dbm bestand (of iets in
	# die geest) te zetten zodat het uit te lezen is. Maar dat moet dan wel in de
	# java daemon en de perl daemon gebeuren.

	$parts = explode("/", $rrdfile);
	$path = array_slice($parts, 0, 4);
	$path[] = "php";
	$path = array_merge($path, array_slice($parts, 4));
	$phpFile = str_replace(".rrd", ".php", implode("/", $path));
	if (! file_exists($phpFile)) {
		error("File does not exist: $phpFile");
	}
	return strpos(file_get_contents($phpFile), "'STACK:") !== FALSE;
}

$start = $_REQUEST["start"];
$service  = @$_REQUEST['service'];
$computer = @$_REQUEST['computer'];

if(!is_numeric($start)) {
	$start = time() - 24 * 3600 * 3;
}

$rrdfile = RJSTATS_DATA . "/$computer/$service.rrd";

if (! file_exists($rrdfile)) {
	die("No such php file: $rrdfile");
}

$command = array();
$command[] = 'rrdtool';
$command[] = 'xport';
$command[] = '--json';
$command[] = '--start '.$start;
$command[] = getXportDefs($rrdfile);
$output = rj_exec(implode(" ", $command));

$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
$obj = $json->decode(implode(" ", $output));
$obj['meta']['stacked'] = isStackedGraph($rrdfile);

header('Content-type: application/json');
echo json_encode($obj);
?>
