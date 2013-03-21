<?php
### DELETE EVENT
if (isset($_POST['del']) && is_array($_POST['del']))
	{
	print "<script type='text/javascript'>\n" .
		"var conf=confirm(\"Are you sure you want to delete {$_POST['del']}?\");\n" .
		"if (!conf) location.href=\"?\";\n" .
		"</script>";

	for ($i=0; $i < count($_POST['del']); $i++)
		{
		$del[$i] = explode("_", $_POST['del'][$i]);
		$ret[$i] = $cal->delete_events($del[$i][0], $del[$i][1]);
		}

	}
else if (!empty($_GET['uid']))
	{
	print "<script type='text/javascript'>\n" .
		"var conf=confirm(\"Are you sure you want to delete {$_GET['uid']}?\");\n" .
		"if (!conf) location.href=\"?\";\n" .
		"</script>";

	$ret = $cal->delete_events($_GET['uid'], NULL);
	}
else
	{
		die("No UID!");
	}

?>
<script type='text/javascript'>
	location.href="?/";
</script>
