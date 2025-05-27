<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_icalsender;

/**
 * iCalsender module local lib functions
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Formats a Unix timestamp into an iCalendar (ICS) datetime string (UTC).
 *
 * @param int $timestamp Unix epoch time.
 * @return string Formatted datetime string for ICS (e.g., 20240509T120000Z).
 */
function local_icalsender_format_ics_datetime($timestamp) {
    return gmdate('Ymd\THis\Z', $timestamp);
}


/**
 * Removes all newline and carriage return characters from a string.
 *
 * @param string $text The input text.
 * @return string The text with all line breaks removed.
 */
function local_icalsender_remove_newlines($text) {
    // Remove all types of line breaks.
    $cleanedtext = str_replace(["\r", "\n", "\r\n"], '', $text);
    return $cleanedtext;
}
/**
 * Generates an iCalendar attendee list from an array of user objects.
 *
 * @param array $users Array of user objects.
 * @param string $currentuseremail The email address of the current user (to exclude from attendees).
 * @return string ICS-formatted attendee lines.
 */
function local_icalsender_generate_attendees($users, $currentuseremail) {
    $attendees = '';
    foreach ($users as $user) {
        if ($user->email === $currentuseremail) {
            continue;
        }
        $attendees .= "ATTENDEE;CN={$user->firstname} {$user->lastname};
            ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$user->email}\n";
    }
    return $attendees;
}


/**
 * Generates an iCalendar (ICS) event for a new event invitation.
 *
 * @param object $eventrecord Event data object.
 * @param string $desc Event description.
 * @param array $users Array of user objects.
 * @param object $USER The current user object.
 * @param object $from The sender user object.
 * @param int $seqnumber Sequence number for the event.
 * @param bool $isorganizer Whether the sender is the organizer.
 * @return string ICS file content.
 */
function local_icalsender_generate_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, $isorganizer = true) {
    $dtstamp = local_icalsender_format_ics_datetime(time());
    $dtstart = local_icalsender_format_ics_datetime($eventrecord->timestart);
    $dtend = local_icalsender_format_ics_datetime($eventrecord->timestart + $eventrecord->timeduration);
    $lastmodified = $dtstamp;
    $uid = "{$eventrecord->id}@learn.com";
    $summary = $eventrecord->name;
    $location = $eventrecord->location;

    $organizeremail = $isorganizer ? $from->email : $USER->email;
    $organizername = $isorganizer ? "LMS Organizer" : "{$USER->firstname} {$USER->lastname}";

    $chair = "ATTENDEE;CN={$USER->firstname} {$USER->lastname};
            ROLE=CHAIR;PARTSTAT=ACCEPTED;RSVP=TRUE:mailto:{$USER->email}\n";
    $attendees = local_icalsender_generate_attendees($users, $USER->email);

    return <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Moodle//NONSGML Moodle ICS Generator//EN
METHOD:REQUEST
BEGIN:VEVENT
UID:$uid
DTSTAMP:$dtstamp
DTSTART:$dtstart
DTEND:$dtend
SEQUENCE:$seqnumber
STATUS:CONFIRMED
SUMMARY:$summary
DESCRIPTION:$desc
ORGANIZER;CN=$organizername:mailto:$organizeremail
$chair$attendees
TRANSP:OPAQUE
LOCATION:$location
LAST-MODIFIED:$lastmodified
BEGIN:VALARM
TRIGGER:-PT10M
DESCRIPTION:Reminder for $summary
ACTION:DISPLAY
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;
}


/**
 * Generates an iCalendar (ICS) event for an event update.
 *
 * @param object $eventrecord Event data object.
 * @param string $desc Event description.
 * @param array $users Array of user objects.
 * @param object $USER The current user object.
 * @param object $from The sender user object.
 * @param int $seqnumber Sequence number for the event.
 * @param bool $isorganizer Whether the sender is the organizer.
 * @return string ICS file content for update.
 */
