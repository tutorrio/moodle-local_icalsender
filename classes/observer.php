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
class observer
{
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

        if ($event instanceof \core\event\user_enrolment_created) {
            $context = \context_course::instance($courseid);
            $enrolledusers   = get_enrolled_users($context);
            // Only select 'course' calendar since only that event needs to be communicated to the enrolled users.
            $sql = 'SELECT  *
                    FROM    {event}
                    WHERE   courseid = :courseid
                            AND eventtype = "course"';
            $events = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        } else if ($event instanceof \core\event\cohort_member_added) {
            $cohortid = $event->objectid;
            $enrolledusers = cohort_get_members($cohortid);
            // Only select 'course' calendar since only that event needs to be communicated to the enrolled users.
            $sql = 'SELECT  *
                    FROM    {event}
                    WHERE   courseid = :courseid
                            AND eventtype = "course"';
            $events = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        } else if ($event instanceof \core\event\group_member_added) {
            $groupid = $event->objectid;
            $enrolledusers = groups_get_members($groupid);
            // Only select 'group' calendar since this event only impacts group changes.
            $sql = 'SELECT  *
                    FROM    {event}
                    WHERE   courseid = :courseid
                            AND eventtype = "group"
                            AND groupid = :groupid';
            $events = $DB->get_records_sql($sql, ['courseid' => $courseid, 'groupid' => $groupid]);
        } else {
            debugging("unsupported event...: ", DEBUG_DEVELOPER);
            return;
        }

        // Check if SQL query returned calendar events.
        if (empty($events)) {
            return;
        }

        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        $userenrol[] = $enrolleduser;
        foreach ($events as $eventrecord) {
            if ($eventrecord->timestart + $eventrecord->timeduration < time()) { // Check event is in the past.
                continue;
            }
            if (event_delivery::uses_api()) {
                event_delivery::event_updated($eventrecord, $courseurl->out(false), $enrolledusers);
                continue;
            }
            $eventid = $eventrecord->id;
            $seqnum = local_icalsender_get_sequence_number($eventid);
            local_icalsender_send_mail_with_ics_attachment($eventrecord, $userenrol, $courseurl->out(), false, $seqnum);
            local_icalsender_send_mail_with_update_ics_attachment($eventrecord, $enrolledusers, $courseurl->out(), true, $seqnum);
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

        if ($event instanceof \core\event\user_enrolment_deleted) {
            $context = \context_course::instance($courseid);
            $enrolledusers   = get_enrolled_users($context);
            // Select all events..both Group and course since user is fully unenrolled from course.
            $sql = 'SELECT  *
                    FROM    {event}
                    WHERE   courseid = :courseid
                            AND eventtype = "course"';
            $events = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        } else if ($event instanceof \core\event\cohort_member_removed) {
            $cohortid = $event->objectid;
            $enrolledusers = cohort_get_members($cohortid);
            // Select all events..both Group and course since user is fully unenrolled from course.
            $sql = 'SELECT  *
                    FROM    {event}
                    WHERE   courseid = :courseid
                            AND eventtype = "course"';
            $events = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        } else if ($event instanceof \core\event\group_member_removed) {
            $groupid = $event->objectid;
            $enrolledusers = groups_get_members($groupid);
            // Only select 'group' calendar since this event only impacts group changes.
            $sql = 'SELECT  *
                    FROM    {event}
                    WHERE   courseid = :courseid
                            AND eventtype = "group"
                            AND groupid = :groupid';
            $events = $DB->get_records_sql($sql, ['courseid' => $courseid, 'groupid' => $groupid]);
        } else {
            debugging("icalsender: unsupported event", DEBUG_DEVELOPER);
            return;
        }

        // Check if SQL query returned any calendar events.
        if (empty($events)) {
            return;
        }

        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        $userunenrol[] = $unenrolleduser;
        foreach ($events as $eventrecord) {
            // Check event is in the past.
            if ($eventrecord->timestart + $eventrecord->timeduration < time()) {
                continue;  // Event is in the past, skip it.
            }
            if (event_delivery::uses_api()) {
                event_delivery::event_updated($eventrecord, $courseurl->out(false), $enrolledusers);
                continue;
            }
            $eventid = $eventrecord->id;
            $seqnum = local_icalsender_get_sequence_number($eventid);
            // Send delete to unenrolled user.
            local_icalsender_send_mail_with_delete_ics_attachment($eventrecord, $userunenrol, $courseurl->out(), false, $seqnum);
            // Send update to organizer.
            local_icalsender_send_mail_with_update_ics_attachment($eventrecord, $enrolledusers, $courseurl->out(), true, $seqnum);
        }
    }

