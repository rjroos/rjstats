<?
/* $Id: index.php,v 1.8 2009/04/09 10:37:56 rjroos Exp $ */
error_reporting(E_ALL);
require("rjstats.conf.inc");

class Find {
	var $_dir;                     // dir to search.
	var $_recursive     = true;    // find recursively
	var $_excludeFilter = array(); // regex.
	var $_includeFilter = array(); // regex include or exclude
	var $_sort;                    // sort function.
	
	// unimplemented.
	var $_type          = 'file';  // file or directory

	function Find($dir) {
		$this->_dir = $dir;
	}

	function addIncludeFilter($regex) {
		$this->_checkRegex($regex);
		$this->_includeFilter[] = $regex;
	}

	function addExcludeFilter($regex) {
		$this->_checkRegex($regex);
		$this->_excludeFilter[] = $regex;
	}

	function setRecursive($bool) {
		if(!is_bool($bool)) {
			die("Not a boolean: '$string'");
		}
		$this->_recursive = $bool;
	}

	function setBaseDir($dir) {
		$this->_dir = $dir;
	}

	function setSort($str) {
		if(!function_exists($str)) {
			die("Call to undefined function $str");
		}
		$this->_sort = $str;
	}

	function safe_clone() {
		if(version_compare("5", phpversion()) == -1) {
			return clone($this);
		}
		return $this;
	}

	function _checkRegex($regex) {
		$firstChar = substr($regex, 0, 1);
		if($firstChar != '/') {
			die("Regex '$regex' must start with '/'!");
		}
		$modifiers = substr($regex, strrpos($regex,'/')+1);
		if(!preg_match("/^[imsxeADSUXu]*$/",$modifiers)) {
			die("Invalid modifiers ($modifiers) for '$regex'!");
		}
	}

	// exclude filters have precedence over include filters.
	// if the include filters size is null we return true so a find without
	// any filters will return all files in a directory, as in unix find.
	
	function _filenameMatchesFilters($filename, $useincludefilters = true) {
		foreach($this->_excludeFilter as $regex) {
			if(preg_match($regex, $filename)) {
				return false;
			}
		}
		if(sizeof($this->_includeFilter)==0) {
			return true;
		}
		if (!$useincludefilters) return true;
		foreach($this->_includeFilter as $regex) {
			if(preg_match($regex, $filename)) {
				return true;
			}
		}
		return false;
	}

	function getMatches() {
		$recurse = $this->_recursive;
		$dir     = $this->_dir;

		$result = array();

		if(!is_dir($dir)) {
			die("Not a directory: : '$dir'");
		}
		
		$dh = opendir( $dir );
		if ( $dh === false ) {
			die("Cannot open $dir");
		}

		if ( substr($dir,-1) == "/" ) {
			$dir = substr($dir, 1, -1);
		}

		/* Set files in an array to limit open file/dir handles */
		$files = array();
		while ($file = readdir($dh)) {
			$files[] = $file;
		}
		closedir($dh);
		
		foreach($files as $file) {
			if ( $file == "." || $file== ".." ) { 
				continue ; 
			}

			$filename = "$dir/$file";

			if ( is_dir( $filename ) ) {
				if ( $recurse ) {
					if ($this->_filenameMatchesFilters($filename, false)) {
						$f = $this->safe_clone();
						$f->setBaseDir($filename);
						$array = $f->getMatches();
						$result = array_merge($result, $array);
					}
				}
			} else {
				if($this->_filenameMatchesFilters($filename)) {
					$result[] =$filename;
				}
			}
		}
		
		if(isset($this->_sort)) {
			usort($result, $this->_sort);
		}
		return $result;
	}
}

$f = new Find(RJSTATS_DATA);
$f->addIncludeFilter("/rrd$/");
$f->setRecursive(true);
$arr = $f->getMatches();

$computers = array();
$services  = array();
$servicegroups = array();
foreach($arr as $file) {
	$tmp = split("/", $file);
	$iSize = sizeof($tmp);
	$computers[] = $tmp[$iSize - 3];
	$group = $tmp[$iSize - 2];
	$sService = $group . "/" . $tmp[$iSize - 1];
	$sService = substr($sService, 0, -4);
	$services[] = $sService;
	if (!in_array($group, $servicegroups)) {
		$servicegroups[] = $group;
	}
}
$computers = array_unique($computers);
$services  = array_unique($services);