function local_icalsender_generate_update_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, $isorganizer = true) {
    $dtstamp = local_icalsender_format_ics_datetime(time());
    $dtstart = local_icalsender_format_ics_datetime($eventrecord->timestart);
    $dtend = local_icalsender_format_ics_datetime($eventrecord->timestart + $eventrecord->timeduration);
    $lastmodified = $dtstamp;
    $uid = "{$eventrecord->id}@learn.com";
    $summary = $eventrecord->name;
    $location = $eventrecord->location;

    $organizeremail = $isorganizer ? $from->email : $USER->email;
    $organizername = $isorganizer ? "LMS Organizer" : "{$USER->firstname} {$USER->lastname}";

    $chair = "ATTENDEE;CN={$USER->firstname} {$USER->lastname};
            ROLE=CHAIR;PARTSTAT=ACCEPTED;RSVP=TRUE:mailto:{$USER->email}\n";
    $attendees = local_icalsender_generate_attendees($users, $USER->email);

    return <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Moodle//NONSGML Moodle ICS Generator//EN
METHOD:REQUEST
BEGIN:VEVENT
UID:$uid
DTSTAMP:$dtstamp
DTSTART:$dtstart
DTEND:$dtend
SEQUENCE:$seqnumber
STATUS:CONFIRMED
SUMMARY:$summary
DESCRIPTION:$desc
ORGANIZER;CN=$organizername:mailto:$organizeremail
$chair$attendees
TRANSP:OPAQUE
LOCATION:$location
LAST-MODIFIED:$lastmodified
END:VEVENT
END:VCALENDAR
ICS;
}

/**
 * Generates an iCalendar (ICS) event for event cancellation.
 *
 * @param object $eventrecord Event data object.
 * @param string $desc Event description.
 * @param object $USER The current user object.
 * @param string $organizeremail Organizer's email address.
 * @param int $seqnumber Sequence number for the event.
 * @return string ICS file content for cancellation.
 */
function local_icalsender_generate_cancel_ics($eventrecord, $desc, $USER, $organizeremail, $seqnumber) {
    $dtstamp = local_icalsender_format_ics_datetime(time());
    $dtstart = local_icalsender_format_ics_datetime($eventrecord->timestart);
    $dtend = local_icalsender_format_ics_datetime($eventrecord->timestart + $eventrecord->timeduration);
    $lastmodified = $dtstamp;
    $uid = "{$eventrecord->id}@learn.com";
    $summary = $eventrecord->name;
    $location = $eventrecord->location;
    $organizername = "{$USER->firstname} {$USER->lastname}";

    return <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Moodle//NONSGML Moodle ICS Generator//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:$uid
DTSTAMP:$dtstamp
DTSTART:$dtstart
DTEND:$dtend
SEQUENCE:$seqnumber
STATUS:CANCELLED
SUMMARY:$summary
ORGANIZER;CN=$organizername:mailto:$organizeremail
DESCRIPTION:$desc
LOCATION:$location
LAST-MODIFIED:$lastmodified
END:VEVENT
END:VCALENDAR
ICS;
}


/**
 * Sends emails with ICS attachments to event participants and optionally the organizer.
 *
 * @param object $eventrecord Event data object.
 * @param array $users Array of user objects.
 * @param string $url Course or event URL.
 * @param bool $organizeralso Whether to send to the organizer as well.
 * @param int $seqnumber Sequence number for the event.
 * @return void
 */
