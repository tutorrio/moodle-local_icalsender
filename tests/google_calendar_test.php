<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_icalsender;

/**
 * Tests for Google Calendar synchronisation helpers.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_icalsender\google_calendar
 */
final class google_calendar_test extends \advanced_testcase {
    /**
     * The Google event body contains the corresponding Moodle event fields.
     */
    public function test_event_body(): void {
        $event = (object)[
            'id' => 42,
            'name' => 'Workshop',
            'description' => '<p>Bring &amp; share</p>',
            'location' => 'Room 3',
            'timestart' => 1735689600,
            'timeduration' => 3600,
        ];

        $body = google_calendar::event_body($event, 'https://moodle.example/course/view.php?id=7');

        $this->assertSame('Workshop', $body['summary']);
        $this->assertSame('Bring & share', $body['description']);
        $this->assertSame('Room 3', $body['location']);
        $this->assertSame('2025-01-01T00:00:00Z', $body['start']['dateTime']);
        $this->assertSame('2025-01-01T01:00:00Z', $body['end']['dateTime']);
        $this->assertSame('42', $body['extendedProperties']['private']['moodleEventId']);
    }

    /**
     * Zero-duration Moodle events receive the smallest valid exclusive end time.
     */
    public function test_event_body_zero_duration(): void {
        $event = (object)[
            'id' => 43,
            'name' => 'Deadline',
            'description' => '',
            'timestart' => 1735689600,
            'timeduration' => 0,
        ];

        $body = google_calendar::event_body($event, 'https://moodle.example/');

        $this->assertSame('2025-01-01T00:00:01Z', $body['end']['dateTime']);
    }

    /**
     * Courses without the calendarid custom field do not opt into Google sync.
     */
    public function test_get_course_calendar_id_without_field(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        $this->assertNull(google_calendar::get_course_calendar_id($course->id));
    }

    /**
     * A non-empty calendarid course custom field opts the course into Google sync.
     */
    public function test_get_course_calendar_id(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category([
            'component' => 'core_course',
            'area' => 'course',
            'itemid' => 0,
        ]);
        $field = $generator->create_field([
            'categoryid' => $category->get('id'),
            'shortname' => 'calendarid',
            'type' => 'text',
        ]);
        $generator->add_instance_data($field, $course->id, ' shared@example.com ');

        $this->assertSame('shared@example.com', google_calendar::get_course_calendar_id($course->id));
    }
}