    /**
     * Handles student self-confirmation of an Attendance session.
     *
     * The Attendance plugin is optional. Moodle only fires this event when
     * mod_attendance is installed, so this observer deliberately avoids a hard
     * type hint on the Attendance event class.
     *
     * @param \core\event\base $event Attendance event containing session details.
     */
    public static function attendance_taken_by_student(\core\event\base $event) {
        self::handle_attendance_taken($event);
    }

    /**
     * Handles staff recording an Attendance session.
     *
     * The Attendance plugin is optional. Moodle only fires this event when
     * mod_attendance is installed, so this observer deliberately avoids a hard
     * type hint on the Attendance event class.
     *
     * @param \core\event\base $event Attendance event containing session details.
     */
    public static function attendance_taken(\core\event\base $event) {
        self::handle_attendance_taken($event);
    }

    /**
     * Handles Attendance events using the same course/group attendee model as Moodle calendar events.
     *
     * @param \core\event\base $event Attendance event containing session details.
     * @return void
     */
    private static function handle_attendance_taken(\core\event\base $event): void {
        global $DB;
        global $CFG;
        require_once($CFG->dirroot . '/local/icalsender/locallib.php');

        $sessionid = (int)($event->other['sessionid'] ?? 0);
        $attendanceid = (int)$event->objectid;

        if (!$sessionid) {
            debugging('icalsender: attendance event missing session id', DEBUG_DEVELOPER);
            return;
        }

        $session = self::attendance_session($sessionid, $attendanceid);
        if (!$session) {
            return;
        }

        $eventrecord = self::attendance_event_record($session);
        if (!$eventrecord) {
            return;
        }

        $candidateusers = self::attendance_session_users($eventrecord);
        if (empty($candidateusers)) {
            debugging('icalsender: attendance session has no active enrolled users', DEBUG_DEVELOPER);
            return;
        }
        $statusdata = self::attendance_calendar_users($sessionid, $candidateusers);
        $users = $statusdata['users'];
        $statusids = $statusdata['statusids'];

        $courseurl = new \moodle_url('/course/view.php', ['id' => $eventrecord->courseid]);
        if (event_delivery::uses_api()) {
            if (empty($users)) {
                event_delivery::event_deleted((int)$eventrecord->id);
                return;
            }
            // Shared-calendar delivery creates the Google event on first Attendance detection and updates it after that.
            event_delivery::event_updated($eventrecord, $courseurl->out(false), $users);
            return;
        }

        self::seed_legacy_attendance_delivery($sessionid, (int)$eventrecord->id, $candidateusers);
        local_icalsender_delete_event((int)$eventrecord->id);
        $records = self::attendance_delivery_records($sessionid);
        $createusers = [];
        foreach ($users as $userid => $user) {
            if (empty($records[$userid]) || empty($records[$userid]->active)) {
                $createusers[$userid] = $user;
            }
        }

        $canceluserids = [];
        foreach ($records as $userid => $record) {
            if (!empty($record->active) && !isset($users[$userid])) {
                $canceluserids[] = (int)$userid;
            }
        }
        $cancelusers = self::attendance_users_by_id($canceluserids);

        $seqnum = null;
        if (!empty($createusers) || !empty($cancelusers)) {
            $seqnum = self::attendance_next_sequence($records);
        }
        if (!empty($cancelusers)) {
            local_icalsender_send_attendance_cancel_ics_attachment(
                $eventrecord,
                $cancelusers,
                $courseurl->out(),
                array_fill_keys(array_keys($cancelusers), $seqnum)
            );
        }
        if (!empty($createusers)) {
            local_icalsender_send_attendance_invite_ics_attachment($eventrecord, $createusers, $courseurl->out(), $seqnum);
        }
        self::save_attendance_delivery_state($sessionid, (int)$eventrecord->id, $statusids, $records, $seqnum);
    }