function getNiceHost($host) {
	if (!strstr($host, ".")) {
		return $host;
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
usort($computers, "sort_hostname");
sort($services);
sort($servicegroups);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html>

<head>
<title>RJStats graphs.</title>
<link rel="stylesheet" type="text/css" href="stylesheet.css"></link>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script> 
<script type='text/javascript' src='http://jackbliss.co.uk/projects/localstorage/min.jquery.saveit.js'></script>
<script src="http://code.highcharts.com/highcharts.js"></script>    
<script type='text/javascript' src='stats.js'></script>

<script type='text/javascript'>
$(document).ready(function() {
	updateServices();
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

function updateServices_oud() {
	var f = document.forms['form'];
	var aGroups = [];
	var oGroup = f['servicegroups[]'];
	for (var i = 0 ; i < oGroup.options.length ; i++) {
		var oOption = oGroup.options[i];
		if (oOption.selected) {
			aGroups.push(oOption.text);
		}
	}
	
	var oServices = f['services[]'];
	for (var i = 0 ; i < oServices.options.length ; i++) {
		var oOption = oServices.options[i];
		var sDisplay = showService(aGroups, oOption.text) ? "block" : "none";
		oOption.style.display = sDisplay;
	}
}

function updateServices() {
	var f = document.forms['form'];
	var aGroups = [];
	var oGroup = f['servicegroups[]'];
	for (var i = 0 ; i < oGroup.options.length ; i++) {
		var oOption = oGroup.options[i];
		if (oOption.selected) {
			aGroups.push(oOption.text);
		}
	}
	
	var oServices = f['services[]'];
	var servicesToShow = [];
	var servicesToHide = [];
	for (var i = 0 ; i < oServices.options.length ; i++) {
		var oOption = oServices.options[i];
		if (showService(aGroups, oOption.text)) {
			servicesToShow.push(oOption);
		} else {
			servicesToHide.push(oOption);
		}
	}

	var oHiddenServices = f['hiddenServices[]'];
	for (var i = 0; i < oHiddenServices.options.length; i++) {
		var oOption = oHiddenServices.options[i];
		if (showService(aGroups, oOption.text)) {
			servicesToShow.push(oOption);
		} else {
			servicesToHide.push(oOption);
		}
	}

	servicesToShow = servicesToShow.sort(function(oOption1, oOption2){ return oOption1.text.localeCompare(oOption2.text)});
	servicesToHide = servicesToHide.sort(function(oOption1, oOption2){ return oOption1.text.localeCompare(oOption2.text)});

	$(oServices).empty();
	$(servicesToShow).each(function(i, oOption) { $(oServices).append(oOption); });

	$(oHiddenServices).empty();
	$(servicesToHide).each(function(i, oOption) { $(oHiddenServices).append(oOption); });

	if ($(oServices).find("option:selected")) {
		$(oServices).attr("scrollTop", 17 * $(oServices).find("option:selected").attr("index"));
	}

}
function getCharts() {
	<?php
		$timespan = $_REQUEST['timespan'];
		foreach ($_REQUEST['computers'] as $comp) {
			foreach ($_REQUEST['services'] as $serv) { 
				echo "fetchChart('$comp', '$serv', $timespan);";
			}
		}
	?>
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

<body onload="getCharts()">

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
		<td>
			<select name="computers[]" multiple>
			<? foreach($computers as $pc) {
				$selected = '';
				$nice = getNiceHost($pc);
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
			<select name="servicegroups[]" multiple onchange="updateServices()">
			<? foreach($servicegroups as $sg) {
				$selected = '';
				if(@in_array($sg, $_REQUEST['servicegroups'])) {
					$selected = " selected";
				}
			?>
				<option<?= $selected ?>><?= $sg ?></option>
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
			<select name="hiddenServices[]" multiple style="display:none">
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
		<td>
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
				<? $timerepeat = (isset($_REQUEST['timerepeat'])
									? $_REQUEST['timerepeat']
									: 1);
				?>
				<input type='text' name='timerepeat' value='<?=$timerepeat?>'
						size='4' maxlength='3'/>
			</ul>
			<input type='submit' value="GET" />
			<input type='submit' value="POST" onclick="toggleFormMethod()" />
		</td>
	</tr>
</table>
</form>
</div>
<div class="statswrapper">
<?
function doComputer($computer) {
	$timespan = $_REQUEST['timespan'] or 3600*24*31;
	if ($timespan < 0) {
		$timerepeat = (isset($_REQUEST['timerepeat'])
							? $_REQUEST['timerepeat']
							: 1);
		$timespan = -$timespan * $timerepeat;
	}
	$start = time() - $timespan;
	foreach($_REQUEST['services'] as $service) {
		$f = RJSTATS_DATA."/".$computer."/$service.rrd";
		if(file_exists($f)) {
			echo("<h4>" .getNiceHost($computer)." - $service</h4>\n");
			# we willen een default hashing-achtige truuk zodat alle host/service combo's een id hebben zonder / en andere grappen erin
			echo("<div id='".base64_encode($computer.$service)."'></div>");
		}
	}
}

if(isset($_REQUEST['timespan'])) {
	if(isset($_REQUEST["allcomputers"])) {
		foreach($computers as $computer) {
			doComputer($computer);
		}
	} else {
		if (isset($_REQUEST['computers'])) {
			foreach($_REQUEST['computers'] as $computer) {
				doComputer($computer);
			}
		}
	}
}
?>
<p>
<a href='http://rjstats.sourceforge.net/' target='rjstats.sf.net'>rjstats</a> - UNIX monitoring
<p>
<a href="http://validator.w3.org/check?uri=referer"><img
    src="http://www.w3.org/Icons/valid-html401"
    alt="Valid HTML 4.01!" height="31" width="88"></a>
<a href="http://jigsaw.w3.org/css-validator/"><img
  style="border: 0; width: 88px; height: 31px"
    src="http://jigsaw.w3.org/css-validator/images/vcss" 
    alt="Valid CSS!"></a>
</div>
</body>
</html>
