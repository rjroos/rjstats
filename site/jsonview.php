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

function removeSpikes(&$json, $percentage) {

	function trimmed_mean($data, $percentage) {
		$percentage = min(99, max(0, $percentage));
		$data = array_filter($data, function($obj) { return $obj !== NULL; });
		sort($data);
		$offset = floor(count($data) * $percentage / 100 / 2);
		$result = array();
		for ($i = $offset ; $i < count($data) - $offset ; $i++) {
			$result[] = $data[$i];
		}
		return $result;
	}

	function stddev($sample) {
		$mean = array_sum($sample) / count($sample);
		$devs = array();
		foreach ($sample as $key => $num) {
			$devs[$key] = pow($num - $mean, 2);
		}
		return sqrt(array_sum($devs) / (count($devs) - 1));
	}

	$json['meta']['spikes'] = array();

	for ($i = 0 ; $i < count($json['meta']['legend']) ; $i++) {
		$sname = $json['meta']['legend'][$i];
		$json['meta']['spikes'][$sname] = array();
		$orig_sdata = array();
		foreach ($json['data'] as $row) {
			$orig_sdata[] = $row[$i];
		}
		$sdata = trimmed_mean($orig_sdata, $percentage);

		$stddev = stddev($sdata);
		$avg = array_sum($sdata) / max(count($sdata), 1);

//		$json['meta']['spikes'][$sname]['sample'] = $sdata;
//		$json['meta']['spikes'][$sname]['sample_size'] = count($sdata);
//		$json['meta']['spikes'][$sname]['orig'] = $orig_sdata;
//		$json['meta']['spikes'][$sname]['orig_size'] = count($orig_sdata);

		$json['meta']['spikes'][$sname]['avg'] = $avg;
		$json['meta']['spikes'][$sname]['stddev'] = $stddev;
		$json['meta']['spikes'][$sname]['removed'] = array();

		foreach ($json['data'] as &$row) {
			$val = $row[$i];
			if ($val == null) {
				continue;
			}
			if ($val >= ($avg - 10 * $stddev) && $val <= ($avg + 10 * $stddev)) {
				continue;
			}
			$row[$i] = null;
			$json['meta']['spikes'][$sname]['removed'][] = $val;
		}
	}
}

$start = $_REQUEST["start"];
$service  = @$_REQUEST['service'];
$computer = @$_REQUEST['computer'];
$spike_detect = (int) @$_REQUEST['spike_detect'];

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

if ($spike_detect > 0) {
	removeSpikes($obj, $spike_detect);
}

header('Content-type: application/json');
echo json_encode($obj);
?>