    /**
     * Fetch an Attendance session for a student self-marking event.
     *
     * @param int $sessionid Attendance session id.
     * @param int $attendanceid Attendance activity id from the event.
     * @return \stdClass|null
     */
    private static function attendance_session(int $sessionid, int $attendanceid): ?\stdClass {
        global $DB;

        $session = $DB->get_record('attendance_sessions', ['id' => $sessionid], '*', IGNORE_MISSING);
        if (!$session) {
            debugging('icalsender: attendance session not found', DEBUG_DEVELOPER);
            return null;
        }
        if ($attendanceid && (int)$session->attendanceid !== $attendanceid) {
            debugging('icalsender: attendance session does not match event activity id', DEBUG_DEVELOPER);
            return null;
        }
        return $session;
    }

    /**
     * Resolve an Attendance session to the Moodle calendar event shape used by iCal Sender.
     *
     * @param \stdClass $session Attendance session record.
     * @return \stdClass|null
     */
    private static function attendance_event_record(\stdClass $session): ?\stdClass {
        global $DB;

        if (!empty($session->caleventid)) {
            $eventrecord = $DB->get_record('event', ['id' => (int)$session->caleventid], '*', IGNORE_MISSING);
            if ($eventrecord) {
                $eventrecord->timeduration = (int)($eventrecord->timeduration ?? 0);
                $eventrecord->location = (string)($eventrecord->location ?? '');
                $eventrecord->name = self::attendance_course_name((int)$eventrecord->courseid, $eventrecord->name);
                return $eventrecord;
            }
        }

        $attendance = $DB->get_record('attendance', ['id' => (int)$session->attendanceid], '*', IGNORE_MISSING);
        if (!$attendance) {
            debugging('icalsender: attendance activity not found', DEBUG_DEVELOPER);
            return null;
        }

        $eventrecord = new \stdClass();
        $eventrecord->id = 0 - (int)$session->id;
        $eventrecord->name = self::attendance_course_name((int)$attendance->course, $attendance->name);
        $eventrecord->description = (string)($session->description ?? '');
        $eventrecord->courseid = (int)$attendance->course;
        $eventrecord->eventtype = 'attendance';
        $eventrecord->groupid = (int)($session->groupid ?? 0);
        $eventrecord->userid = 0;
        $eventrecord->modulename = 'attendance';
        $eventrecord->instance = (int)$session->attendanceid;
        $eventrecord->timestart = (int)$session->sessdate;
        $eventrecord->timeduration = (int)($session->duration ?? 0);
        $eventrecord->location = '';
        return $eventrecord;
    }

