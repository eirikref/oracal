<?php
/*
 * +----------------------------------------------------------------------+
 * | oracallib.php                                                        |
 * | A wrapper for the Oracle Calendar extension                          |
 * |                                                                      |
 * | This class is not meant to accompany the Oracle Calendar extension   |
 * | in any "official" way.  It's something I wrote to make life easier   |
 * | for myself and I thought I'd share with others using the extension.  |
 * | It takes care of things like coding and encoding data, checking	  |
 * | timestamps and so on.                                                |
 * | Pleas send patches to fix the last function :)                       |
 * +----------------------------------------------------------------------+
 * | Author: Eirik Refsdal <eirikref@pvv.ntnu.no>                         |
 * +----------------------------------------------------------------------+
 *
 *  $Id: oracallib.php,v 1.1.1.1 2004/02/13 04:05:12 eirikref Exp $
 *
 */
require_once "csdk_flags.php";

class calendar
	{
	var $host       = NULL;
	var $username   = NULL;
	var $password   = NULL;
	var $identifier = NULL;
	var $agendas    = array();

	function calendar($host, $username, $password)
		{
		$this->host     = $host;
		$this->username = $username;
		$this->password = $password;
		}
	
	function connect()
		{
		$this->identifier = oracal_connect($this->host, $this->username, $this->password);

		return !empty($this->identifier);
		}

	function disconnect()
	{
		if ($this->identifier && $this->get_agendas())
			$this->close_agendas();
		
		return (($this->identifier) ?
			oracal_disconnect($this->identifier):
			FALSE);
		}
	
	function error()
		{
		$tmp = oracal_error();

		return (strlen($tmp) > 0) ?
			$tmp:
			FALSE;
		}
	
	function get_identifier()
		{
		return $this->identifier;
		}

	function get_capabilities($num = NULL)
		{
		$tmp = array();

		if ($this->identifier)
			$tmp = oracal_get_capabilities($this->identifier);

		if (isset($num) && $num >= 0 && $num <= (sizeof($tmp) - 1))
			return $tmp[$num];
		elseif (is_array($tmp) && sizeof($tmp) > 0)
			return $tmp;
		else
			return FALSE;
		}

	function open_agenda($string)
		{
		$tmp = array();

		if (!isset($this->identifier) || !is_string($string))
			return FALSE;

		$string = utf8_encode($string);

		if (oracal_open_agenda($this->identifier, $string))
			{
			$tmp = oracal_get_agenda_info($this->identifier);
			$this->agendas[$string] = array('type' => utf8_decode($tmp[0]),
							'name' => utf8_decode($tmp[1]),
							'mail' => utf8_decode($tmp[2]),
							);
			return TRUE;
			}
		else
			return FALSE;
		}

	function close_agendas()
		{
		return (!empty($this->identifier) ?
			oracal_close_agendas($this->identifier):
			FALSE);
		}

	function get_agendas()
		{
		return (is_array($this->agendas) && sizeof($this->agendas) > 0) ?
			$this->agendas:
			FALSE;
		}

	function get_daily_events($year, $month, $day, $flags = CSDK_FLAG_NONE)
		{
		if (!not_empty($day, $month, $year))
			return "Please fill out all form data!";

		$date  = date("Ymd", mktime(1,1,1, $month, $day, $year));
		$start = $date . "T000000";
		$end   = $date . "T235959";

		return $this->get_events_by_range($start, $end, $flags);
		}

	function get_events_by_range($dtstart, $dtend, $flags = CSDK_FLAG_NONE)
		{
		$return = FALSE;
		if ($this->check_timestamp($dtstart) && $this->check_timestamp($dtend) && isset($this->identifier))
			{
			$return = oracal_get_events_by_range($this->identifier, $dtstart, $dtend, $flags);

			print oracal_error();

			$return = $this->decode_event_data($return);
			}
		else 
        {
		error_log('OraCal: Invalid timestamp or not connected to server');
        }

		return $return;
		}

	function decode_event_data($string)
		{
		$in_calendar = FALSE;
		$tmparr      = array();
		$return      = NULL;

		if (is_string($string))
			{
			$string = quoted_printable_decode($string);
			$string = utf8_decode($string);
			
			$return = explode("\r\n", $string);
			
			foreach ($return as $line) 
				{
				if ('BEGIN:VCALENDAR' == $line)
					$in_calendar = 1;
				elseif ('END:VCALENDAR' == $line) 
					{
					$in_calendar = NULL;
					$tmparr[]    = $line;
					}
				
				if ($in_calendar)
					$tmparr[] = $line;
				}
			
			$return = implode("\r\n", $tmparr);
			$return .= "\r\n";
			}

		return (isset($return)) ?
			$return:
			FALSE;
		}

	/* Not by any means perfect.  But it's good enough for now. */
	function check_timestamp($ts)
		{
		return (ereg('^[0-9]{8}T[0-9]{6}', $ts) ?
			TRUE:
			FALSE);
		}

	function check_uid_array($uids)
		{
		for ($i = 0; $i < count($uids); $i++)
			if (!is_string($uids[$i]))
				return FALSE;

		return TRUE;
		}
		
	function get_events_by_uid($uid, $rid = NULL, $flags = CSDK_FLAG_NONE)
		{
		$return = NULL;

		if (is_string($uid) && isset($this->identifier))
			{
			$tmp = array($uid);
			$return = oracal_get_events_by_uid($this->identifier, $tmp, $rid, $flags);
			}
		elseif (is_array($uid) && isset($this->identifier) && check_uid_array($uid))
			$return = oracal_get_events_by_uid($this->identifier, $uid, $rid, $flags);

		if (isset($return) && $return )
			return $this->decode_event_data($return);
		else
			{
			error_log('OraCal: Invalid UID or not connected to server');
			return FALSE;
			}
		}

	function delete_events($uid, $datetime)
		{
		if (is_string($uid) && isset($this->identifier))
			$tmp = array($uid);
		elseif (is_array($uid) && isset($this->identifier) && check_uid_array($uid))
			$tmp = $uid;

		if (!isset($tmp))
			{
			error_log('OraCal: Invalid UID or not connected to server');
			return FALSE;
			}
		else
			return (oracal_delete_events($this->identifier, $tmp, CSDK_FLAG_NONE, $datetime) ?
				TRUE:
				FALSE);
		}

	function store_appointment($start, $end, $summary, $location, $attendees,
		$description = "None",
		$class       = "PUBLIC",
		$status      = "CONFIRMED",
		$rrule       = NULL)
		{
		if (!not_empty($start, $end, $summary, $location))
			return "Please fill out all required information!";
			
		$tmp = "BEGIN:VCALENDAR\n" .
			"VERSION:2.0\n" .
			"PRODID://Oracle//CAPI//EN\n" .
			"BEGIN:VEVENT\n" .
			"X-ORACLE-EVENTTYPE:APPOINTMENT\n" .
			"CLASS:$class\n" .
			"DTSTART:$start\n";

		if (!empty($rrule))
			$tmp .= "RRULE:$rrule\n";
			
		$tmp .= "DTEND:$end\n" .
			"SUMMARY:$summary\n";

		if (!empty($description))
			{
			$description = preg_replace("/[\n\r]/","", $description);
			$tmp .= "DESCRIPTION:" . wordwrap($description, 70, "\n ", 1) . "\n";
			}
			
		$tmp .= "STATUS:$status\n" .
			"LOCATION:$location\n";

		foreach ($attendees as $a)
			if (!empty($a))
				$tmp .= "ATTENDEE:MAILTO:$a\n";

		$tmp .= "END:VEVENT\n" .
			"END:VCALENDAR\n";

		print "<pre>$tmp</pre>";

		return $this->store_events($tmp,
			CSDK_FLAG_STORE_CREATE |
			CSDK_FLAG_STREAM_NOT_MIME |
			CSDK_FLAG_STORE_INVITE_SELF);
		}
		
	function modify_appointment($uid, $start, $end, $summary, $location,
		$attendees, $description, $class, $status, $rid)
		{
		if (!not_empty($summary, $location))
			return "Please fill out all required information!";
			
		$tmp = "BEGIN:VCALENDAR\n" .
			"VERSION:2.0\n" .
			"PRODID://Oracle//CAPI//EN\n" .
			"BEGIN:VEVENT\n" .
			"UID:$uid\n" .
			"DTSTART:$start\n" .
			"DTEND:$end\n";
			
		if (!empty($rid))
			$tmp .= "RECURRENCE-ID:$rid\n";

		$tmp .= "X-ORACLE-EVENTTYPE:APPOINTMENT\n" .
			"CLASS:$class\n" .
			"SUMMARY:$summary\n";

		if (!empty($description))
			{
			$description = preg_replace("/[\n\r]/","", $description);
			$tmp .= "DESCRIPTION:" . wordwrap($description, 70, "\n ", 1) . "\n";
			}
			
		$tmp .= "STATUS:$status\n" .
			"LOCATION:$location\n";

		foreach ($attendees as $a)
			if (!empty($a))
				$tmp .= "ATTENDEE:MAILTO:$a\n";

		$tmp .= "END:VEVENT\n" .
			"END:VCALENDAR\n";

		return $this->store_events($tmp,
			CSDK_FLAG_STORE_REPLACE |
			CSDK_FLAG_STREAM_NOT_MIME |
			CSDK_FLAG_STORE_INVITE_SELF);
		}

	function store_events($data, $flags)
		{
		if (is_string($data) && $this->identifier)
			$uids = oracal_store_events($this->identifier, utf8_encode($data),
					   	$flags);

		if ($this->error())
			print "ERROR: " . $this->error();

		return $uids;
		}
	}

