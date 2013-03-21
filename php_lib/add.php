<?php
if (!empty($_POST['summary']))
	{
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

	$res = $cal->store_appointment($start, $end, $summary, $location, $_POST['attendee'],
		$description, $class, $status, $rrule);

	if (!is_array($res))
		print "$res<br />\n";

	print "<script language='javascript'>location.href=\"?/\";</script>";
	}
else if (!empty($_POST['rawdata']))
	{
	$res = $cal->store_events($_POST['rawdata'], CSDK_FLAG_STORE_CREATE | CSDK_FLAG_STREAM_NOT_MIME | CSDK_FLAG_STORE_INVITE_SELF);
	print "<script language='javascript'>location.href=\"?/\";</script>";
	}
?>

<table cellspacing='1' width='100%'>

<tr>
<td>
	<form action='?/add' method='POST'>

	<table align='center'>

	<tr>
	<td>

	<table cellpadding='2' cellspacing='1' align='center'>

	<tr>
	<td colspan='2'><b>Add Event</b></td>
	</tr>

	<tr>
	<td>Start</td>
	<td>
		<select name="s_month">
			<option value='<?=date("n");?>'><?=date("F");?></option>
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

		<input type="text" name="s_day" value="<?=date("d");?>" size="2" maxlength="2" />
		<input type="text" name="s_year" value="<?=date("Y");?>" size="4" maxlength="4" />
		<span id='dtstart-time'>
		-
		<input type='text' size='2' name='s_hour' maxlength='2' value='<?=date("H"); ?>' />
		<input type='text' size='2' name='s_min' maxlength='2' value='00' />
		</span>
	</td>
	</tr>
	
	<tr id='dtend'>
	<td>Finish</td>
	<td>
		<select name="f_month">
			<option value='<?=date("n");?>'><?=date("F");?></option>
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

		<input type="text" name="f_day" value="<?=date("d");?>" size="2" maxlength="2" />
		<input type="text" name="f_year" value="<?=date("Y");?>" size="4" maxlength="4" />
		-
		<input type='text' size='2' name='f_hour' maxlength='2' value='<?=date("H"); ?>' />
		<input type='text' size='2' name='f_min' maxlength='2' value='00' />
	</td>
	</tr>
		
	<tr>
	<td>RRULE</td>
	<td><input type='text' name='rrule' size='32' /></td>
	</tr>

	<tr>
	<td>Summary</td>
	<td><textarea name='summary' cols='32' rows='2'></textarea></td>
	</tr>

	<tr id='location'>
	<td>Location</td>
	<td><input type='text' name='location' size='32' /></td>
	</tr>

	<tr>
	<td>Class</td>
	<td>
		<input type='radio' name='class' value='PUBLIC' checked='checked' /> Public<br />
		<input type='radio' name='class' value='PRIVATE' /> Private
	</td>
	</tr>

	<tr id='status'>
	<td>Status</td>
	<td>
		<input type='radio' name='status' value='CONFIRMED' checked='checked' /> Confirmed<br />
		<input type='radio' name='status' value='TENTATIVE' /> Tentative
	</td>
	</tr>

	<tr>
	<td>Attendee (email)</td>
	<td><input type='text' name='attendee[]' size='32' /></td>
	</tr>

	<tr>
	<td>Attendee (email)</td>
	<td><input type='text' name='attendee[]' size='32' /></td>
	</tr>

	<tr>
	<td>Attendee (email)</td>
	<td><input type='text' name='attendee[]' size='32' /></td>
	</tr>

	<tr>
	<td>Description</td>
	<td><textarea name='description' cols='32' rows='5'></textarea></td>
	</tr>

	<tr>
	<td colspan='2' align='center'><input type='submit' value='submit' /></td>
	</tr>

	</table>

	</td>
	<td valign='top'>

	<form action='?/add' method='POST'>

	<table cellpadding='2' cellspacing='1' align='center'>

	<tr>
	<td><b>Add Raw iCal Data</b></td>
	</tr>
	
	<tr>
	<td><textarea rows='10' cols='30' name='rawdata' /></textarea></td>
	</tr>

	<tr>
	<td><input type='submit' value='Store' /></td>
	</tr>
	
	</table>

	</form>
	
	</td>
	</tr>

	</table>
</td>
</tr>

</table>

</body>
</html>
