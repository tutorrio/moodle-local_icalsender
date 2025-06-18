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

global $CFG;
require_once($CFG->dirroot . '/local/icalsender/locallib.php');
require_once($CFG->dirroot . '/local/icalsender/classes/mailer.php');



use local_icalsender\mailer;

defined('MOODLE_INTERNAL') || die();


/**
 * Unit tests for locallib.php functions.
 *
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mock_test extends \advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
    }

    public function test_send_mail_with_ics_attachment_successful() {
        // Setup mock user.


        // Setup global USER mock.
        $USER = new \stdClass();
        $USER->id = 1;
        $USER->firstname = 'OrganizerFirst';
        $USER->lastname = 'OrganizerLast';
        $USER->email = 'organizer@example.com';

        // Dummy event record.
        $eventrecord = new \stdClass();
        $eventrecord->id = 123;
        $eventrecord->name = 'Test Event';
        $eventrecord->timestart = time() + 3600; // 1 hour from now.
        $eventrecord->timeduration = 3600; // 1 hour duration.
        $eventrecord->location = 'Test Location';
        $eventrecord->description = "This is a test event.\nWith multiple lines.";

        // Dummy users array.
        $user1 = new \stdClass();
        $user1->id = 2;
        $user1->firstname = 'UserOne';
        $user1->lastname = 'LastOne';
        $user1->email = 'userone@example.com';

        $user2 = new \stdClass();
        $user2->id = 3;
        $user2->firstname = 'UserTwo';
        $user2->lastname = 'LastTwo';
        $user2->email = 'usertwo@example.com';

        $users = [$user1, $user2];

        $url = 'http://example.com/course/1';
        $organizeralso = false;
        $seqnumber = 1;


        // $user = (object) [
        //     'id' => 2,
        //     'email' => 'testuser@example.com',
        //     'firstname' => 'Test',
        //     'lastname' => 'User',
        // ];

        $subject = 'Test Subject';
        $message = '<p>This is a test message.</p>';
        $icsdata = 'BEGIN:VCALENDAR...END:VCALENDAR';
        $filename = 'invite.ics';

        // Mock the mailer class if used.
        $mailerMock = $this->getMockBuilder(mailer::class)
            ->onlyMethods(['local_icalsender_send_ics_mail_from_noreply'])
            ->getMock();

        $mailerMock->expects(self::exactly(3))
        ->method('local_icalsender_send_ics_mail_from_noreply')
        ->willReturnMap([
            [$USER->email, 'Foo','dsds', 'cc'],
            [$user1->email, 'Bar','dsds', 'cc'],
            [$user2->email, 'Baz','dsds', 'cc'],
        ]);

        // Inject the mock somehow or override internal function if necessary.
        // If not possible directly, consider using a wrapper or dependency injection.

        // Call the function.
        local_icalsender_send_mail_with_ics_attachment($eventrecord, $users, $url, $organizeralso, $seqnumber);

    }

    // /**
    //  * Test local_icalsender_send_mail_with_ics_attachment function.
    //  *
    //  * Verifies that local_icalsender_send_mail_with_ics_attachment sends ICS calendar invitation emails
    //  * to all specified attendees and, if requested, to the organizer as well.
    //  * Sets up a mock event and users, calls the function, and asserts that the
    //  * mail-sending stub is called for each intended recipient.
    //  *
    //  * Assertions:
    //  * - local_icalsender_send_ics_mail_from_noreply is called at least once.
    //  * - The organizer receives an email if $organizeralso is true.
    //  * - All users in the attendees list receive an email.
    //  * @covers \local_icalsender\helper::test_local_icalsender_send_mail_with_ics_attachment
    //  */
    // public function test_local_icalsender_send_mail_with_ics_attachment(): void {
    //     global $USER, $senticsmails;

    //     // Reset captured calls.
    //     $senticsmails = [];

    //     // Setup global USER mock.
    //     $USER = new \stdClass();
    //     $USER->id = 1;
    //     $USER->firstname = 'OrganizerFirst';
    //     $USER->lastname = 'OrganizerLast';
    //     $USER->email = 'organizer@example.com';

    //     // Dummy event record.
    //     $eventrecord = new \stdClass();
    //     $eventrecord->id = 123;
    //     $eventrecord->name = 'Test Event';
    //     $eventrecord->timestart = time() + 3600; // 1 hour from now.
    //     $eventrecord->timeduration = 3600; // 1 hour duration.
    //     $eventrecord->location = 'Test Location';
    //     $eventrecord->description = "This is a test event.\nWith multiple lines.";

    //     // Dummy users array.
    //     $user1 = new \stdClass();
    //     $user1->id = 2;
    //     $user1->firstname = 'UserOne';
    //     $user1->lastname = 'LastOne';
    //     $user1->email = 'userone@example.com';

    //     $user2 = new \stdClass();
    //     $user2->id = 3;
    //     $user2->firstname = 'UserTwo';
    //     $user2->lastname = 'LastTwo';
    //     $user2->email = 'usertwo@example.com';

    //     $users = [$user1, $user2];

    //     $url = 'http://example.com/course/1';
    //     $organizeralso = true;
    //     $seqnumber = 1;

    //     $mock = $this->getMockBuilder(\local_icalsender\mailer::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods(['local_icalsender_send_ics_mail_from_noreply'])
    //         ->getMock();

    //     $mock->expects(self::exactly(3))
    //     ->method('local_icalsender_send_ics_mail_from_noreply')
    //     ->willReturnMap([
    //         [$USER->email, 'Foo','dsds', 'cc'],
    //         [$user1->email, 'Bar','dsds', 'cc'],
    //         [$user2->email, 'Baz','dsds', 'cc'],
    //     ]);

    //     // Call the function under test.
    //     local_icalsender_send_mail_with_ics_attachment($eventrecord, $users, $url, $organizeralso, $seqnumber);


    //     // self::assertSame('Bar', $mock->get(9));
    //     // self::assertSame('Baz', $mock->get(5));
    //     // self::assertSame('Foo', $mock->get(1));

    //     // // Assertions.
    //     // $this->assertNotEmpty($senticsmails, "local_icalsender_send_ics_mail_from_noreply was not called");

    //     // // Organizer email should be sent once.
    //     // $foundorganizer = false;
    //     // $founduserone = false;
    //     // $foundusertwo = false;
    //     // foreach ($senticsmails as $call) {
    //     //     if ($call['useremail'] === $USER->email) {
    //     //         $foundorganizer = true;
    //     //     }
    //     //     if ($call['useremail'] === $user1->email) {
    //     //         $founduserone = true;
    //     //     }
    //     //     if ($call['useremail'] === $user2->email) {
    //     //         $foundusertwo = true;
    //     //     }
    //     // }
    //     // $this->assertTrue($foundorganizer, "Organizer email was not sent");
    //     // $this->assertTrue($founduserone, "UserOne email was not sent");
    //     // $this->assertTrue($foundusertwo, "UserTwo email was not sent");
    //     return;
    // }

    // /**
    //  * Test local_icalsender_send_mail_with_ics_attachment sends emails to all attendees and optionally the organizer.
    //  * @covers \local_icalsender\helper::test_local_icalsender_send_mail_with_ics_attachment_sends_to_all_attendees_and_optionally_organizer
    //  */
    // public function test_local_icalsender_send_mail_with_ics_attachment_sends_to_all_attendees_and_optionally_organizer(): void {
    //     global $senticsmails, $USER;

    //     $senticsmails = [];

    //     // Mock global USER as the organizer.
    //     $USER = (object)[
    //         'id' => 1,
    //         'firstname' => 'Organizer',
    //         'lastname' => '',
    //         'email' => 'organizer@example.com',
    //         'username' => 'organizer@example.com',
    //     ];

    //     // Create a fake event record.
    //     $event = (object)[
    //         'id' => 100,
    //         'name' => 'Test Event',
    //         'timestart' => time() + 3600,
    //         'timeduration' => 3600,
    //         'location' => '',
    //         'description' => 'Event Description',
    //     ];

    //     // Create fake users (attendees).
    //     $attendee1 = (object)[
    //         'id' => 2,
    //         'firstname' => 'Alice',
    //         'lastname' => 'wonderland',
    //         'email' => 'alice@example.com',
    //         'username' => 'alice@example.com',
    //     ];
    //     $attendee2 = (object)[
    //         'id' => 3,
    //         'firstname' => 'Bob',
    //         'lastname' => 'Peeters',
    //         'email' => 'bob@example.com',
    //         'username' => 'bob@example.com',
    //     ];

    //     $users = [$attendee1, $attendee2];

    //     $url = 'https://example.com/course';
    //     $organizeralso = true;
    //     $seqnumber = 1;

    //     $mock = $this->getMockBuilder(\local_icalsender\mailer::class)
    //         ->onlyMethods(['local_icalsender_send_ics_mail_from_noreply'])
    //         ->getMock();

    //     local_icalsender_send_mail_with_ics_attachment($event, $users, $url, $organizeralso, $seqnumber);

    //     // Verify: should send to Alice, Bob, and the organizer.
    //     $this->assertCount(3, $senticsmails);

    //     $recipients = array_map(function($call) {
    //         return $call['useremail'];
    //     }, $senticsmails);

    //     $this->assertContains('alice@example.com', $recipients);
    //     $this->assertContains('bob@example.com', $recipients);
    //     $this->assertContains('organizer@example.com', $recipients);

    //     return;
    // }

}

