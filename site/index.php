<?
/* $Id: index.php,v 1.8 2009/04/09 10:37:56 rjroos Exp $ */
error_reporting(E_ALL);
require_once("rjstats.conf.inc");
require_once("lib/Find.php");

function param($name, $def) {
	if (isset($_REQUEST[$name])) {
		return $_REQUEST[$name];
	}
	return $def;
}

$f = new Find(RJSTATS_DATA);
$f->addIncludeFilter("/rrd$/");
$f->setRecursive(true);
$arr = $f->getMatches();

$computers = array();
$services  = array();
foreach($arr as $file) {
	$tmp = explode("/", $file);
	$iSize = sizeof($tmp);
	$computers[] = $tmp[$iSize - 3];
	$group = $tmp[$iSize - 2];
	$sService = $group . "/" . $tmp[$iSize - 1];
	$sService = substr($sService, 0, -4);
	$services[] = $sService;
}
$computers = array_unique($computers);
$services  = array_unique($services);

$HOSTNAMES = array();
if (file_exists("/etc/rjstats/hosts.txt")) {
	$f = fopen("/etc/rjstats/hosts.txt", "r");
	if ($f) {
		while (! feof($f)) {
			$line = fgets($f);
			if (preg_match("/^((?:\d+.){3}.\d+)\s+(\S+)/", $line, $m)) {
				$HOSTNAMES[$m[1]] = $m[2];
			}
		}
	}
	fclose($f);
}

function getNiceHost($host) {
	if (!strstr($host, ".")) {
		return $host;
	}
	global $HOSTNAMES;
	if (isset($HOSTNAMES[$host])) {
		return $HOSTNAMES[$host];
	}
	return gethostbyaddr($host);
}

function sort_hostname($a, $b) {
	if ($a == $b) {
		return 0;
	}
	$stra = getNiceHost($a);
	$strb = getNiceHost($b);
	return strnatcmp($stra, $strb);
}

function getStarttime() {
	$timespan = $_REQUEST['timespan'] or 3600*24*31;
	if ($timespan < 0) {
		$timerepeat = param("timerepeat", 1);
		$timespan = -$timespan * $timerepeat;
	}
	$start = time() - $timespan;
	return $start;
}

usort($computers, "sort_hostname");
sort($services);
?>
<!DOCTYPE html>

<html>

<head>
<title>RJStats graphs.</title>
<link rel="stylesheet" type="text/css" href="stylesheet.css">
<script src="js/jquery-2.1.1.min.js"></script>
<script src='js/min.jquery.saveit.js'></script>
<script src="js/highcharts.js"></script>
<script src='stats.js'></script>

<script type='text/javascript'>
$(document).ready(function() {
	$('#savedsearches').loadit({def : 'Geen.'});
});

function showService(aGroupsSelected, sService) {
	if (aGroupsSelected.length == 0) {
		return true;
	}
	var sGroup = sService.split("/")[0];
	for (var i = 0 ; i < aGroupsSelected.length ; i++) {
		var s = aGroupsSelected[i];
		if (sGroup == s) {
			return true;
		}
	}
	return false;
}

function toggleFormMethod() {
	var f = document.forms['form'];
	f.method = "POST";
	return true;
}

function saveSearch() {
	var s = prompt("Name?");
	$('#savedsearches').append(
			"<li>" +
			"<a href='" + document.location + "'>" + s + "</a>" +
			"&nbsp;&nbsp;&nbsp;" +
			"<a class='small' href='#' onclick='removeSearch(this); return false;'>remove</a>" +
			"</li>")
	$('#savedsearches').saveit();
}

function clearAllSaved() {
	if (!confirm("Clear all?")) {
		return;
	}
	$('#savedsearches').html('');
	$('#savedsearches').saveit();
}

function removeSearch(obj) {
	$(obj.parentNode).remove();
	$('#savedsearches').saveit();
}
</script>
</head>

<body>

