<?
/* $Id: index.php,v 1.2 2005/03/06 12:57:45 rjroos Exp $ */

error_reporting(E_ALL);
define("RJSTATS_DATA", "/tmp/rjstats");

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
foreach($arr as $file) {
	$tmp = split("/", $file);
	$iSize = sizeof($tmp);
	$computers[] = $tmp[$iSize - 3];
	$sService = $tmp[$iSize - 2]."/".$tmp[$iSize - 1];
	$sService = substr($sService, 0, -4);
	$services[] = $sService;
}
$computers = array_unique($computers);
$services  = array_unique($services);
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
		$f = RJSTATS_DATA."/".$computer."/php/"."/$service.php";
		echo ($f);
		if(file_exists($f)) {
			echo("<h4>" .gethostbyaddr($computer)." - $service</h4>\n");
			echo("<img src='$computer/$service.php?start=$start' /><br/>\n");
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
