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
 * Observers used in icalsender.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handles user enrollment events (manual, cohort, or group).
     *
     * Sends calendar invites to the newly enrolled user and updates the organizer
     * for all relevant course or group calendar events.
     *
     * @param \core\event\user_enrolment_created|\core\event\cohort_member_added|\core\event\group_member_added $event
     *   The event object containing enrollment details.
     */
    public static function user_enrolled($event) {
        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/local/icalsender/locallib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;

        if (!$enrolleduser = $DB->get_record('user', ['id' => $userid])) {
            debugging("icalsender: no user id found", DEBUG_DEVELOPER);
            return;
        }

        if ($event instanceof \core\event\user_enrolment_created ) {
            // Only select 'course' calendar since only that event needs to be communicated to the enrolled users.
            $sql = 'SELECT * FROM {event} WHERE courseid = :courseid AND eventtype = "course"';
            $context = \context_course::instance($courseid);
            $enrolledusers   = get_enrolled_users($context);
        } else if ($event instanceof \core\event\cohort_member_added) {
            // Only select 'course' calendar since only that event needs to be communicated to the enrolled users.
            $sql = 'SELECT * FROM {event} WHERE courseid = :courseid AND eventtype = "course"';
            $cohortid = $event->objectid;
            $enrolledusers = cohort_get_members($cohortid);
        } else if ( $event instanceof \core\event\group_member_added ) {
            $groupid = $event->objectid;
            $enrolledusers = groups_get_members($groupid);
            // Only select 'group' calendar since this event only impacts group changes.
            $sql = 'SELECT * FROM {event} WHERE courseid = :courseid AND eventtype = "group" AND groupid='.$groupid;

        } else {
            debugging("unsupported event...: ", DEBUG_DEVELOPER);
            return;
        }

        // Run the query.
        $events = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        if (empty($events)) {
            debugging("icalsender: no relevant course or group calendar events found.", DEBUG_DEVELOPER);
            return;
        }

        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        $userenrol[] = $enrolleduser;
        foreach ($events as $eventrecord) {
            $eventid = $eventrecord->id;
            $seqnum = local_icalsender_get_sequence_number($eventid);
            send_mail_with_ics_attachment($eventrecord, $userenrol, $courseurl->out(), false , $seqnum);
            send_mail_with_update_ics_attachment($eventrecord, $enrolledusers, $courseurl->out(), true, $seqnum);
        }
    }


    /**
     * Handles user unenrollment events (manual, cohort, or group).
     *
     * Sends calendar cancellation to the unenrolled user and updates the organizer
     * for all relevant course or group calendar events.
     *
     * @param \core\event\user_enrolment_deleted|\core\event\cohort_member_removed|\core\event\group_member_removed $event
     *   The event object containing unenrollment details.
     */
    public static function user_unenrolled($event) {
        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/local/icalsender/locallib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;

        if (!$unenrolleduser = $DB->get_record('user', ['id' => $userid])) {
            debugging("icalsender: no user id found", DEBUG_DEVELOPER);
            return;
        }

        if ($event instanceof \core\event\user_enrolment_deleted ) {
            // Select all events..both Group and course since user is fully unenrolled from course.
            $sql = 'SELECT * FROM {event} WHERE courseid = :courseid AND eventtype = "course"';
            $context = \context_course::instance($courseid);
            $enrolledusers   = get_enrolled_users($context);
        } else if ($event instanceof \core\event\cohort_member_removed) {
            // Select all events..both Group and course since user is fully unenrolled from course.
            $sql = 'SELECT * FROM {event} WHERE courseid = :courseid AND eventtype = "course"';
            $cohortid = $event->objectid;
            $enrolledusers = cohort_get_members($cohortid);
        } else if ( $event instanceof \core\event\group_member_removed ) {
            debugging("icalsender: group_member_removed", DEBUG_DEVELOPER);
            $groupid = $event->objectid;
            $enrolledusers = groups_get_members($groupid);
            // Only select 'group' calendar since this event only impacts group changes.
            $sql = 'SELECT * FROM {event} WHERE courseid = :courseid AND eventtype = "group" AND groupid='.$groupid;
        } else {
            debugging("icalsender: unsupported event", DEBUG_DEVELOPER);
            return;
        }

        // Run the query.
        $events = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        if (empty($events)) {
            debugging("icalsender: No relevant course or group calendar events found.", DEBUG_DEVELOPER);
            return;
        }

        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        $userunenrol[] = $unenrolleduser;
        foreach ($events as $eventrecord) {
            $eventid = $eventrecord->id;
            $seqnum = local_icalsender_get_sequence_number($eventid);
            // Send delete to unenrolled user.
            send_mail_with_delete_ics_attachment($eventrecord, $userunenrol, $courseurl->out() , false, $seqnum);
            // Send update to organizer.
            send_mail_with_update_ics_attachment($eventrecord, $enrolledusers, $courseurl->out(), true, $seqnum);
        }

    }

    /**
     * Handles the creation of a new calendar event.
     *
     * Notifies all users enrolled in the course or group when a new course or group event is added,
     * and logs the event in the ICS event log.
     *
     * @param \core\event\calendar_event_created $event
     *   The event object containing details of the created calendar event.
     */
    public static function calendar_event_created(\core\event\calendar_event_created $event) {
        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/local/icalsender/locallib.php');

        $eventid = $event->objectid;
        if (!$eventrecord = $DB->get_record('event', ['id' => $eventid])) {
            debugging("icalsender: event id not found in DB", DEBUG_DEVELOPER);
            return;
        }

        switch ($eventrecord->eventtype) {
            case "course":
                $courseid = $eventrecord->courseid;
                if (!$courseid) {
                    debugging("icalsender: course event detected but no courseid", DEBUG_DEVELOPER);
                    return;
                }
                // Get all enrolled users in that course.
                $context = \context_course::instance($courseid);
                $users   = get_enrolled_users($context);
                break;
            case "group":
                $courseid = $eventrecord->courseid;
                $groupid = $eventrecord->groupid;
                if (!$courseid || !$groupid) {
                    debugging("icalsender: missing courseid or groupid");
                    return;
                }
                $users = groups_get_members($groupid);
                if (empty($users)) {
                    debugging("icalsender: no users in group", DEBUG_DEVELOPER);
                    return;
                }
                break;
            case "site":
            case "category":
            case "user":
            default:
                return;
        }
        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        send_mail_with_ics_attachment($eventrecord, $users, $courseurl->out(), true, 0);
        local_icalsender_insert_event($eventid, $eventrecord->name);   // Insert record into ics_event_log.
    }


    /**
     * Handles updates to calendar events.
     *
     * Sends updated calendar invites to all relevant users and updates the sequence number
     * in the ICS event log for the event.
     *
     * @param \core\event\calendar_event_updated $event
     *   The event object containing details of the updated calendar event.
     */
    public static function calendar_event_updated(\core\event\calendar_event_updated $event) {
        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/local/icalsender/locallib.php');

        $eventid = $event->objectid;
        if (!$eventrecord = $DB->get_record('event', ['id' => $eventid])) {
            return;
        }

        switch ($eventrecord->eventtype) {
            case "course":
                $courseid = $eventrecord->courseid;
                if (!$courseid) {
                    debugging("icalsender: course event detected but no courseid", DEBUG_DEVELOPER);
                    return;
                }
                // Get all enrolled users in that course.
                $context = \context_course::instance($courseid);
                $users = get_enrolled_users($context);
                break;
            case "group":
                $courseid = $eventrecord->courseid;
                $groupid = $eventrecord->groupid;
                if (!$courseid || !$groupid) {
                    debugging("icalsender: missing courseid or groupid", DEBUG_DEVELOPER);
                    return;
                }
                $users = groups_get_members($groupid);
                if (empty($users)) {
                    debugging("icalsender: no users in group", DEBUG_DEVELOPER);
                    return;
                }
                break;
            case "site":
            case "category":
            case "user":
            default:
                return;
        }
        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

        if (!$DB->record_exists('ics_event_log', ['eventid' => $eventid])) {
            local_icalsender_insert_event($eventid, $eventrecord->name);
            $seqnum = 0;
        } else {
            $seqnum = local_icalsender_get_sequence_number($eventid) + 1;
        }

        send_mail_with_update_ics_attachment($eventrecord, $users, $courseurl->out(), false, $seqnum);
        local_icalsender_set_sequence_number($eventid, $seqnum);
    }

    /**
     * Handles deletion of calendar events.
     *
     * Sends calendar cancellation to all relevant users and removes the event from the ICS event log.
     *
     * @param \core\event\calendar_event_deleted $event
     *   The event object containing details of the deleted calendar event.
     */
    public static function calendar_event_deleted(\core\event\calendar_event_deleted $event) {
        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/local/icalsender/locallib.php');

        // The $event->objectid is the event's ID in the 'event' table.
        $eventid = $event->objectid;

        // Query the ics_event_log table to check if the eventid matches one of the events we have sent out an ICS invite.
        if ($DB->record_exists('ics_event_log', ['eventid' => $eventid])) {
            $eventname = local_icalsender_get_event_name($eventid);
            $seqnum = local_icalsender_get_sequence_number($eventid) + 1;

            $data = $event->get_data();
            $eventid = $data['objectid']; // The ID of the deleted event.
            $courseid = $data['courseid'];
            $course = $DB->get_record('course', ['id' => $courseid], 'fullname');

            $context = \context_course::instance($courseid);
            $users   = get_enrolled_users($context);
            $eventrecord = new \stdClass();
            $eventrecord->id = $eventid;
            $eventrecord->name = $eventname;
            $eventrecord->description = "Cancelling LMS Event $eventname for $course->fullname";
            $eventrecord->timestart = $event->other['timestart'];
            $eventrecord->timeduration = $event->other['timeduration'];
            $eventrecord->location = '';    // Location information is lost since already removed from DB table.Just set to empty, this is not crucial information.
            $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

            send_mail_with_delete_ics_attachment($eventrecord, $users, $courseurl->out(), true, $seqnum);
            local_icalsender_delete_event($eventid);

        } else {
            debugging("icalsender: event $eventid not found in DB ... ignore calendar delete event", DEBUG_DEVELOPER);
            return;
        }
    }
}
