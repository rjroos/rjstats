<?php
error_reporting(E_ALL);

class NameValue {
	var $value;
	var $name;

	function NameValue($value, $name) {
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
?>