<div class="menuwrapper">
<div class='savedsearchbox'>
<div class="header">Saved searches (localStorage! not permanent).
<a href="#" onclick="saveSearch(); return false;">Save this search</a>
<a class="small" href="#" onclick="clearAllSaved(); return false;">Clear</a>
</div>
<ul id="savedsearches">
</ul>
</div>

<form method='get' action='<?= $_SERVER["PHP_SELF"] ?>' name='form'>

<table>
	<tr>
		<td style='width:20%'>
			<select name="computers[]" multiple>
			<? foreach($computers as $pc) {
				$selected = '';
				$nice = getNiceHost($pc);
				$pcs = @$_REQUEST['computers'];
				if (isset($pcs) && in_array($pc, $pcs)) {
					$selected = " selected";
				}
			?>
				<option<?= $selected ?> value='<?= $pc ?>'><?= $nice ?></option>
			<? } ?>
			</select>
		</td>

		<td style='width:50%'>
			<input type='text' name='filter-services' placeholder='Filter'>
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
	$res = "<input type='radio' $selected name='timespan' value='$var' "
			."id='time_$var'/><label for='time_$var'>$lbl</label>";
	return $res;
}

?>
		<td style='width:15%'>
			<ul>
				<li><?= radio(3600,        "Last hour") ?></li>
				<li><?= radio(3600*24,     "Last day") ?></li>
				<li><?= radio(3600*24*3,   "Last 3 days") ?></li>
				<li><?= radio(3600*24*14,  "Last 2 weeks") ?></li>
				<li><?= radio(3600*24*21,  "Last 3 weeks") ?></li>
				<li><?= radio(3600*24*31,  "Last month") ?></li>
				<li><?= radio(3600*24*365, "Last year") ?></li>
				<br/>
				<li><?= radio(-3600, "Last hours") ?></li>
				<li><?= radio(-3600*24, "Last days") ?></li>
				<li><?= radio(-3600*24*7, "Last weeks") ?></li>
				<? $timerepeat = param("timerepeat", 1); ?>
				<input type='text' name='timerepeat' value='<?=$timerepeat?>' size='4' maxlength='3'/>
			</ul>
			<input type='submit' value="GET" />
			<input type='submit' value="POST" onclick="toggleFormMethod()" />
		</td>
		<td style='width:15%'>
			<h3>Options</h3>
			<label>
				Percentage for trimmed mean spike detection
				<input type='text' name='spike_detect' value='<?= param("spike_detect", 20) ?>' />
			</label>
			<hr/>
			<label for="oldstyle">Legacy rrdtool graphs</label><input type="checkbox" name="oldstyle" id="oldstyle" <?php if (@$_REQUEST['oldstyle'] == "on") {echo 'checked';} ?> />
		</td>
	</tr>
</table>
</form>
</div>
<div class="statswrapper">
<?
function doComputer($computer) {
	$start = getStarttime();
	foreach (param("services", array()) as $service) {
		$f = RJSTATS_DATA."/".$computer."/$service.rrd";
		if (! file_exists($f)) {
			continue;
		}

		echo("<h4>" .getNiceHost($computer)." - $service</h4>\n");
		if (@$_REQUEST['oldstyle'] == "on") {
			$url = "view.php?computer=$computer&amp;service=$service&amp;start=$start";
			echo("<p><img src='$url' alt='".getNiceHost($computer)." - $service' /><br/>\n");
		} else {
			printf("<div data-computer='%1\$s' data-service='%2\$s' data-starttime='%3\$s' class='rjchart'>%1\$s - %2\$s</div>",
					htmlentities($computer), htmlentities($service), $start);
		}
	}
}

if(isset($_REQUEST['timespan'])) {
	foreach (param("computers", array()) as $computer) {
		doComputer($computer);
	}
}
?>
<p>
<a href='http://rjstats.sourceforge.net/' target='_blank'>rjstats</a> - UNIX monitoring
<p>
</div>
</body>
</html>
