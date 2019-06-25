<?php
class Find {
	var $_dir;                     // dir to search.
	var $_recursive     = true;    // find recursively
	var $_excludeFilter = array(); // regex.
	var $_includeFilter = array(); // regex include or exclude
	var $_sort;                    // sort function.
	
	// unimplemented.
	var $_type          = 'file';  // file or directory

	function __construct($dir) {
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