class vcalendar_parser
	{
	var $data       = null;
	var $events     = array();
	var $num_events = 0;
	
	function vcalendar_parser($data)
		{
		$this->data = $data;
		}

	function parse()
		{
		$tmp = array();
		$sub = null;
	
		/* Mend lines to "unfold" longer lines
		 * Described in RFC 2445
		 */
		$this->data = preg_replace("/\r\n /", "", $this->data);

		/* Remove additional whitespace in the data and convert to only one
		 * space
		 * Described in RFC 2445
		 */
		$this->data = preg_replace("/[ ]{2,}/", " ", $this->data);

		$tmp = explode("\r\n", $this->data);
	
		foreach ($tmp as $line)
			{
			if ('BEGIN:VEVENT' == trim($line))
				{
				$this->events[$this->num_events++] = new vcalendar_event();
				continue;
				}
			
			if ($this->num_events == 0)
				continue;

			$dcol = strpos($line, ":");
			$dsem = strpos($line, ";");
				
			$dsem ?
				$lp = ($dcol < $dsem ? $dcol : $dsem):
				$lp = $dcol;
				
			$switchstr = substr($line, 0, $lp);
			$sub = substr($line, $lp + 1);

			switch($switchstr)
				{
				case 'ATTENDEE':
					$this->events[$this->num_events - 1]->set_attendees($sub);
					break;
				case 'CATEGORIES':
					$this->events[$this->num_events - 1]->set_category($sub);
					break;
				case 'CLASS':
					$this->events[$this->num_events - 1]->set_class($sub);
					break;
				case 'DESCRIPTION':
				 	$this->events[$this->num_events - 1]->set_description($sub);
					break;
				case 'DTEND':
					$this->events[$this->num_events - 1]->set_dtend($sub);
					break;
				case 'DTSTART':
					$this->events[$this->num_events - 1]->set_dtstart($sub);
					break;
				case 'DTSTAMP':
					$this->events[$this->num_events - 1]->set_dtstamp($sub);
					break;
				case 'LOCATION':
					$this->events[$this->num_events - 1]->set_location($sub);
					break;
				case 'ORGANIZER':
			   		$this->events[$this->num_events - 1]->set_organizer($sub);
					break;
				case 'PRIORITY':	
			   		$this->events[$this->num_events - 1]->set_priority($sub);
					break;
				case 'RESOURCES':
					$this->events[$this->num_events - 1]->set_resources($sub);
					break;
				case 'RECURRENCE-ID':
					$this->events[$this->num_events - 1]->set_rid($sub);
					break;
				case 'STATUS':
					$this->events[$this->num_events - 1]->set_status($sub);
					break;
				case 'SUMMARY':
					$this->events[$this->num_events - 1]->set_summary($sub);
					break;
				case 'TRIGGER':
					$this->events[$this->num_events - 1]->set_trigger($sub);
					break;
				case 'UID':
					$this->events[$this->num_events - 1]->set_uid($sub);
					break;
				case 'VERSION':
					break;
				case 'X-ORACLE-EVENTTYPE':
					$this->events[$this->num_events - 1]->set_type($sub);
					break;
				default:
					#die("No case matched in parse()\n");
				}
		
			unset($sub);
			}

		if (!function_exists("cmp"))
			{
			function cmp($a, $b)
				{
				if ($a->get_dtstart() == $b->get_dtstart())
					return 0;
	
				if ($a->get_dtstart() < $b->get_dtstart()) 
					return -1;
				else
   					return 1;
				}
			}

		usort($this->events, "cmp");
		return $this->events;
		}
	}

