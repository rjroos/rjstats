<?
/* $Id: index.php,v 1.1 2005/03/06 12:40:23 rjroos Exp $ */

error_reporting(E_ALL);

include("../rjstats.conf.inc");
include("../lib/Find.inc");

$f = new Find(RJSTATS_DATA);
$f->addIncludeFilter("/rrd$/");
$f->setRecursive(true);
$arr = $f->getMatches();

$computers = array();
$services  = array();
foreach($arr as $file) {
	$tmp = split("/", $file);
	$computers[] = $tmp[sizeof($tmp) - 3];
	$group = $tmp[sizeof($tmp) - 2];
	$graph = $tmp[sizeof($tmp) - 1];
	$services[] = $group . "/" . substr($graph, 0, -4);
}
$computers = array_unique($computers);
$services = array_unique($services);
function sort_hostname($a, $b) {
	if ($a == $b) {
		return 0;
	}
	$stra = gethostbyaddr($a);
	$strb = gethostbyaddr($b);
	return ($stra < $strb) ? -1 : 1;
}
usort($computers, "sort_hostname");
sort($services);
?>
<html>

<head>
<title>RJStats graphs.</title>
<style>
select {
	height:300px;
}
li {
	list-style:none;
}
ul {
	padding-left:0px;
}
tr {
	vertical-align:top;
}
td {
	border:1px solid #000;
}
</style>
</head>

<body>

<hr/>

<a href="?allcomputers=1&services[]=system/cpu&timespan=<?= 3600 * 24 * 2 ?>">All CPU</a>
<a href="?allcomputers=1&services[]=system/memory&timespan=<?= 3600 * 24 * 2 ?>">All MEM</a>
<form method='get' action='<?= $_SERVER["PHP_SELF"] ?>'>

<table>
	<tr>
		<td>
			<select name="computers[]" multiple>
			<? foreach($computers as $pc) {
				$selected = '';
				$nice = gethostbyaddr($pc);
				$pcs = @$_REQUEST['computers'];
				$all = @$_REQUEST['allcomputers'];
				if ($all || (isset($pcs) && in_array($pc, $pcs))) {
					$selected = " selected";
				}
			?>
				<option<?= $selected ?> value='<?= $pc ?>'><?= $nice ?></option>
			<? } ?>
			</select>
		</td>

		<td>
			<select name="services[]" multiple>
			<? foreach($services as $s) {
				$selected = '';
				if(@in_array($s, $_REQUEST['services'])) {
					$selected = " selected";
				}
			?>
				<option<?= $selected ?>><?= $s ?></option>
			<? } ?>
			</select>
		</td>

<?
function radio($var, $lbl) {
	$selected = '';
	if(!isset($_REQUEST['timespan'])) {
		if($var == 3600*24*14) {
			$selected = 'checked';
		}
	} else {
		if($_REQUEST['timespan'] == $var) {
			$selected = 'checked';
		}
	}
	return "<input type='radio' $selected name='timespan' value='$var' id='$var'/><label for='$var'>$lbl</label></input>";
}
?>
		<td>
			<ul>
				<li><?= radio(3600,        "Last hour") ?></li>
				<li><?= radio(3600*24,     "Last day") ?></li>
				<li><?= radio(3600*24*3,   "Last 3 days") ?></li>
				<li><?= radio(3600*24*14,  "Last 2 weeks") ?></li>
				<li><?= radio(3600*24*21,  "Last 3 weeks") ?></li>
				<li><?= radio(3600*24*31,  "Last month") ?></li>
				<li><?= radio(3600*24*365, "Last year") ?></li>
			</ul>
			<input type='submit'/>
		</td>
	</tr>
</table>
</form>

<hr/>
<?
function doComputer($computer) {
	$timespan = $_REQUEST['timespan'] or 3600*24*31;
	$start = time() - $timespan;
	foreach($_REQUEST['services'] as $service) {
		$f = RJSTATS_DATA . "/$computer/php/$service.php";
		if (file_exists($f)) {
			echo("<h4>" .gethostbyaddr($computer)." - $service</h4>\n");
			echo("<img src='view.php?computer=$computer&service=$service&start=$start'\n");
		} else {
			echo("No graph $f<br/>");
		}
	}
}

if(isset($_REQUEST['timespan'])) {
	if(isset($_REQUEST["allcomputers"])) {
		foreach($computers as $computer) {
			doComputer($computer);
		}
	} else {
		foreach($_REQUEST['computers'] as $computer) {
			doComputer($computer);
		}
	}
}
?>

</body>
</html>
