<?php
/*
 * +----------------------------------------------------------------------+
 * | oracallib/example.php                                                |
 * | A tiny example using the calendar extension and helper classes       |
 * +----------------------------------------------------------------------+
 * | Author: Eirik Refsdal <eirikref@pvv.ntnu.no>                         |
 * +----------------------------------------------------------------------+
 *
 *  $Id: example.php,v 1.1.1.1 2004/02/13 04:05:12 eirikref Exp $
 *
 */

require_once('oracallib.php');
require_once('csdk_flags.php');

function not_empty()
	{
	$args = func_get_args();
	
	foreach ($args as $arg)
		if (empty($arg))
			return FALSE;

	return TRUE;
	}

$cal = new calendar('calendar.denison.edu', 'webcal', 'webcal');

if (!$cal->connect())
	die ("Could not connect to the database\n");
	
$cal->open_agenda('webcal');
?>

<html>
<head>
	<title>Oracal</title>
	<meta http-equiv="content-type" content="application/xhtml+xml; charset=utf-8" />
	<script language="javascript">
	<!--
	function gourl(link)
		{
		location.href=link;
		}
	function changedis(handle)
		{
		document.getElementById(handle).style.display != "table" ?
			document.getElementById(handle).style.display="table":
			document.getElementById(handle).style.display="none";
		}
	-->
	</script>
</head>
<body>

<?php
### CONTENT ##
require (eregi("(add|del|edit)", $_SERVER['QUERY_STRING'], $arg) ? strtolower($arg[1]): "main") . ".php";
$cal->close_agendas();
$cal->disconnect();
?>

</body>

</html>
