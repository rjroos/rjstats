<?php
error_reporting(E_ALL);

class Tree {
	var $name ;
	var $childs = array();

	function Tree($name) {
		$this->name = $name;
	}

	function add(&$obj) {
		if (get_class($obj) != "Tree") {
			die("Not a Tree: $obj");
		}
		$this->childs[$obj->name] = $obj;
		return $obj;
	}

	function get(&$name) {
		$elem = @$this->childs[$name];
		return $elem;
	}

	function toJavascript() {
		$name = $this->name;
		$str = "var $name;\n";
		$this->_traverse($str, $name);
		return $str;
	}

	function _traverse(&$str, $prefix) {
		$name = $this->name;
		if (sizeof($this->childs) == 0) {
			$str .= "$prefix = 1;\n";
		} else {
			$str .= "$prefix = new Array();\n";
			foreach ($this->childs as $child) {
				$child->_traverse($str, $prefix . "['" . $child->name . "']");
			}
		}
	}
}
?>