    /**
     * Get the course name to use as the Attendance invite title.
     *
     * @param int $courseid Moodle course id.
     * @param string $fallback Fallback title when the course cannot be loaded.
     * @return string Course fullname or fallback.
     */
    private static function attendance_course_name(int $courseid, string $fallback): string {
        global $DB;

        if (!$courseid) {
            return $fallback;
        }

        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid], IGNORE_MISSING);
        return $coursename !== false ? (string)$coursename : $fallback;
    }

    /**
     * Get active enrolled users for an Attendance session.
     *
     * @param \stdClass $eventrecord Event data object.
     * @return array Moodle user records keyed by user id.
     */
    private static function attendance_session_users(\stdClass $eventrecord): array {
        $context = \context_course::instance((int)$eventrecord->courseid);
        $groupid = (int)($eventrecord->groupid ?? 0);
        return get_enrolled_users($context, '', $groupid, 'u.*', null, 0, 0, true);
    }

    /**
     * Get users whose current Attendance status should keep a calendar invite active.
     *
     * @param int $sessionid Attendance session id.
     * @param array $candidateusers Active enrolled course or group users keyed by user id.
     * @return array Users and status ids keyed by user id.
     */
    private static function attendance_calendar_users(int $sessionid, array $candidateusers): array {
        global $DB;

        if (empty($candidateusers)) {
            return ['users' => [], 'statusids' => []];
        }

        list($usersql, $params) = $DB->get_in_or_equal(array_keys($candidateusers), SQL_PARAMS_NAMED, 'userid');
        $params['sessionid'] = $sessionid;
        $sql = "SELECT l.studentid, l.statusid, s.acronym, s.description
                  FROM {attendance_log} l
                  JOIN {attendance_statuses} s ON s.id = l.statusid
                 WHERE l.sessionid = :sessionid
                   AND l.studentid {$usersql}
                   AND s.deleted = 0
                   AND s.grade > 0";
        $records = $DB->get_records_sql($sql, $params);

        $users = [];
        $statusids = [];
        foreach ($records as $record) {
            $userid = (int)$record->studentid;
            if (isset($candidateusers[$userid]) && !self::is_excused_attendance_status($record)) {
                $users[$userid] = $candidateusers[$userid];
                $statusids[$userid] = (int)$record->statusid;
            }
        }
        return ['users' => $users, 'statusids' => $statusids];
    }

    /**
     * Whether an Attendance status is the default Excused state.
     *
     * @param \stdClass $status Attendance status record.
     * @return bool
     */
    private static function is_excused_attendance_status(\stdClass $status): bool {
        $acronym = strtoupper(trim((string)($status->acronym ?? '')));
        $description = strtolower(trim((string)($status->description ?? '')));

        return $acronym === 'E' || $description === 'excused';
    }

    /**
     * Get prior Attendance ICS delivery records for a session.
     *
     * @param int $sessionid Attendance session id.
     * @return array Delivery records keyed by user id.
     */
    private static function attendance_delivery_records(int $sessionid): array {
        global $DB;

        $records = $DB->get_records('local_icalsender_att_users', ['sessionid' => $sessionid]);
        $byuser = [];
        foreach ($records as $record) {
            $byuser[(int)$record->userid] = $record;
        }
        return $byuser;
    }

    /**
     * Import Attendance invites that were tracked with the old event-level ICS table.
     *
     * @param int $sessionid Attendance session id.
     * @param int $eventid Moodle calendar event id.
     * @param array $candidateusers Active enrolled course or group users keyed by user id.
     * @return void
     */
    private static function seed_legacy_attendance_delivery(int $sessionid, int $eventid, array $candidateusers): void {
        global $DB;

        if (
            empty($candidateusers) ||
            $DB->record_exists('local_icalsender_att_users', ['sessionid' => $sessionid]) ||
            !$DB->record_exists('local_icalsender_ics_events', ['eventid' => $eventid])
        ) {
            return;
        }

        $seqnum = (int)local_icalsender_get_sequence_number($eventid);
        $now = time();
        foreach ($candidateusers as $userid => $user) {
            $DB->insert_record('local_icalsender_att_users', (object)[
                'eventid' => $eventid,
                'sessionid' => $sessionid,
                'userid' => (int)$userid,
                'statusid' => null,
                'active' => 1,
                'seqnum' => $seqnum,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Load active Moodle users by id.
     *
     * @param array $userids User ids.
     * @return array User records keyed by user id.
     */
    private static function attendance_users_by_id(array $userids): array {
        global $DB;

        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (empty($userids)) {
            return [];
        }

        list($usersql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        return $DB->get_records_select('user', "id {$usersql} AND deleted = 0", $params);
    }

    /**
     * Get the next Attendance ICS sequence number for a session.
     *
     * @param array $records Existing delivery records.
     * @return int Next sequence number.
     */
    private static function attendance_next_sequence(array $records): int {
        $maxseq = null;
        foreach ($records as $record) {
            $seqnum = (int)($record->seqnum ?? 0);
            $maxseq = $maxseq === null ? $seqnum : max($maxseq, $seqnum);
        }
        return $maxseq === null ? 0 : $maxseq + 1;
    }

    /**
     * Persist per-user Attendance ICS delivery state.
     *
     * @param int $sessionid Attendance session id.
     * @param int $eventid Moodle calendar event id.
     * @param array $statusids Calendar-eligible status ids keyed by user id.
     * @param array $records Existing delivery records keyed by user id.
     * @param int|null $seqnum Sequence number sent for create/delete changes.
     * @return void
     */
    private static function save_attendance_delivery_state(
        int $sessionid,
        int $eventid,
        array $statusids,
        array $records,
        ?int $seqnum
    ): void {
        global $DB;

        $now = time();
        foreach ($statusids as $userid => $statusid) {
            $userid = (int)$userid;
            if (!empty($records[$userid])) {
                $record = $records[$userid];
                $wasactive = !empty($record->active);
                $record->eventid = $eventid;
                $record->statusid = (int)$statusid;
                $record->active = 1;
                if (!$wasactive && $seqnum !== null) {
                    $record->seqnum = $seqnum;
                }
                $record->timemodified = $now;
                $DB->update_record('local_icalsender_att_users', $record);
                continue;
            }

            $DB->insert_record('local_icalsender_att_users', (object)[
                'eventid' => $eventid,
                'sessionid' => $sessionid,
                'userid' => $userid,
                'statusid' => (int)$statusid,
                'active' => 1,
                'seqnum' => $seqnum ?? 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        foreach ($records as $userid => $record) {
            if (!empty($record->active) && !isset($statusids[$userid])) {
                $record->eventid = $eventid;
                $record->active = 0;
                if ($seqnum !== null) {
                    $record->seqnum = $seqnum;
                }
                $record->timemodified = $now;
                $DB->update_record('local_icalsender_att_users', $record);
            }
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

        // Check if the event is in the past. If so, do not send any notifications.
        if ($eventrecord->timestart + $eventrecord->timeduration < time()) {
            return;  // Event is in the past, skip it.
        }

        $skipics = false;
        switch ($eventrecord->eventtype) {
            case "course":
                $courseid = $eventrecord->courseid;
                if (!$courseid) {
                    debugging("icalsender: course event detected but no courseid", DEBUG_DEVELOPER);
                    return;
                }
                // Get all enrolled, active  users in that course.
                $context = \context_course::instance($courseid);
                $users = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true); // Excludes suspended users.
                break;
            case "group":
                $courseid = $eventrecord->courseid;
                $groupid = $eventrecord->groupid;
                if (!$courseid || !$groupid) {
                    debugging("icalsender: missing courseid or groupid");
                    return;
                }
                $context = \context_course::instance($courseid);
                // Filter on groupid and excludes suspended users.
                $users   = get_enrolled_users($context, '', $groupid, 'u.*', null, 0, 0, true);
                if (empty($users)) {
                    debugging("icalsender: no users in group", DEBUG_DEVELOPER);
                    $skipics = true;
                }
                break;
            case "site":
            case "category":
            case "user":
            default:
                return;
        }

        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        if (event_delivery::uses_api()) {
            event_delivery::event_created($eventrecord, $courseurl->out(false), $users);
            return;
        }
        if ($skipics) {
            return;
        }
        local_icalsender_send_mail_with_ics_attachment($eventrecord, $users, $courseurl->out(), true, 0);
        local_icalsender_insert_event($eventid, $eventrecord->name);   // Insert record into local_icalsender_ics_events.
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

        // Check if the event is in the past. If so, do not send any notifications.
        if ($eventrecord->timestart + $eventrecord->timeduration < time()) {
            // Keep an existing shared-calendar event accurate even when it is moved into the past.
            if (
                event_delivery::uses_api()
                && in_array($eventrecord->eventtype, ['course', 'group'], true)
                && !empty($eventrecord->courseid)
            ) {
                $courseurl = new \moodle_url('/course/view.php', ['id' => $eventrecord->courseid]);
                $context = \context_course::instance($eventrecord->courseid);
                $groupid = $eventrecord->eventtype === 'group' ? (int)($eventrecord->groupid ?? 0) : 0;
                if ($eventrecord->eventtype !== 'group' || $groupid) {
                    $users = get_enrolled_users($context, '', $groupid, 'u.*', null, 0, 0, true);
                    event_delivery::event_updated($eventrecord, $courseurl->out(false), $users, false);
                }
            }
            return;  // Event is in the past, skip it.
        }

        $skipics = false;
        switch ($eventrecord->eventtype) {
            case "course":
                $courseid = $eventrecord->courseid;
                if (!$courseid) {
                    debugging("icalsender: course event detected but no courseid", DEBUG_DEVELOPER);
                    return;
                }
                // Get all enrolled, active users in that course.
                $context = \context_course::instance($courseid);
                $users = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true); // Excludes suspended users.
                break;
            case "group":
                $courseid = $eventrecord->courseid;
                $groupid = $eventrecord->groupid;
                if (!$courseid || !$groupid) {
                    debugging("icalsender: missing courseid or groupid", DEBUG_DEVELOPER);
                    return;
                }

                $context = \context_course::instance($courseid);
                // Filter on groupid and excludes suspended users.
                $users   = get_enrolled_users($context, '', $groupid, 'u.*', null, 0, 0, true);
                if (empty($users)) {
                    debugging("icalsender: no users in group", DEBUG_DEVELOPER);
                    $skipics = true;
                }
                break;
            case "site":
            case "category":
            case "user":
            default:
                return;
        }
        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        if (event_delivery::uses_api()) {
            event_delivery::event_updated($eventrecord, $courseurl->out(false), $users);
            return;
        }
        if ($skipics) {
            return;
        }

        if (!$DB->record_exists('local_icalsender_ics_events', ['eventid' => $eventid])) {
            local_icalsender_insert_event($eventid, $eventrecord->name);
            $seqnum = 0;
        } else {
            $seqnum = local_icalsender_get_sequence_number($eventid) + 1;
        }

        local_icalsender_send_mail_with_update_ics_attachment($eventrecord, $users, $courseurl->out(), false, $seqnum);
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

        $eventid = $event->objectid;
        if (event_delivery::uses_api()) {
            event_delivery::event_deleted((int)$eventid);
            return;
        }
        // Query the DB to check if the eventid matches one of the events we have sent out an ICS invite for.
        if ($DB->record_exists('local_icalsender_ics_events', ['eventid' => $eventid])) {
            $eventname = local_icalsender_get_event_name($eventid);
            $seqnum = local_icalsender_get_sequence_number($eventid) + 1;

            $data = $event->get_data();
            $eventid = $data['objectid']; // The ID of the deleted event.
            $courseid = $data['courseid'];
            $course = $DB->get_record('course', ['id' => $courseid], 'fullname');

            $context = \context_course::instance($courseid);
            $users = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true); // Excludes suspended users.
            $eventrecord = new \stdClass();
            $eventrecord->id = $eventid;
            $eventrecord->name = $eventname;
            $eventrecord->description = "Cancelling LMS Event $eventname for $course->fullname";
            $eventrecord->timestart = $event->other['timestart'];
            if (isset($event->other['timeduration'])) {
                $eventrecord->timeduration = $event->other['timeduration'];
            } else {
                $eventrecord->timeduration = 0;
            }
            // Location information is lost since already removed from DB table.Just set to empty.
            $eventrecord->location = '';
            $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

            local_icalsender_send_mail_with_delete_ics_attachment($eventrecord, $users, $courseurl->out(), true, $seqnum);
            local_icalsender_delete_event($eventid);
        } else {
            debugging("icalsender: event $eventid not found in DB ... ignore calendar delete event", DEBUG_DEVELOPER);
            return;
        }
    }
}