function local_icalsender_send_mail_with_ics_attachment($eventrecord, $users, $url, $organizeralso, $seqnumber) {
    global $USER;

    $eventdate = userdate($eventrecord->timestart);
    $subject = "New LMS Event {$eventrecord->name} on $eventdate";
    $desc = local_icalsender_remove_newlines($eventrecord->description);
    $from = \core_user::get_noreply_user();
    $mailer = new \local_icalsender\mailer();

    if ($organizeralso == true ) {
        $message   = "Hello {$USER->firstname},<br><br>"
               . "You have an event or training coming up: '{$eventrecord->name}' scheduled on {$eventdate} for course $url.<br>"
               . "Please add this invite to your calendar to stay in the loop.<br><br>"
               . "Regards,<br>Your LMS";

        // Sent to organizer.
        $icsdataorganizer = local_icalsender_generate_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, true);
        $mailer->local_icalsender_send_ics_mail_from_noreply($USER, $subject, $message, $icsdataorganizer);
    }
    foreach ($users as $user) {
        $message   = "Hello {$user->firstname},<br><br>"
        . "You have an event or training coming up: '{$eventrecord->name}' scheduled on {$eventdate} for course $url.<br>"
        . "Please add this invite to your calendar to stay in the loop.<br><br>"
        . "Regards,<br>Your LMS";

        $icsdataattendee  = local_icalsender_generate_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, false);
        if ($USER->email != $user->email ) {   // If mail == USER , skip since that's the organizer.
            $mailer->local_icalsender_send_ics_mail_from_noreply($user, $subject, $message, $icsdataattendee);
        }
    }

    return;
}


/**
 * Sends cancellation emails with ICS attachments to event participants and optionally the organizer.
 *
 * @param object $eventrecord Event data object.
 * @param array $users Array of user objects.
 * @param string $url Course or event URL.
 * @param bool $organizeralso Whether to send to the organizer as well.
 * @param int $seqnumber Sequence number for the event.
 * @return void
 */
function local_icalsender_send_mail_with_delete_ics_attachment($eventrecord, $users, $url, $organizeralso, $seqnumber ) {
    global $USER;

    $subject = "Cancelling LMS event {$eventrecord->name}";
    $desc = local_icalsender_remove_newlines($eventrecord->description);
    $from = \core_user::get_noreply_user();
    $mailer = new \local_icalsender\mailer();

    if ($organizeralso == true ) {
        $message   = "Hello {$USER->firstname},<br><br>"
        . "One of your calendar events has been cancelled: '{$eventrecord->name}' for course $url.<br><br>"
        . "Regards,<br>Your LMS";

        // Delete also for organizer since the complete calendar event is deleted.
        $icsdataorganizer = local_icalsender_generate_cancel_ics($eventrecord, $desc, $USER, $from->email, $seqnumber);
        $mailer->local_icalsender_send_ics_mail_from_noreply($USER, $subject, $message, $icsdataorganizer);
    }

    $icsdataattendee = local_icalsender_generate_cancel_ics($eventrecord, $desc, $USER, $USER->email, $seqnumber);
    foreach ($users as $user) {
        if ($USER->email != $user->email ) {
            $message   = "Hello {$user->firstname},<br><br>"
            . "One of your calendar events has been cancelled: '{$eventrecord->name}' for course $url.<br><br>"
            . "Regards,<br>Your LMS";

            $mailer->local_icalsender_send_ics_mail_from_noreply($user, $subject, $message, $icsdataattendee);
        }
    }

    return;
}


/**
 * Sends update emails with ICS attachments to event participants and/or the organizer.
 *
 * @param object $eventrecord Event data object.
 * @param array $users Array of user objects.
 * @param string $url Course or event URL.
 * @param bool $organizeronly Whether to send only to the organizer.
 * @param int $seqnumber Sequence number for the event.
 * @return void
 */
