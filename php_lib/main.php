<?php
require_once "oracallib.php";


function timeconv($ts)
	{
	$ts = preg_match("#.*T(\d{2})(\d{2})#", $ts, $m);

	return ($m[1] > 12 ? $m[1] - 12: $m[1]) .
		":$m[2] " . 
		($m[1] > 11 ? "PM": "AM");
	}

function days_events(&$cal, $date)
	{
	$events = $cal->get_events_by_range($date . 'T000000', $date . 'T235959', 
					CSDK_FLAG_FETCH_COMBINED |
					CSDK_FLAG_FETCH_LOCALTIMES);
	$vcal   = new vcalendar_parser($events);
	$events = $vcal->parse();
	
	$tmp = NULL;

	foreach ($events as $event) {
		$rid = $event->get_rid();
		
		$tmp .= 
			### DELTE CHECKBOX
			"<input type='checkbox' name='del[]' value='" . urlencode($event->get_uid()) . "_" . urlencode($event->get_rid()) . "' /> " .

			### DATE
			"<b>" . timeconv($event->get_dtstart()) . " - " . timeconv($event->get_dtend()) . "</b><br />\n" .

			### DEBUG
			"[ <a href='#' onClick='changedis(\"" . $event->get_uid() . $event->get_rid() . "\")'>Show / Hide Debug</a> ]\n\n" .
			$event->get_data($event->get_uid() . $event->get_rid(), TRUE) . "\n\n" .

			### EDIT
			"[ <a href='?/edit&edit=" . urlencode($event->get_uid()) . (!empty($rid) ? "&rid=" . urlencode($event->get_rid()): "") . "'>Edit</a> ]\n";

		### DELETE ENTIRE RRULE
		if (!empty($rid))
			$tmp .= "[ <a href='?/del&uid=" . $event->get_uid() . "'>Delete RRULE</a> ]\n";

		### INFORMATION
		$tmp .= "<br /><br />\n\n" .
			"<b>Summary:</b> " .                        $event->get_summary() . "<br />\n" .
			"<b>Location:</b> " .                       $event->get_location() . "<br />\n" .
			"<b>Description:</b><br />\n<blockquote>" . $event->get_description() . "</blockquote>\n";
	}
   	
	return $tmp;
}
?>

<form action='?/del' method='POST'>

<table width='100%' align='center'>

<tr>
<td><a href='?/add'>New Event</a></td>
</tr>

<?php
for ($i = 0; $i < 7; $i++)
	{
	$mktime = mktime(1,1,1,date("m"), date("d") - date("w") + $i, date("Y"));
	$day  = date("l, M jS", $mktime);
	$date = date("Ymd", $mktime);
	print "<tr>\n<td valgin='top'>" .
		"<b>$day:</b>\n\n" .
		"<blockquote>" . days_events($cal, $date, $i) . "</blockquote>\n\n" .
		"\n\n</blockquote>\n</td>\n</tr>\n\n";
	}
?>
<tr>
<td align='center'><input type='submit' value='Delete' />
</tr>

</table>

</form>

