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
     * Valid users are included once as Google Calendar attendees.
     */
    public function test_event_body_attendees(): void {
        $event = (object)[
            'id' => 44,
            'name' => 'Attendee event',
            'description' => '',
            'timestart' => 1735689600,
            'timeduration' => 3600,
        ];
        $users = [
            (object)[
                'firstname' => 'Alice',
                'lastname' => 'Example',
                'firstnamephonetic' => '',
                'lastnamephonetic' => '',
                'middlename' => '',
                'alternatename' => '',
                'email' => 'alice@example.com',
            ],
            (object)[
                'firstname' => 'Duplicate',
                'lastname' => 'Alice',
                'firstnamephonetic' => '',
                'lastnamephonetic' => '',
                'middlename' => '',
                'alternatename' => '',
                'email' => 'ALICE@example.com',
            ],
            (object)[
                'firstname' => 'Bob',
                'lastname' => 'Example',
                'firstnamephonetic' => '',
                'lastnamephonetic' => '',
                'middlename' => '',
                'alternatename' => '',
                'email' => 'bob@example.com',
            ],
            (object)['firstname' => 'Invalid', 'lastname' => 'User', 'email' => 'not-an-email'],
        ];

        $body = google_calendar::event_body($event, 'https://moodle.example/', $users);

        $this->assertCount(2, $body['attendees']);
        $this->assertSame('alice@example.com', $body['attendees'][0]['email']);
        $this->assertSame('Alice Example', $body['attendees'][0]['displayName']);
        $this->assertSame('bob@example.com', $body['attendees'][1]['email']);
    }

    /**
     * Service account JWT assertions contain the expected claims and a valid RSA signature.
     */
    public function test_service_account_jwt(): void {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('The openssl extension is required to test service account JWT signing.');
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privatekey));

        $credentials = [
            'type' => 'service_account',
            'client_email' => 'calendar-service@example.iam.gserviceaccount.com',
            'private_key' => $privatekey,
            'private_key_id' => 'testkeyid',
        ];
        $issuedat = 1735689600;

        $method = new \ReflectionMethod(google_calendar::class, 'create_signed_jwt');
        $method->setAccessible(true);
        $jwt = $method->invoke(null, $credentials, $issuedat, 'calendar-admin@example.com');
        $parts = explode('.', $jwt);

        $this->assertCount(3, $parts);
        $header = json_decode($this->base64url_decode($parts[0]), true);
        $claims = json_decode($this->base64url_decode($parts[1]), true);
        $signature = $this->base64url_decode($parts[2]);
        $publickey = openssl_pkey_get_details($key)['key'];

        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
        $this->assertSame('testkeyid', $header['kid']);
        $this->assertSame('calendar-service@example.iam.gserviceaccount.com', $claims['iss']);
        $this->assertSame('https://www.googleapis.com/auth/calendar.events', $claims['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
        $this->assertSame($issuedat, $claims['iat']);
        $this->assertSame($issuedat + 3600, $claims['exp']);
        $this->assertSame('calendar-admin@example.com', $claims['sub']);
        $this->assertSame(1, openssl_verify($parts[0] . '.' . $parts[1], $signature, $publickey, OPENSSL_ALGO_SHA256));
    }

    /**
     * Empty delegated user leaves the service account JWT unimpersonated.
     */
    public function test_service_account_jwt_without_delegated_user(): void {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('The openssl extension is required to test service account JWT signing.');
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privatekey));

        $credentials = [
            'type' => 'service_account',
            'client_email' => 'calendar-service@example.iam.gserviceaccount.com',
            'private_key' => $privatekey,
            'private_key_id' => 'testkeyid',
        ];

        $method = new \ReflectionMethod(google_calendar::class, 'create_signed_jwt');
        $method->setAccessible(true);
        $jwt = $method->invoke(null, $credentials, 1735689600, '');
        $parts = explode('.', $jwt);
        $claims = json_decode($this->base64url_decode($parts[1]), true);

        $this->assertArrayNotHasKey('sub', $claims);
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

    /**
     * Google Calendar JSON errors are reduced to useful diagnostics.
     */
    public function test_google_error_summary_for_calendar_api_error(): void {
        $summary = $this->invoke_google_error_summary(json_encode([
            'error' => [
                'code' => 403,
                'message' => 'The caller does not have permission',
                'status' => 'PERMISSION_DENIED',
                'errors' => [
                    [
                        'reason' => 'forbiddenForServiceAccounts',
                        'message' => 'Service accounts cannot invite attendees without delegation.',
                    ],
                ],
            ],
        ]));

        $this->assertSame(
            'PERMISSION_DENIED; The caller does not have permission; forbiddenForServiceAccounts; '
                . 'Service accounts cannot invite attendees without delegation.',
            $summary
        );
    }

    /**
     * Google OAuth token endpoint errors are reduced to useful diagnostics.
     */
    public function test_google_error_summary_for_token_error(): void {
        $summary = $this->invoke_google_error_summary(json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'Invalid JWT Signature.',
        ]));

        $this->assertSame('invalid_grant; Invalid JWT Signature.', $summary);
    }

    /**
     * Calendar 404 diagnostics identify the delegated user whose calendar access matters.
     */
    public function test_calendar_access_hint_names_delegated_user(): void {
        $service = new google_calendar([
            'type' => 'service_account',
            'client_email' => 'calendar-service@example.iam.gserviceaccount.com',
            'private_key' => 'unused in this test',
        ], 'calendar-admin@example.com');

        $method = new \ReflectionMethod(google_calendar::class, 'calendar_access_hint');
        $method->setAccessible(true);
        $hint = $method->invoke($service, 'shared@example.com');

        $this->assertStringContainsString("course calendarid 'shared@example.com'", $hint);
        $this->assertStringContainsString("delegated Google user 'calendar-admin@example.com'", $hint);
        $this->assertStringContainsString('Make changes to events', $hint);
    }

    /**
     * Calendar 404 diagnostics name the service account when no delegated user is configured.
     */
    public function test_calendar_access_hint_names_service_account_without_delegated_user(): void {
        $service = new google_calendar([
            'type' => 'service_account',
            'client_email' => 'calendar-service@example.iam.gserviceaccount.com',
            'private_key' => 'unused in this test',
        ], '');

        $method = new \ReflectionMethod(google_calendar::class, 'calendar_access_hint');
        $method->setAccessible(true);
        $hint = $method->invoke($service, 'shared@example.com');

        $this->assertStringContainsString("service account 'calendar-service@example.iam.gserviceaccount.com'", $hint);
    }

    /**
     * Google failures are emitted as Moodle log events.
     */
    public function test_google_failure_logs_moodle_event(): void {
        $this->resetAfterTest(true);
        $sink = $this->redirectEvents();

        $method = new \ReflectionMethod(google_calendar::class, 'log_failure');
        $method->setAccessible(true);
        $method->invoke(
            null,
            'create',
            123,
            new \RuntimeException('Google Calendar request failed with HTTP status 403.'),
            null,
            'shared@example.com'
        );

        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_icalsender\event\google_sync_failed::class, $events[0]);
        $this->assertSame(123, $events[0]->objectid);
        $this->assertSame('create', $events[0]->other['action']);
        $this->assertSame('shared@example.com', $events[0]->other['calendarid']);
    }

    /**
     * Decode a base64url-encoded JWT segment.
     *
     * @param string $value Encoded value.
     * @return string Decoded value.
     */
    private function base64url_decode(string $value): string {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($value, '-_', '+/'));
    }

    /**
     * Invoke the private Google error summary helper.
     *
     * @param string $response JSON response body.
     * @return string|null Error summary.
     */
    private function invoke_google_error_summary(string $response): ?string {
        $method = new \ReflectionMethod(google_calendar::class, 'google_error_summary');
        $method->setAccessible(true);
        return $method->invoke(null, $response);
    }
}
