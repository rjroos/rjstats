<?
/* $Id: new-old.php,v 1.1 2005/03/06 12:40:23 rjroos Exp $ */

error_reporting(E_ALL);

include("../rjstats.conf.inc");
include("../lib/Find.inc");

class TimeSpan {
	var $value;
	var $name;

	function TimeSpan($value, $name) {
		$this->value = $value;
		$this->name  = $name;
	}

	function getValue() {
		return $this->value;
	}
	
	function getName() {
		return $this->name;
	}
}

$TIMESPANS = array(
	new TimeSpan(3600,     "Last hour"),
	new TimeSpan(24*3600,  "Last day"),
	new TimeSpan(2*24*3600, "Last 2 days"),
	new TimeSpan(2*7*24*3600, "Last 2 weeks"),
	new TimeSpan(3*7*24*3600, "Last 3 weeks"),
	new TimeSpan(31*24*3600,  "Last month"),
	new TimeSpan(365*24*3600,  "Last year"),
);

class Computer {

	var $ip;
	var $graphs = array();

	function Computer($ip) {
		$this->ip = $ip;
	}

	function addGraph($graph) {
		$graphs[] = $graph;
	}

	function getValue() {
		return $this->ip;
	}

	function toString() {
		return gethostbyaddr($this->ip);
	}
}

class Graph {
	var $group;

	function Graph($group, $graph) {
		$this->group = $group;
		$this->graph = $graph;
	}

}

class RJStats {
	private $computers = array();

	function RJStats() {
		$c = new Computer("127.0.0.1");
		$c->addGraph(new Graph("mailservers/debian-exim3", "emails"));
		$c->addGraph(new Graph("mailservers/debian-amavis", "virus"));
		$this->computers[] = $c;

		$c = new Computer("192.168.0.1");
		$c->addGraph(new Graph("databases/mysql", "queries"));
		$this->computers[] = $c;
	}

	function getComputers() {
		return $this->computers;
	}

	function getGroups() {
		$arr = array();
		foreach ($this->computers as $c) {
			$arr = array_merge($arr, $c->getGroups());
		}
		return array_unique($arr);
	}
}

$rjstats = new RJStats();
?>

<html>

<head>
<title>RJStats graphs.</title>
<style>
body {
	font-family: helvetica;
}
select {
	height:300px;
	width:200px;
}
h2 {
	font-size:110%;
}
tr {
	vertical-align:top;
}
td {
	border:1px solid #000;
}
</style>
<script type='text/javascript' src='rjstats.js'></script>
</head>

<body>

<? foreach ($rjstats->getComputers() as $comp) { ?>
	<?= $comp->ip ?>
<? } ?>

<? 
function selectBox($title, $name, $data) {
	$str = '';
	$str .= "\n<h2>$title</h2>\n";
	$str .= "<select name='$name' multiple>\n";
	foreach ($data as $o) {
		$value = $o->getValue();
		$string = $o->toString();
		$selected = '';
		$postArr = @$_REQUEST[$name];
		if ($postArr != null) {
			$selected = (in_array($value, $postArr) ? " selected" : "");
		}
		$str .= "\t<option$selected value='$value'>$string</option>\n";
	}
	$str .= "</select>\n";
	return $str;
}
?>

<hr/>

<table>
	<tr>
		<td><?= selectBox("Computers", "computers[]", $rjstats->getComputers()) ?></td>
		<td><?= selectBox("Groups", "groups[]", $rjstats->getGroups()) ?></td>
		<td><?= selectBox("Graphs", "graphs[]", $rjstats->getGraphs()) ?></td>
		<td><?= selectBox("Timespans", "timespans[]", TIMESPANS) ?></td>
		<td><input type='submit'/></td>
	</tr>
</table>
</form>

<hr/>

</body>
</html>