function local_icalsender_send_mail_with_update_ics_attachment($eventrecord, $users, $url, $organizeronly, $seqnumber) {
    global $USER;

    $eventdate = userdate($eventrecord->timestart);
    $subject = "Update LMS Event {$eventrecord->name} on $eventdate";
    $messageorganizer   = "Hello {$USER->firstname},<br><br>"
               . "Your event or training has been updated: '{$eventrecord->name}' "
               . "scheduled on {$eventdate} for course $url.<br><br>"
               . "Regards,<br>Your LMS";
    $from = \core_user::get_noreply_user();
    $desc = local_icalsender_remove_newlines($eventrecord->description);
    $mailer = new \local_icalsender\mailer();

    $icsdataorganizer = local_icalsender_generate_update_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, true);
    $mailer->local_icalsender_send_ics_mail_from_noreply($USER, $subject, $messageorganizer, $icsdataorganizer);
    if ($organizeronly == false ) {      // Also send update to all other participants.
        $icsdataattendee  = local_icalsender_generate_update_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, false);
        foreach ($users as $user) {
            if ($USER->email != $user->email ) {
                $message = "Hello {$user->firstname},<br><br>"
                . "Your event or training has been updated: '{$eventrecord->name}' "
                . "scheduled on {$eventdate} for course $url.<br><br>"
                . "Regards,<br>Your LMS";

                $mailer->local_icalsender_send_ics_mail_from_noreply($user, $subject, $message, $icsdataattendee);
            }
        }
    }
    return;
}


/**
 * Inserts a new event log entry into the local_icalsender_ics_events table.
 *
 * @param int $eventid Event ID.
 * @param string $eventname Event name.
 * @return void
 */
function local_icalsender_insert_event($eventid, $eventname) {
    global $DB;

    try {
        if ($DB->record_exists('local_icalsender_ics_events', ['eventid' => (int)$eventid])) {
            return;
        }

        $record = new \stdClass();
        $record->eventid = $eventid;
        $record->eventname = $eventname;
        $record->seqnum = 0;
        $record->senttime = time();

        $id = $DB->insert_record('local_icalsender_ics_events', $record);
    } catch (dml_exception $e) {
        debugging("icalsender: Insert of eventid $eventid failed: " . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return;
}

/**
 * Deletes an event log entry from the local_icalsender_ics_events table.
 *
 * @param int $eventid Event ID.
 * @return void
 */
function local_icalsender_delete_event($eventid) {
    global $DB;
    try {
        $DB->delete_records('local_icalsender_ics_events', ['eventid' => $eventid]);
    } catch (dml_exception $e) {
        debugging("icalsender: delete of eventid $eventid failed: " . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return;
}



/**
 * Retrieves the event name from the local_icalsender_ics_events table for a given event ID.
 *
 * @param int $eventid Event ID.
 * @return string|null Event name, or null if not found.
 */
function local_icalsender_get_event_name($eventid) {
    global $DB;

    try {
        $eventname = $DB->get_field('local_icalsender_ics_events', 'eventname', ['eventid' => $eventid], MUST_EXIST);
    } catch (dml_exception $e) {
        debugging("icalsender: retrieval eventname of eventid $eventid failed: " . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return $eventname;
}


/**
 * Retrieves the sequence number from the local_icalsender_ics_events table for a given event ID.
 *
 * @param int $eventid Event ID.
 * @return int|null Sequence number, or null if not found.
 */
function local_icalsender_get_sequence_number($eventid) {
    global $DB;
    try {
        $seqnum = $DB->get_field('local_icalsender_ics_events', 'seqnum', ['eventid' => $eventid], MUST_EXIST);
    } catch (dml_exception $e) {
        debugging("icalsender: retrieval seqnum of eventid $eventid failed: " . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return $seqnum;
}


/**
 * Sets the sequence number for a given event in the local_icalsender_ics_events table.
 *
 * @param int $eventid Event ID.
 * @param int $seqnum Sequence number to set.
 * @return void
 */
function local_icalsender_set_sequence_number($eventid, $seqnum) {
    global $DB;
    try {
        $DB->set_field('local_icalsender_ics_events', 'seqnum', $seqnum, ['eventid' => $eventid]);
    } catch (dml_exception $e) {
        debugging("icalsender: retieval seqnum of eventid $eventid failed: " . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return;
}
