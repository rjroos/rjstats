<?
/* $Id: new.php,v 1.1 2005/03/06 12:40:23 rjroos Exp $ */

error_reporting(E_ALL);

require_once("../rjstats.conf.inc");
require_once("../lib/Find.inc");
require_once("../lib/Tree.inc");
require_once("../lib/NameValue.inc");

$f = new Find(RJSTATS_DATA);
$f->addIncludeFilter("/rrd$/");
$f->setRecursive(true);
$arr = $f->getMatches();

$computers = array();
$groups    = array();
$graphs    = array();

$all = new Tree("all");
foreach ($arr as $file) {
	// $file == something_here/ip/group/graph.rrd.
	$arr = split("/", $file);
	$size = sizeof($arr);
	$ip     = $arr[$size - 3];
	$group  = $arr[$size - 2];
	$graph  = $arr[$size - 1];
	$graph  = preg_replace("/.rrd$/", "", $graph);

	$elemIp = $all->get($ip);
	if ($elemIp == null) {
		$elemIp = new Tree($ip);
		$all->add($elemIp);
		$computers[] = new NameValue($ip, gethostbyaddr($ip));
	}

	$elemGroup = $elemIp->get($group);
	if ($elemGroup == null) {
		$elemGroup = new Tree($group);
		$elemIp->add($elemGroup);
		$groups[] = new NameValue($group, $group);
	}

	$elemGraph = $elemGroup->get($graph);
	if ($elemGraph == null) {
		$elemGraph = new Tree($graph);
		$elemGroup->add($elemGraph);
		$graphs[] = new NameValue($graph, $graph);
	}
}

$timespans = array(
	new NameValue(               3600, "Last hour"),
	new NameValue(          24 * 3600, "Last day"),
	new NameValue(      2 * 24 * 3600, "Last 2 days"),
	new NameValue(  2 * 7 * 24 * 3600, "Last 2 weeks"),
	new NameValue(  3 * 7 * 24 * 3600, "Last 3 weeks"),
	new NameValue(     31 * 24 * 3600, "Last month"),
	new NameValue( 3 * 31 * 24 * 3600, "Last 3 months"),
	new NameValue( 6 * 31 * 24 * 3600, "Last 6 months"),
	new NameValue(    365 * 24 * 3600, "Last year"),
);

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
<script type='text/javascript'>
<?= $all->toJavascript() ?>
</script>
</head>

<body>

<? 
function nameValueSort($a, $b) {
	$sa = $a->getName();
	$sb = $b->getName();
	if ($sa == $sb) {
		return 0;
	}
	return ( $sa < $sb ) ? -1 : 1;
}

function uniqueNameValueList($list) {
	$result = array();
	foreach($list as $elem) {
		$result[$elem->getValue()] = $elem;
	}
	$result2 = array_values($result);
	usort($result2, "nameValueSort");
	return $result2;
}

function selectBox($title, $name, $data) {
	$str = '';
	$str .= "\n<h2>$title</h2>\n";
	$str .= "<select onchange='update()' name='$name"."[]' multiple>\n";
	$data = uniqueNameValueList($data);
	foreach ($data as $o) {
		$value = $o->getValue();
		$string = $o->getName();
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

<form name="form" action="<?= $_SERVER["PHP_SELF"] ?>" method="GET">
<table>
	<tr>
		<td><?= selectBox("Computers", "computers", $computers); ?></td>
		<td><?= selectBox("Groups", "groups", $groups); ?></td>
		<td><?= selectBox("Graphs", "graphs", $graphs); ?></td>
		<td><?= selectBox("Timespans", "timespans", $timespans); ?></td>
		<td><input type='submit'/></td>
	</tr>
</table>
</form>

<hr/>

</body>
</html>
