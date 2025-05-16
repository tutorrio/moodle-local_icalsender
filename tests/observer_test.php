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
 * Unit tests for observer class.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer_test extends \advanced_testcase {

    /**
     * Test calendar_event_created observer.
     * @covers \local_icalsender\helper::test_calendar_event_created_course_event
     */
    public function test_calendar_event_created_course_event(): void {
        global $DB, $CFG;

        // Ensure required Moodle libs are loaded for static analysis and runtime.
        require_once($CFG->dirroot . '/enrol/locallib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');
        require_once($CFG->dirroot . '/lib/accesslib.php');

        $this->resetAfterTest(true);

        // Create a course and a user.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Alice',
            'lastname' => 'Wonderland',
            'email' => 'user1@example.com',
            'username' => 'alice@example.com',
        ]);
        $this->setAdminUser();

        // Enrol user in course.
        $enrol = \enrol_get_plugin('manual');
        $enrolinstances = \enrol_get_instances($course->id, true);
        $manualinstance = null;
        foreach ($enrolinstances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
        $enrol->enrol_user($manualinstance, $user->id);

        // Create a course event.
        $event = new \stdClass();
        $event->name = 'Test Course Event';
        $event->description = 'Test event description';
        $event->courseid = $course->id;
        $event->eventtype = 'course';
        $event->groupid = 0;
        $event->userid = 0;
        $event->modulename = '';
        $event->instance = 0;
        $event->timestart = time() + 3600;
        $event->timeduration = 0;
        $event->visible = 1;
        $event->timemodified = time();
        $eventid = $DB->insert_record('event', $event);

        // Create the event object as Moodle would.
        $eventdata = [
            'objectid' => $eventid,
            'courseid' => $course->id,
            'contextid' => \context_course::instance($course->id)->id,
            'other' => [
                'repeatid' => 0,
                'name' => $event->name,
                'timestart' => $event->timestart,
                'timeduration' => $event->timeduration,
                'eventtype' => $event->eventtype,
            ],
        ];
        $eventobj = \core\event\calendar_event_created::create($eventdata);

        // Call the observer.
        $this->assertDebuggingNotCalled(); // If you want to ensure no unexpected debug output.
        observer::calendar_event_created($eventobj);

        // Assert that the event was logged in ics_event_log.
        $log = $DB->get_record('ics_event_log', ['eventid' => $eventid]);
        $this->assertNotEmpty($log, 'Event should be logged in ics_event_log');
        $this->assertEquals($event->name, $log->eventname);
    }

    /**
     * Test calendar delete
     * @covers \local_icalsender\helper::test_calendar_event_deleted_course_event
     */
    public function test_calendar_event_deleted_course_event(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $this->resetAfterTest(true);

        // Create course and user, enrol user.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Alice',
            'lastname' => 'Wonderland',
            'email' => 'user1@example.com',
            'username' => 'alice@example.com',
        ]);
        $this->setAdminUser();

        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course->id, true);
        $manualinstance = null;
        foreach ($enrolinstances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
        $enrol->enrol_user($manualinstance, $user->id);

        // Create a course event.
        $event = new \stdClass();
        $event->name = 'Test Delete Event';
        $event->description = 'This is a test delete event.';
        $event->courseid = $course->id;
        $event->eventtype = 'course';
        $event->groupid = 0;
        $event->userid = $user->id;
        $event->modulename = '';
        $event->instance = 0;
        $event->timestart = time() + 3600;
        $event->timeduration = 0;
        $event->visible = 1;
        $event->timemodified = time();
        $eventid = $DB->insert_record('event', $event);

        // Pretend the event was previously sent and logged.
        $DB->insert_record('ics_event_log', [
            'eventid' => $eventid,
            'eventname' => $event->name,
            'seqnum' => 0,
            'senttime' => time(),
        ]);

        // Now trigger the deletion.
        $eventdata = [
            'objectid' => $eventid,
            'courseid' => $course->id,
            'contextid' => \context_course::instance($course->id)->id,
            'other' => [
                'name' => $event->name,
                'eventtype' => $event->eventtype,
                'timeduration' => $event->timeduration,
                'timestart' => $event->timestart,
                'repeatid' => 0,
            ],
        ];
        $eventobj = \core\event\calendar_event_deleted::create($eventdata);

        // Expect debugging message.
        $this->assertDebuggingNotCalled(); // If you want to ensure no unexpected debug output.
        observer::calendar_event_deleted($eventobj);

        // Check that the log record has been removed.
        $logexists = $DB->record_exists('ics_event_log', ['eventid' => $eventid]);
        $this->assertFalse($logexists, 'Event should be deleted from ics_event_log');
    }

    /**
     * Test calender update
     * @covers \local_icalsender\helper::test_calendar_event_updated_course_event
     */
    public function test_calendar_event_updated_course_event(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Alice',
            'lastname' => 'Wonderland',
            'email' => 'user1@example.com',
            'username' => 'alice@example.com',
        ]);
        $this->setAdminUser();

        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course->id, true);
        foreach ($enrolinstances as $instance) {
            if ($instance->enrol === 'manual') {
                $enrol->enrol_user($instance, $user->id);
                break;
            }
        }

        // Create a course event.
        $event = new \stdClass();
        $event->name = 'Original Event';
        $event->description = 'Initial description';
        $event->courseid = $course->id;
        $event->eventtype = 'course';
        $event->groupid = 0;
        $event->userid = $user->id;
        $event->modulename = '';
        $event->instance = 0;
        $event->timestart = time() + 3600;
        $event->timeduration = 0;
        $event->visible = 1;
        $event->timemodified = time();
        $eventid = $DB->insert_record('event', $event);

        // Simulate the event has already been logged.
        $DB->insert_record('ics_event_log', [
            'eventid' => $eventid,
            'eventname' => $event->name,
            'seqnum' => 0,
            'senttime' => time(),
        ]);

        // Create and trigger the update event.
        $eventdata = [
            'objectid' => $eventid,
            'courseid' => $course->id,
            'contextid' => \context_course::instance($course->id)->id,
            'other' => [
                'repeatid' => 0,
                'name' => 'Updated Event',
                'timestart' => $event->timestart,
                'timeduration' => $event->timeduration,
                'eventtype' => $event->eventtype,
            ],
        ];
        $eventobj = \core\event\calendar_event_updated::create($eventdata);

        // Call the observer.
        $this->assertDebuggingNotCalled(); // If you want to ensure no unexpected debug output.
        observer::calendar_event_updated($eventobj);

        // Check sequence was incremented.
        $newseq = $DB->get_field('ics_event_log', 'seqnum', ['eventid' => $eventid]);
        $this->assertEquals(1, $newseq, 'Sequence number should be incremented after update');
    }
}
