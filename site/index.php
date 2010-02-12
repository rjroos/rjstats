<?
/* $Id: index.php,v 1.11 2010/02/12 15:31:53 rjroos Exp $ */
error_reporting(E_ALL);
require("rjstats.conf.inc");

//class Iterator {
//	var $_arr;
//	var $_map;
//	var $_pointer;
//
//	function Iterator($arr) {
//		$this->_arr = $arr;
//		$this->_pointer = 0;
//	}
//
//	function hasNext() {
//		return $this->_pointer < sizeof($this->_arr);
//	}
//
//	function next() {
//		var_dump($this->_arr);
//		$obj = $this->_arr[$this->_pointer];
//		$this->_pointer = $this->_pointer + 1;
//		return $obj;
//	}
//}

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
function sort_hostname($a, $b) {
	if ($a == $b) {
		return 0;
	}
	$stra = gethostbyaddr($a);
	$strb = gethostbyaddr($b);
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
<style type='text/css'>
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
<script type='text/javascript'>
window.onload = function() {updateServices()}

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
	for (var i = 0 ; i < oServices.options.length ; i++) {
		var oOption = oServices.options[i];
		var sDisplay = showService(aGroups, oOption.text) ? "block" : "none";
		oOption.style.display = sDisplay;
	}
}

function toggleFormMethod() {
	var f = document.forms['form'];
	f.method = "POST";
	return true;
}
</script>
</head>

<body>
<hr>
<p>
<a href="?allcomputers=1&amp;services[]=system/cpu&amp;timespan=<?= 3600 * 24 * 2 ?>">All CPU</a>
<a href="?allcomputers=1&amp;services[]=system/memory&amp;timespan=<?= 3600 * 24 * 2 ?>">All MEM</a>
<a href="mysqlservers/">MySql Activity Reports Kantoor</a>
<a href="userdir/">Userdir stats</a>

<form method='get' action='<?= $_SERVER["PHP_SELF"] ?>' name='form'>

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

<hr>
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
		$f = RJSTATS_DATA."/".$computer."/php/"."/$service.php";
		if(file_exists($f)) {
			echo("<h4>" .gethostbyaddr($computer)." - $service</h4>\n");
			echo("<p><img src='view.php?computer=$computer&amp;service=$service&amp;start=$start' alt='".gethostbyaddr($computer)." - $service' /><br/>\n");
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
<hr>
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
</body>
</html>
