<?php
if (!empty($_POST['summary']))
	{
	if (!isset($_POST['rid']))
		$_POST['rid'] = NULL;
	
	$start = date('Ymd\THis',
		mktime($_POST['s_hour'], $_POST['s_min'], 0,
			$_POST['s_month'], $_POST['s_day'], $_POST['s_year']));
	$end   = date('Ymd\THis',
		mktime($_POST['f_hour'], $_POST['f_min'], 0,
			$_POST['f_month'], $_POST['f_day'], $_POST['f_year']));

	for ($i = 0; $i < count($_POST['attendee']); $i++)
		$_POST['attendee'][$i] = substr($_POST['attendee'][$i], 0, 62);

	$summary     = substr($_POST['summary'], 0, 126);
	$location    = substr($_POST['location'], 0, 126);
	$description = substr($_POST['description'], 0, 32766);
	$class       = substr($_POST['class'], 0, 14);
	$status      = substr($_POST['status'], 0, 14);
	$rrule       = substr($_POST['rrule'], 0, 126);
		
	$res = $cal->modify_appointment($_POST['uid'], $start, $end,
		$summary, $location, $_POST['attendee'], $description, $class, $status, $_POST['rid']);

	if (!is_array($res)) 
	{
		print "$res<br />\n";
	}
	print "<script language='javascript'>location.href=\"?/\";</script>";
	die();
	}
else if (!empty($_GET['edit']))
	{
	if (!isset($_GET['rid']))
		$_GET['rid'] = NULL;

	$events = $cal->get_events_by_uid($_GET['edit'], $_GET['rid'], 
					CSDK_FLAG_FETCH_COMBINED |
					CSDK_FLAG_FETCH_EXPAND_RRULE |
					CSDK_FLAG_FETCH_LOCALTIMES);

	$vcal   = new vcalendar_parser($events);
	$event = $vcal->parse();
	$event = $event[0];

	preg_match("/(\d{2})(\d{2})(\d{2})T(\d{2})(\d{2})/",
		$event->get_dtstart(), $start);
		
	preg_match("/(\d{2})(\d{2})(\d{2})T(\d{2})(\d{2})/", 
		$event->get_dtend(), $end);

	if ($start[2] < 10)
		$start[2] = substr($start[2], 1, 1);
	
	if ($end[2] < 10)
		$end[2] = substr($end[2], 1, 1);

	$attendees = $event->get_attendees();
	}
else
	die("No UID!");
?>

<table cellspacing='1' width='100%'>

<tr>
<td>
	<form action='?/edit' method='POST'>

	<input type='hidden' name='uid' value='<?=$event->get_uid(); ?>' />

	<?php
	if (!empty($_GET['rid']))
		print "<input type='hidden' name='rid' value='{$_GET['rid']}' />";
	?>
	
	<table align='center'>

	<tr>
	<td>

	<table cellpadding='2' cellspacing='1' align='center'>

	<tr>
	<td colspan='2'><b>Modify Event</b></td>
	</tr>

	<tr>
	<td>Start</td>
	<td>
		<select name="s_month">
			<option value='<?=$start[2];?>'><?=date("F", mktime(1,1,1,$start[2],1,2006));?></option>
			<option value='1'>January</option>
			<option value='2'>February</option>
			<option value='3'>March</option>
			<option value='4'>April</option>
			<option value='5'>May</option>
			<option value='6'>June</option>
			<option value='7'>July</option>
			<option value='8'>August</option>
			<option value='9'>September</option>
			<option value='10'>October</option>
			<option value='11'>November</option>
			<option value='12'>December</option>
		</select>

		<input type="text" name="s_day" value="<?=$start[3];?>" size="2" maxlength="2" />
		<input type="text" name="s_year" value="<?=$start[1];?>" size="4" maxlength="4" />
		<span id='dtstart-time'>
		-
		<input type='text' size='2' name='s_hour' maxlength='2' value='<?=$start[4];?>' />
		<input type='text' size='2' name='s_min' maxlength='2' value='<?=$start[5];?>' />
		</span>
	</td>
	</tr>
	
	<tr id='dtend'>
	<td>Finish</td>
	<td>
		<select name="f_month">
			<option value='<?=$end[2];?>'><?=date("F", mktime(1,1,1,$end[2],1,2006));?></option>
			<option value='1'>January</option>
			<option value='2'>February</option>
			<option value='3'>March</option>
			<option value='4'>April</option>
			<option value='5'>May</option>
			<option value='6'>June</option>
			<option value='7'>July</option>
			<option value='8'>August</option>
			<option value='9'>September</option>
			<option value='10'>October</option>
			<option value='11'>November</option>
			<option value='12'>December</option>
		</select>

		<input type="text" name="f_day" value="<?=$end[3];?>" size="2" maxlength="2" />
		<input type="text" name="f_year" value="<?=$end[1];?>" size="4" maxlength="4" />
		-
		<input type='text' size='2' name='f_hour' maxlength='2' value='<?=$end[4];?>' />
		<input type='text' size='2' name='f_min' maxlength='2' value='<?=$end[5];?>' />
	</td>
	</tr>
		
	<tr>
	<td>Summary</td>
	<td><textarea name='summary' cols='32' rows='2'><?=$event->get_summary(); ?></textarea></td>
	</tr>

	<tr id='location'>
	<td>Location</td>
	<td><input type='text' name='location' size='32' value='<?=$event->get_location(); ?>' /></td>
	</tr>

	<tr>
	<td>Class</td>
	<td>
		<input type='radio' name='class' value='PUBLIC' <?php
		if ($event->get_class() == "PUBLIC")
			print "checked='checked' ";
		?>/> Public<br />
		<input type='radio' name='class' value='PRIVATE' <?php
		if ($event->get_class() == "PRIVATE")
			print "checked='checked' ";
		?>/> Private
	</td>
	</tr>

	<tr id='status'>
	<td>Status</td>
	<td>
		<input type='radio' name='status' value='CONFIRMED'<?php
		if ($event->get_status() == "CONFIRMED")
			print "checked='checked' ";
		?>/> Confirmed<br />
		
		<input type='radio' name='status' value='TENTATIVE' <?php
		if ($event->get_status() == "TENTATIVE")
			print "checked='checked' ";
		?>/> Tentative
	</td>
	</tr>

	<tr>
	<td>Attendee (email)</td>
	<td><input type='text' name='attendee[]' size='32' value='<?=$attendees[0]['mail']; ?>'/></td>
	</tr>

	<tr>
	<td>Attendee (email)</td>
	<td><input type='text' name='attendee[]' size='32' value='<?php if (isset($attendees[1])) print $attendees[1]['mail']; ?>'/></td>
	</tr>
	
	<tr>
	<td>Description</td>
	<td><textarea name='description' cols='32' rows='5'><?=$event->get_description(); ?></textarea></td>
	</tr>

	<tr>
	<td colspan='2' align='center'><input type='submit' value='submit' /></td>
	</tr>

	</table>

	</td>
	</tr>

	</table>
</td>
</tr>

</table>

</body>
</html>
