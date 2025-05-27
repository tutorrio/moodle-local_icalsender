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

/**
 * Unit tests for locallib.php functions.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_icalsender;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/icalsender/locallib.php');

/**
 * Unit tests for locallib.php functions.
 *
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {

    /**
     * Test local_icalsender_format_ics_datetime returns correct ICS datetime string for known timestamps.
     * @covers \local_icalsender\helper::local_icalsender_format_ics_datetime_basic
     */
    public function test_local_icalsender_format_ics_datetime_basic(): void {
        // 2024-05-09 12:00:00 UTC
        $timestamp = 1715256000;
        $expected = '20240509T120000Z';
        $this->assertSame($expected, local_icalsender_format_ics_datetime($timestamp));
        return;
    }

    /**
     * Test local_icalsender_format_ics_datetime returns correct ICS datetime string for midnight timestamp.
     * @covers \local_icalsender\helper::test_local_icalsender_format_ics_datetime_midnight
     */
    public function test_local_icalsender_format_ics_datetime_midnight(): void {

        // 2025-01-01 00:00:00 UTC
        $timestamp = 1735689600;
        $expected = '20250101T000000Z';
        $this->assertSame($expected, local_icalsender_format_ics_datetime($timestamp));
        return;
    }

    /**
     * Test local_icalsender_format_ics_datetime returns correct ICS datetime string for end of year timestamp.
     * @covers \local_icalsender\helper::test_local_icalsender_format_ics_datetime_end_of_year
     */
    public function test_local_icalsender_format_ics_datetime_end_of_year(): void {

        // 2023-12-31 23:59:59 UTC
        $timestamp = 1704067199;
        $expected = '20231231T235959Z';
        $this->assertSame($expected, local_icalsender_format_ics_datetime($timestamp));
        return;
    }

    /**
     * Test local_icalsender_format_ics_datetime returns correct ICS datetime string for Unix epoch timestamp.
     * @covers \local_icalsender\helper::test_local_icalsender_format_ics_datetime_epoch
     */
    public function test_local_icalsender_format_ics_datetime_epoch(): void {

        $timestamp = 0;
        $expected = '19700101T000000Z';
        $this->assertSame($expected, local_icalsender_format_ics_datetime($timestamp));
        return;
    }

    /**
     * Test local_icalsender_remove_newlines removes all types of newlines and carriage returns.
     * @covers \local_icalsender\helper::test_local_icalsender_remove_newlines_mixed
     */
    public function test_local_icalsender_remove_newlines_mixed(): void {

        $input = "Line1\r\nLine2\nLine3\rLine4";
        $expected = "Line1Line2Line3Line4";
        $this->assertSame($expected, local_icalsender_remove_newlines($input));
        return;
    }

    /**
     * Test local_icalsender_remove_newlines removes string containing only newlines and carriage returns.
     * @covers \local_icalsender\helper::test_local_icalsender_remove_newlines_only_newlines
     */
    public function test_local_icalsender_remove_newlines_only_newlines(): void {

        $input = "\n\r\n\r";
        $expected = "";
        $this->assertSame($expected, local_icalsender_remove_newlines($input));
        return;
    }

    /**
     * Test local_icalsender_remove_newlines returns unchanged string when no newlines are present.
     * @covers \local_icalsender\helper::test_local_icalsender_remove_newlines_no_newlines
     */
    public function test_local_icalsender_remove_newlines_no_newlines(): void {

        $input = "NoNewlinesHere";
        $expected = "NoNewlinesHere";
        $this->assertSame($expected, local_icalsender_remove_newlines($input));
        return;
    }

    /**
     * Test local_icalsender_remove_newlines returns empty string when input is empty.
     * @covers \local_icalsender\helper::test_local_icalsender_remove_newlines_empty_string
     */
    public function test_local_icalsender_remove_newlines_empty_string(): void {

        $input = "";
        $expected = "";
        $this->assertSame($expected, local_icalsender_remove_newlines($input));
        return;
    }

    /**
     * Test local_icalsender_remove_newlines removes newlines at the start and end of the string.
     * @covers \local_icalsender\helper::test_local_icalsender_remove_newlines_newlines_at_edges
     */
    public function test_local_icalsender_remove_newlines_newlines_at_edges(): void {

        $input = "\nStartMiddle\r\nEnd\r";
        $expected = "StartMiddleEnd";
        $this->assertSame($expected, local_icalsender_remove_newlines($input));
        return;
    }

    /**
     * Test local_icalsender_generate_ics function with a basic event and attendees.
     * @covers \local_icalsender\helper::test_local_icalsender_generate_ics_basic
     */
    public function test_local_icalsender_generate_ics_basic(): void {

        // Prepare event record.
        $eventrecord = new \stdClass();
        $eventrecord->id = 123;
        $eventrecord->name = 'Test Event';
        $eventrecord->location = 'Test Room';
        $eventrecord->timestart = 1715539200; // 2024-05-12 12:00:00 UTC
        $eventrecord->timeduration = 3600;

        // Description.
        $desc = 'This is a test event.';

        // Users array (attendees).
        $user1 = new \stdClass();
        $user1->firstname = 'Alice';
        $user1->lastname = 'Smith';
        $user1->email = 'alice@example.com';

        $user2 = new \stdClass();
        $user2->firstname = 'Bob';
        $user2->lastname = 'Jones';
        $user2->email = 'bob@example.com';

        $users = [$user1, $user2];

        // Current user (chair).
        $USER = new \stdClass();
        $USER->firstname = 'Carol';
        $USER->lastname = 'Taylor';
        $USER->email = 'carol@example.com';

        // From (organizer).
        $from = new \stdClass();
        $from->firstname = 'Organizer';
        $from->lastname = 'Person';
        $from->email = 'organizer@example.com';

        $seqnumber = 1;
        $isorganizer = true;

        // Call the function.
        $ics = local_icalsender_generate_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, $isorganizer);

        // Assertions: check for key ICS fields and values.
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('SUMMARY:Test Event', $ics);
        $this->assertStringContainsString('LOCATION:Test Room', $ics);
        $this->assertStringContainsString('DESCRIPTION:This is a test event.', $ics);
        $this->assertStringContainsString('ORGANIZER;CN=LMS Organizer:mailto:organizer@example.com', $ics);
        $this->assertStringContainsString('ATTENDEE;CN=Carol Taylor;',  $ics);
        $this->assertStringContainsString('ATTENDEE;CN=Alice Smith;', $ics);
        $this->assertStringContainsString('ATTENDEE;CN=Bob Jones;', $ics);
        $this->assertStringContainsString('SEQUENCE:1', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        return;
    }

    /**
     * Test local_icalsender_generate_update_ics function with an updated event and attendees.
     * @covers \local_icalsender\helper::test_local_icalsender_generate_update_ics_basic
     */
    public function test_local_icalsender_generate_update_ics_basic(): void {

        // Prepare event record.
        $eventrecord = new \stdClass();
        $eventrecord->id = 456;
        $eventrecord->name = 'Update Event';
        $eventrecord->location = 'Update Room';
        $eventrecord->timestart = 1715542800; // 2024-05-12 13:00:00 UTC
        $eventrecord->timeduration = 1800;

        $desc = 'This is an updated event.';

        $user1 = new \stdClass();
        $user1->firstname = 'Dave';
        $user1->lastname = 'Brown';
        $user1->email = 'dave@example.com';

        $user2 = new \stdClass();
        $user2->firstname = 'Eve';
        $user2->lastname = 'White';
        $user2->email = 'eve@example.com';

        $users = [$user1, $user2];

        $USER = new \stdClass();
        $USER->firstname = 'Frank';
        $USER->lastname = 'Green';
        $USER->email = 'frank@example.com';

        $from = new \stdClass();
        $from->firstname = 'Organizer';
        $from->lastname = 'Update';
        $from->email = 'organizerupdate@example.com';

        $seqnumber = 2;
        $isorganizer = false;

        $ics = local_icalsender_generate_update_ics($eventrecord, $desc, $users, $USER, $from, $seqnumber, $isorganizer);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertStringContainsString('SUMMARY:Update Event', $ics);
        $this->assertStringContainsString('LOCATION:Update Room', $ics);
        $this->assertStringContainsString('DESCRIPTION:This is an updated event.', $ics);
        $this->assertStringContainsString('ORGANIZER;CN=Frank Green:mailto:frank@example.com', $ics);
        $this->assertStringContainsString('ATTENDEE;CN=Frank Green;', $ics);
        $this->assertStringContainsString('ATTENDEE;CN=Dave Brown;', $ics);
        $this->assertStringContainsString('ATTENDEE;CN=Eve White;', $ics);
        $this->assertStringContainsString('SEQUENCE:2', $ics);
        $this->assertStringContainsString('STATUS:CONFIRMED', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        return;
    }

    /**
     * Test local_icalsender_generate_cancel_ics function with a cancelled event.
     * @covers \local_icalsender\helper::test_local_icalsender_generate_cancel_ics_basic
     */
    public function test_local_icalsender_generate_cancel_ics_basic(): void {

        // Prepare event record.
        $eventrecord = new \stdClass();
        $eventrecord->id = 789;
        $eventrecord->name = 'Cancelled Event';
        $eventrecord->location = 'Cancel Room';
        $eventrecord->timestart = 1715546400; // 2024-05-12 14:00:00 UTC
        $eventrecord->timeduration = 900;

        $desc = 'This event has been cancelled.';

        $USER = new \stdClass();
        $USER->firstname = 'Grace';
        $USER->lastname = 'Hopper';
        $USER->email = 'grace@example.com';

        $organizeremail = 'organizerdelete@example.com';
        $seqnumber = 3;

        $ics = local_icalsender_generate_cancel_ics($eventrecord, $desc, $USER, $organizeremail, $seqnumber);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('METHOD:CANCEL', $ics);
        $this->assertStringContainsString('SUMMARY:Cancelled Event', $ics);
        $this->assertStringContainsString('LOCATION:Cancel Room', $ics);
        $this->assertStringContainsString('DESCRIPTION:This event has been cancelled.', $ics);
        $this->assertStringContainsString('ORGANIZER;CN=Grace Hopper:mailto:organizerdelete@example.com', $ics);
        $this->assertStringContainsString('SEQUENCE:3', $ics);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        return;
    }
}