class vcalendar_event
	{
	var $uid          = null;
	var $rid          = null;
	var $dtstamp      = null;
	var $dtstart      = null;
	var $dtend        = null;
	var $category     = null;
	var $class        = null;
	var $priority     = null;
	var $status       = null;
	var $description  = null;
	var $summary      = null;
	var $location     = null;
	var $organizer    = array();
	var $attendees    = array();
	var $resources    = array();
	var $trigger      = null;
	var $event_type   = null;

	function vcalendar_event()
		{
		// do nothing
		}

	function set_uid($string)
		{
		if (is_string($string))
			{
			$this->uid = $string;
			return true;
			}
		return false;
		}

	function get_uid()
		{
		return $this->uid;
		}

	function set_rid($string)
		{
		if (is_string($string))
			{
			$this->rid = substr($string, strpos($string, ":") + 1);
			return TRUE;
			}
		return FALSE;
		}

	function get_rid()
		{
		return $this->rid;
		}	

	function set_dtstamp($string)
		{
		if (is_string($string))
			{
			$this->dtstamp = substr($string, strpos($string, ":") + 1);
			return true;
			}
		return false;
		}

	function get_dtstamp()
		{
		return $this->dtstamp;
		}

	function set_dtstart($string)
		{
		if (is_string($string))
			{
			$this->dtstart = substr($string, strpos($string, ":") + 1);
			return true;
			}
		return false;
		}

	function get_dtstart()
		{
		return $this->dtstart;
		}

	function set_dtend($string)
		{
		if (is_string($string))
			{
			$this->dtend = substr($string, strpos($string, ":") + 1);
			return true;
			}
		return false;
		}

	function get_dtend()
		{
		return $this->dtend;
		}

	function set_category($string)
		{
		if (is_string($string))
			{
			$this->category = $string;
			return true;
			}
		return false;
		}

	function get_category()
		{
		return $this->category;
		}

	function set_class($string)
		{
		if (is_string($string))
			{
			$this->class = $string;
			return true;
			}
		return false;
		}

	function get_class()
		{
		return $this->class;
		}

	function set_priority($string)
		{
		if (is_string($string))
			{
			$this->priority = $string;
			return true;
			}
		return false;
		}

	function get_priority()
		{
		return $this->priority;
		}

	function set_status($string)
		{
		if (is_string($string))
			{
			$this->status = $string;
			return true;
			}
		return false;
		}

	function get_status()
		{
		return $this->status;
		}

	function set_description($string)
		{
		if (is_string($string))
			{
			$this->description = $string;
			return true;
			}
		return false;
		}

	function get_description()
		{
		return $this->description;
		}
	
	function set_summary($string)
		{
		if (is_string($string))
			{
			$this->summary = $string;
			return true;
			}
		return false;
		}

	function get_summary()
		{
		return $this->summary;
		}
	
	function set_location($string)
		{
		if (is_string($string))
			{
			$this->location = $string;
			return true;
			}
		return false;
		}

	function get_location()
		{
		return $this->location;
		}
	
	function set_organizer($string)
		{
		$fields = array();
		$tmp    = array();
		$tmpstr = null;

		if (is_string($string))
			{
			$fields = explode(';', $string);

			foreach ($fields as $value)
				{
				$tmp = explode('=', $value, 2);
				
				switch ($tmp[0])
					{
					case 'X-ORACLE-GUID':
						$this->organizer['x-oracle-guid'] = $tmp[1];
						break;
					case 'CN':
						$name = substr($tmp[1], 0, strpos($tmp[1], ':'));
						$this->organizer['name'] = $name;

						$mail = substr($tmp[1], strrpos($tmp[1], ':') + 1);
						$this->organizer['mail'] = $mail;
						break;
					}
				}

			return true;
			}
		
		return false;
		}

	function get_organizer()
		{
		return $this->organizer;
		}
	
	function set_attendees($string)
		{
		$fields        = array();
		$tmp           = array();
		$num_attendees = sizeof($this->attendees);
		$tmpstr        = null;

		if (is_string($string))
			{
			$fields = explode(';', $string);

			foreach ($fields as $value)
				{
				$tmp = explode('=', $value, 2);
				
				switch ($tmp[0])
					{
					case 'PARTSTAT':
						$partstat = substr($tmp[1], 0, strpos($tmp[1], ':'));
						$this->attendees[$num_attendees]['partstat'] = $partstat;
			
						$mail = substr($tmp[1], strrpos($tmp[1], ':') + 1);
						$this->attendees[$num_attendees]['mail'] = $mail;
						break;
					case 'ROLE':
						$this->attendees[$num_attendees]['role'] = $tmp[1];
						break;
					case 'CUTYPE':
						$this->attendees[$num_attendees]['cutype'] = $tmp[1];
						break;			
					case 'CN':
						$this->attendees[$num_attendees]['name'] = $tmp[1];
						break;
					}
				}

			return true;
			}
		
		return false;
		}

	function get_attendees()
		{
		return $this->attendees;
		}
	
	function set_resources($string)
		{
		if (is_string($string))
			{
			$this->resources[] = $string;
			return true;
			}
		return false;
		}

	function get_resources()
		{
		return $this->resources;
		}
	
	function set_trigger($string)
		{
		if (is_string($string))
			{
			$this->trigger = $string;
			return true;
			}
		return false;
		}

	function get_trigger()
		{
		return $this->trigger;
		}

	function set_type($string)
		{
		if (is_string($string))
			{
			$this->event_type = $string;
			return true;
			}
		return false;		
		}

	function get_type()
		{
		return $this->event_type;
		}

	function get_data($id = "id", $hide = FALSE)
		{
		return "<table align='center' class='top' id='$id'" .
			($hide ? " style='display: none'" : "") . ">\n" .
			"<tr><td>UID:</td><td>&nbsp;" .          $this->get_uid() . "</td></tr>\n" .
			"<tr><td>DtStamp:</td><td>&nbsp;" .      $this->get_dtstamp() . "</td></tr>\n" .
			"<tr><td>DtStart:</td><td>&nbsp;" .      $this->get_dtstart() . "</td></tr>\n" .
			"<tr><td>DtEnd:</td><td>&nbsp;" .        $this->get_dtend() . "</td></tr>\n" .
			"<tr><td>RecurrenceID:</td><td>&nbsp;" . $this->get_rid() . "</td></tr>\n" .
			"<tr><td>Category:</td><td>&nbsp;" .     $this->get_category() . "</td></tr>\n" .
			"<tr><td>Class:</td><td>&nbsp;" .        $this->get_class() . "</td></tr>\n" .
			"<tr><td>Priority:</td><td>&nbsp;" .     $this->get_priority() . "</td></tr>\n" .
			"<tr><td>Status:</td><td>&nbsp;" .       $this->get_status() . "</td></tr>\n" .
			"<tr><td>Description:</td><td>&nbsp;" .  $this->get_description() . "</td></tr>\n" .
			"<tr><td>Summary:</td><td>&nbsp;" .      $this->get_summary() . "</td></tr>\n" .
			"<tr><td>Location:</td><td>&nbsp;" .     $this->get_location() . "</td></tr>\n" .
			"<tr><td>Orgainzer:</td><td><pre>" . print_r($this->get_organizer(), TRUE) . "</pre></td></tr>\n" .
			"<tr><td>Attendees:</td><td><pre>" . print_r($this->get_attendees(), TRUE) . "</pre></td></tr>\n" .
			"<tr><td>Resources:</td><td><pre>" . print_r($this->get_resources(), TRUE) . "</pre></td></tr>\n" .
			"<tr><td>Trigger:</td><td>&nbsp;" .      $this->get_trigger() . "</td></tr>\n" .
			"<tr><td>Type:</td><td>&nbsp;" .         $this->get_type() . "</td></tr>\n" . 
			"</table>\n";
		}
	}
?>
