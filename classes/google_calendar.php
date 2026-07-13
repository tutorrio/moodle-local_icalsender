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
 * Synchronises Moodle calendar events with a course's shared Google calendar.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_calendar {
    /** Google Calendar API base URL. */
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    /** @var \core\oauth2\client Authenticated Moodle OAuth client. */
    private $client;

    /**
     * Constructor.
     *
     * @param \core\oauth2\client $client Authenticated Moodle OAuth client.
     */
    public function __construct(\core\oauth2\client $client) {
        $this->client = $client;
    }

    /**
     * Create the Google event when the course has a calendarid custom field.
     *
     * @param \stdClass $eventrecord Moodle event record.
     * @param string $courseurl URL of the Moodle course.
     * @param array $users Users to add as Google Calendar attendees.
     * @return void
     */
    public static function event_created(\stdClass $eventrecord, string $courseurl, array $users): void {
        self::safely(function () use ($eventrecord, $courseurl, $users): void {
            $calendarid = self::get_course_calendar_id((int)$eventrecord->courseid);
            if ($calendarid === null) {
                return;
            }
            $service = self::from_configuration();
            if ($service === null) {
                return;
            }
            $googleeventid = $service->insert($calendarid, self::event_body($eventrecord, $courseurl, $users));
            self::save_mapping((int)$eventrecord->id, $calendarid, $googleeventid);
        }, 'create', (int)$eventrecord->id);
    }

    /**
     * Update an existing Google event, or create it if it has not been synced before.
     *
     * @param \stdClass $eventrecord Moodle event record.
     * @param string $courseurl URL of the Moodle course.
     * @param array $users Users to add as Google Calendar attendees.
     * @param bool $createifmissing Create the Google event when no mapping exists.
     * @return void
     */
    public static function event_updated(
        \stdClass $eventrecord,
        string $courseurl,
        array $users,
        bool $createifmissing = true
    ): void {
        self::safely(function () use ($eventrecord, $courseurl, $users, $createifmissing): void {
            $calendarid = self::get_course_calendar_id((int)$eventrecord->courseid);
            if ($calendarid === null) {
                return;
            }
            $service = self::from_configuration();
            if ($service === null) {
                return;
            }

            $mapping = self::get_mapping((int)$eventrecord->id);
            $body = self::event_body($eventrecord, $courseurl, $users);
            if (!$mapping) {
                if (!$createifmissing) {
                    return;
                }
                $googleeventid = $service->insert($calendarid, $body);
                self::save_mapping((int)$eventrecord->id, $calendarid, $googleeventid);
                return;
            }
            if ($mapping->calendarid !== $calendarid) {
                $service->delete($mapping->calendarid, $mapping->googleeventid);
                $googleeventid = $service->insert($calendarid, $body);
                self::save_mapping((int)$eventrecord->id, $calendarid, $googleeventid);
                return;
            }
            $service->update($calendarid, $mapping->googleeventid, $body);
        }, 'update', (int)$eventrecord->id);
    }

    /**
     * Delete a previously synchronised Google event.
     *
     * @param int $eventid Moodle event ID.
     * @return void
     */
    public static function event_deleted(int $eventid): void {
        self::safely(function () use ($eventid): void {
            global $DB;

            $mapping = self::get_mapping($eventid);
            if (!$mapping) {
                return;
            }
            $service = self::from_configuration();
            if ($service === null) {
                return;
            }
            $service->delete($mapping->calendarid, $mapping->googleeventid);
            $DB->delete_records('local_icalsender_gcal_events', ['eventid' => $eventid]);
        }, 'delete', $eventid);
    }

    /**
     * Build a Google Calendar event resource from a Moodle event.
     *
     * @param \stdClass $eventrecord Moodle event record.
     * @param string $courseurl URL of the Moodle course.
     * @param array $users Users to add as Google Calendar attendees.
     * @return array Google Calendar event resource.
     */
    public static function event_body(\stdClass $eventrecord, string $courseurl, array $users = []): array {
        $start = (int)$eventrecord->timestart;
        // Google requires an exclusive end. Moodle permits events with no duration.
        $end = $start + max(1, (int)$eventrecord->timeduration);
        $description = trim(html_entity_decode(strip_tags($eventrecord->description ?? ''), ENT_QUOTES | ENT_HTML5));

        return [
            'summary' => (string)$eventrecord->name,
            'description' => $description,
            'location' => (string)($eventrecord->location ?? ''),
            'start' => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', $start)],
            'end' => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', $end)],
            'attendees' => self::attendees($users),
            'source' => ['title' => get_string('googleeventsource', 'local_icalsender'), 'url' => $courseurl],
            'extendedProperties' => ['private' => [
                'moodleEventId' => (string)$eventrecord->id,
                'moodlePlugin' => 'local_icalsender',
            ]],
        ];
    }

    /**
     * Build a unique Google attendee list from Moodle users with valid email addresses.
     *
     * @param array $users Moodle user records.
     * @return array Google Calendar attendee resources.
     */
    private static function attendees(array $users): array {
        $attendees = [];
        $seen = [];

        foreach ($users as $user) {
            $email = trim((string)($user->email ?? ''));
            $emailkey = strtolower($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$emailkey])) {
                continue;
            }

            $attendee = ['email' => $email];
            $displayname = fullname($user);
            if ($displayname !== '') {
                $attendee['displayName'] = $displayname;
            }
            $attendees[] = $attendee;
            $seen[$emailkey] = true;
        }

        return $attendees;
    }

    /**
     * Get the shared calendar ID from the course custom field named calendarid.
     *
     * @param int $courseid Moodle course ID.
     * @return string|null Calendar ID, or null when no non-empty value is configured.
     */
    public static function get_course_calendar_id(int $courseid): ?string {
        global $DB;

        $sql = 'SELECT d.id, d.value
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE d.instanceid = :courseid
                   AND f.shortname = :shortname
                   AND c.component = :component
                   AND c.area = :area';
        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'shortname' => 'calendarid',
            'component' => 'core_course',
            'area' => 'course',
        ]);
        foreach ($records as $record) {
            $calendarid = trim((string)$record->value);
            if ($calendarid !== '') {
                return $calendarid;
            }
        }
        return null;
    }

    /**
     * Create a service from the Google issuer selected in plugin settings.
     *
     * @return self|null Configured service, or null when Google sync is not configured.
     */
    private static function from_configuration(): ?self {
        $issuerid = (int)get_config('local_icalsender', 'googleoauthissuerid');
        if (!$issuerid) {
            return null;
        }
        $issuer = \core\oauth2\api::get_issuer($issuerid);
        if ($issuer->get('servicetype') !== 'google') {
            throw new \runtime_exception('The configured OAuth issuer is not a Google issuer.');
        }
        $client = \core\oauth2\api::get_system_oauth_client($issuer);
        if (!$client) {
            throw new \runtime_exception('The configured Google OAuth issuer has no connected system account.');
        }
        return new self($client);
    }

    /**
     * Insert an event with the Google Calendar API.
     *
     * @param string $calendarid Google calendar ID.
     * @param array $body Google event resource.
     * @return string Google event ID.
     */
    private function insert(string $calendarid, array $body): string {
        $response = $this->json_request('post', self::events_url($calendarid) . '?sendUpdates=all', $body, [200, 201]);
        if (empty($response->id)) {
            throw new \runtime_exception('Google Calendar did not return an event ID.');
        }
        return (string)$response->id;
    }

    /**
     * Update an event with the Google Calendar API.
     *
     * @param string $calendarid Google calendar ID.
     * @param string $googleeventid Google event ID.
     * @param array $body Google event resource.
     * @return void
     */
    private function update(string $calendarid, string $googleeventid, array $body): void {
        $url = self::event_url($calendarid, $googleeventid) . '?sendUpdates=all';
        $this->json_request('put', $url, $body, [200]);
    }

    /**
     * Delete an event with the Google Calendar API.
     *
     * @param string $calendarid Google calendar ID.
     * @param string $googleeventid Google event ID.
     * @return void
     */
    private function delete(string $calendarid, string $googleeventid): void {
        $url = self::event_url($calendarid, $googleeventid) . '?sendUpdates=all';
        $this->client->delete($url);
        $status = self::response_status($this->client);
        // Missing/deleted remotely already means that the desired state has been reached.
        if (!in_array($status, [200, 204, 404, 410], true)) {
            throw new \runtime_exception("Google Calendar delete failed with HTTP status {$status}.");
        }
    }

    /**
     * Send a JSON API request and validate its response.
     *
     * @param string $method Client method name (post or put).
     * @param string $url Request URL.
     * @param array $body JSON request body.
     * @param int[] $successstatuses Accepted HTTP status codes.
     * @return \stdClass Decoded response.
     */
    private function json_request(string $method, string $url, array $body, array $successstatuses): \stdClass {
        $this->client->setHeader('Content-Type: application/json; charset=utf-8');
        $response = $this->client->{$method}($url, json_encode($body, JSON_UNESCAPED_SLASHES));
        $status = self::response_status($this->client);
        if (!in_array($status, $successstatuses, true)) {
            throw new \runtime_exception("Google Calendar request failed with HTTP status {$status}.");
        }
        $decoded = json_decode((string)$response);
        if (!is_object($decoded)) {
            throw new \runtime_exception('Google Calendar returned an invalid JSON response.');
        }
        return $decoded;
    }

    /**
     * Get the HTTP status from the OAuth client's last request.
     *
     * @param \core\oauth2\client $client OAuth client.
     * @return int HTTP status code.
     */
    private static function response_status(\core\oauth2\client $client): int {
        $info = $client->get_info();
        return (int)($info['http_code'] ?? 0);
    }

    /**
     * Build an events collection URL.
     *
     * @param string $calendarid Google calendar ID.
     * @return string Events collection URL.
     */
    private static function events_url(string $calendarid): string {
        return self::API_BASE . '/calendars/' . rawurlencode($calendarid) . '/events';
    }

    /**
     * Build an individual event URL.
     *
     * @param string $calendarid Google calendar ID.
     * @param string $googleeventid Google event ID.
     * @return string Individual event URL.
     */
    private static function event_url(string $calendarid, string $googleeventid): string {
        return self::events_url($calendarid) . '/' . rawurlencode($googleeventid);
    }

    /**
     * Get a Google event mapping.
     *
     * @param int $eventid Moodle event ID.
     * @return \stdClass|false Mapping record or false.
     */
    private static function get_mapping(int $eventid) {
        global $DB;
        return $DB->get_record('local_icalsender_gcal_events', ['eventid' => $eventid]);
    }

    /**
     * Save the Google event mapping.
     *
     * @param int $eventid Moodle event ID.
     * @param string $calendarid Google calendar ID.
     * @param string $googleeventid Google event ID.
     * @return void
     */
    private static function save_mapping(int $eventid, string $calendarid, string $googleeventid): void {
        global $DB;

        $now = time();
        $mapping = self::get_mapping($eventid);
        if ($mapping) {
            $mapping->calendarid = $calendarid;
            $mapping->googleeventid = $googleeventid;
            $mapping->timemodified = $now;
            $DB->update_record('local_icalsender_gcal_events', $mapping);
            return;
        }
        $DB->insert_record('local_icalsender_gcal_events', (object)[
            'eventid' => $eventid,
            'calendarid' => $calendarid,
            'googleeventid' => $googleeventid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Keep Google failures isolated from the existing ICS notification flow.
     *
     * @param callable $operation Operation to execute.
     * @param string $action Action name used in diagnostics.
     * @param int $eventid Moodle event ID.
     * @return void
     */
    private static function safely(callable $operation, string $action, int $eventid): void {
        try {
            $operation();
        } catch (\Throwable $exception) {
            debugging(
                "icalsender: Google Calendar {$action} failed for Moodle event {$eventid}: " . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
