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

    /** Google OAuth token endpoint used by service accounts. */
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Scope required to create, update and delete Google Calendar events. */
    private const CALENDAR_EVENTS_SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    /** Refresh cached access tokens before Google considers them expired. */
    private const TOKEN_EXPIRY_MARGIN = 60;

    /** @var array Access tokens cached for this PHP request. */
    private static $requesttokens = [];

    /** @var array Service account credentials loaded from the Google JSON key file. */
    private $credentials;

    /** @var string|null Google Workspace user to impersonate through domain-wide delegation. */
    private $delegateduser;

    /**
     * Constructor.
     *
     * @param array $credentials Service account credentials.
     * @param string|null $delegateduser Google Workspace user to impersonate.
     */
    public function __construct(array $credentials, ?string $delegateduser = null) {
        $this->credentials = self::validate_service_account_credentials($credentials);
        $this->delegateduser = self::validate_delegated_user($delegateduser);
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
        $calendarid = null;
        self::safely(function () use ($eventrecord, $courseurl, $users, &$calendarid): void {
            $calendarid = self::get_course_calendar_id((int)$eventrecord->courseid);
            if ($calendarid === null) {
                return;
            }
            $service = self::from_configuration();
            $googleeventid = $service->insert($calendarid, self::event_body($eventrecord, $courseurl, $users));
            self::save_mapping((int)$eventrecord->id, $calendarid, $googleeventid);
        }, 'create', (int)$eventrecord->id, (int)$eventrecord->courseid, function () use (&$calendarid): ?string {
            return $calendarid;
        });
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
        $calendarid = null;
        self::safely(function () use ($eventrecord, $courseurl, $users, $createifmissing, &$calendarid): void {
            $calendarid = self::get_course_calendar_id((int)$eventrecord->courseid);
            if ($calendarid === null) {
                return;
            }
            $service = self::from_configuration();

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
        }, 'update', (int)$eventrecord->id, (int)$eventrecord->courseid, function () use (&$calendarid): ?string {
            return $calendarid;
        });
    }

    /**
     * Delete a previously synchronised Google event.
     *
     * @param int $eventid Moodle event ID.
     * @return void
     */
    public static function event_deleted(int $eventid): void {
        $calendarid = null;
        self::safely(function () use ($eventid, &$calendarid): void {
            global $DB;

            $mapping = self::get_mapping($eventid);
            if (!$mapping) {
                return;
            }
            $calendarid = $mapping->calendarid;
            $service = self::from_configuration();
            $service->delete($mapping->calendarid, $mapping->googleeventid);
            $DB->delete_records('local_icalsender_gcal_events', ['eventid' => $eventid]);
        }, 'delete', $eventid, null, function () use (&$calendarid): ?string {
            return $calendarid;
        });
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
     * Create a service from the configured Google service account.
     *
     * @return self Configured service.
     */
    private static function from_configuration(): self {
        $keypath = trim((string)get_config('local_icalsender', 'googleserviceaccountkeypath'));
        if ($keypath === '') {
            throw new \RuntimeException(
                'Google Calendar API delivery is enabled, but no Google service account key file is configured.'
            );
        }
        return new self(self::load_service_account_credentials($keypath), self::delegated_user_from_configuration());
    }

    /**
     * Get the delegated Google Workspace user from plugin configuration.
     *
     * @return string|null Delegated user email address.
     */
    private static function delegated_user_from_configuration(): ?string {
        $delegateduser = trim((string)get_config('local_icalsender', 'googledelegateduser'));
        return self::validate_delegated_user($delegateduser);
    }

    /**
     * Load service account credentials from a Google JSON key file.
     *
     * @param string $keypath Absolute path to the service account key file.
     * @return array Validated credentials.
     */
    private static function load_service_account_credentials(string $keypath): array {
        $displaypath = self::display_path($keypath);
        if (!file_exists($keypath)) {
            throw new \RuntimeException("The configured Google service account key file does not exist: {$displaypath}");
        }
        if (!is_file($keypath)) {
            throw new \RuntimeException("The configured Google service account key path is not a file: {$displaypath}");
        }
        if (!is_readable($keypath)) {
            throw new \RuntimeException(
                "The configured Google service account key file is not readable by the PHP process: {$displaypath}"
            );
        }

        $contents = file_get_contents($keypath);
        if ($contents === false) {
            throw new \RuntimeException("The configured Google service account key file could not be read: {$displaypath}");
        }

        $credentials = json_decode($contents, true);
        if (!is_array($credentials)) {
            throw new \RuntimeException("The configured Google service account key file is not valid JSON: {$displaypath}");
        }

        return self::validate_service_account_credentials($credentials);
    }

    /**
     * Make a configured server path safe and compact enough for admin-facing logs.
     *
     * @param string $path Configured server path.
     * @return string Path for diagnostics.
     */
    private static function display_path(string $path): string {
        $path = preg_replace('/[[:cntrl:]]+/', ' ', $path);
        return \core_text::substr(trim((string)$path), 0, 500);
    }

    /**
     * Validate the minimum fields required from a Google service account key.
     *
     * @param array $credentials Service account credentials.
     * @return array Validated credentials.
     */
    private static function validate_service_account_credentials(array $credentials): array {
        if (($credentials['type'] ?? '') !== 'service_account') {
            throw new \RuntimeException('The configured Google key file is not a service account key.');
        }
        foreach (['client_email', 'private_key'] as $field) {
            if (trim((string)($credentials[$field] ?? '')) === '') {
                throw new \RuntimeException("The configured Google service account key is missing {$field}.");
            }
        }
        return $credentials;
    }

    /**
     * Validate the delegated Google Workspace user.
     *
     * @param string|null $delegateduser Delegated user email address.
     * @return string|null Valid delegated user email address, or null to use the service account directly.
     */
    private static function validate_delegated_user(?string $delegateduser): ?string {
        $delegateduser = trim((string)$delegateduser);
        if ($delegateduser === '') {
            return null;
        }
        if (!filter_var($delegateduser, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException("The configured Google delegated user is not a valid email address: {$delegateduser}");
        }
        return $delegateduser;
    }

    /**
     * Insert an event with the Google Calendar API.
     *
     * @param string $calendarid Google calendar ID.
     * @param array $body Google event resource.
     * @return string Google event ID.
     */
    private function insert(string $calendarid, array $body): string {
        $response = $this->json_request(
            'post',
            self::events_url($calendarid) . '?sendUpdates=all',
            $body,
            [200, 201],
            $calendarid
        );
        if (empty($response->id)) {
            throw new \RuntimeException('Google Calendar did not return an event ID.');
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
        $this->json_request('put', $url, $body, [200], $calendarid);
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
        $client = $this->new_curl();
        $client->setHeader(['Authorization: Bearer ' . $this->get_access_token()]);
        $response = $client->delete($url);
        $status = self::response_status($client);
        // Missing/deleted remotely already means that the desired state has been reached.
        if (!in_array($status, [200, 204, 404, 410], true)) {
            throw new \RuntimeException(self::http_error_message(
                "Google Calendar delete failed with HTTP status {$status}.",
                (string)$response
            ));
        }
    }

    /**
     * Send a JSON API request and validate its response.
     *
     * @param string $method Client method name (post or put).
     * @param string $url Request URL.
     * @param array $body JSON request body.
     * @param int[] $successstatuses Accepted HTTP status codes.
     * @param string|null $calendarid Google calendar ID, when the request targets a calendar.
     * @return \stdClass Decoded response.
     */
    private function json_request(
        string $method,
        string $url,
        array $body,
        array $successstatuses,
        ?string $calendarid = null
    ): \stdClass {
        $client = $this->new_curl();
        $client->setHeader([
            'Authorization: Bearer ' . $this->get_access_token(),
            'Content-Type: application/json; charset=utf-8',
        ]);
        $response = $client->{$method}($url, json_encode($body, JSON_UNESCAPED_SLASHES));
        $status = self::response_status($client);
        if (!in_array($status, $successstatuses, true)) {
            $message = self::http_error_message(
                "Google Calendar request failed with HTTP status {$status}.",
                (string)$response
            );
            if ($status === 404 && $calendarid !== null) {
                $message .= ' ' . $this->calendar_access_hint($calendarid);
            }
            throw new \RuntimeException($message);
        }
        $decoded = json_decode((string)$response);
        if (!is_object($decoded)) {
            throw new \RuntimeException('Google Calendar returned an invalid JSON response.');
        }
        return $decoded;
    }

    /**
     * Explain the most common cause of Google Calendar 404 responses.
     *
     * @param string $calendarid Google calendar ID.
     * @return string Access diagnostic.
     */
    private function calendar_access_hint(string $calendarid): string {
        $principal = $this->delegateduser ?? (string)$this->credentials['client_email'];
        $principaltype = $this->delegateduser === null ? 'service account' : 'delegated Google user';

        return 'Calendar access hint: verify that the course calendarid '
            . self::safe_diagnostic_value($calendarid)
            . ' is the exact Google Calendar ID and that ' . $principaltype . ' '
            . self::safe_diagnostic_value($principal)
            . ' has Make changes to events permission on that calendar.';
    }

    /**
     * Get an access token for the configured service account.
     *
     * @return string OAuth access token.
     */
    private function get_access_token(): string {
        $now = time();
        $cachekey = $this->access_token_cache_key();

        if (
            isset(self::$requesttokens[$cachekey]) &&
            self::$requesttokens[$cachekey]['expires'] > $now + self::TOKEN_EXPIRY_MARGIN
        ) {
            return self::$requesttokens[$cachekey]['token'];
        }

        $cache = \cache::make('local_icalsender', 'google_access_token');
        $cached = $cache->get($cachekey);
        if (is_array($cached) && ($cached['expires'] ?? 0) > $now + self::TOKEN_EXPIRY_MARGIN) {
            self::$requesttokens[$cachekey] = $cached;
            return $cached['token'];
        }

        $token = $this->request_access_token($now);
        self::$requesttokens[$cachekey] = $token;
        $cache->set($cachekey, $token);

        return $token['token'];
    }

    /**
     * Request a fresh access token from Google's OAuth token endpoint.
     *
     * @param int $now Current Unix timestamp.
     * @return array Access token data.
     */
    private function request_access_token(int $now): array {
        $client = $this->new_curl();
        $client->setHeader(['Content-Type: application/x-www-form-urlencoded']);
        $response = $client->post(self::TOKEN_URL, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => self::create_signed_jwt($this->credentials, $now, $this->delegateduser),
        ], '', '&'));
        $status = self::response_status($client);
        if ($status !== 200) {
            throw new \RuntimeException(self::http_error_message(
                "Google service account token request failed with HTTP status {$status}.",
                (string)$response
            ));
        }

        $decoded = json_decode((string)$response);
        if (!is_object($decoded) || empty($decoded->access_token)) {
            throw new \RuntimeException('Google service account token response did not include an access token.');
        }

        $expiresin = max(1, (int)($decoded->expires_in ?? 3600));
        return [
            'token' => (string)$decoded->access_token,
            'expires' => $now + $expiresin,
        ];
    }

    /**
     * Create and sign a JWT assertion for the service account OAuth flow.
     *
     * @param array $credentials Service account credentials.
     * @param int $issuedat Unix timestamp used for iat.
     * @param string|null $delegateduser Google Workspace user to impersonate.
     * @return string Signed JWT assertion.
     */
    private static function create_signed_jwt(array $credentials, int $issuedat, ?string $delegateduser = null): string {
        $credentials = self::validate_service_account_credentials($credentials);
        $delegateduser = self::validate_delegated_user($delegateduser);

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];
        if (!empty($credentials['private_key_id'])) {
            $header['kid'] = (string)$credentials['private_key_id'];
        }

        $claims = [
            'iss' => (string)$credentials['client_email'],
            'scope' => self::CALENDAR_EVENTS_SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $issuedat,
            'exp' => $issuedat + 3600,
        ];
        if ($delegateduser !== null) {
            $claims['sub'] = $delegateduser;
        }

        $signinginput = self::base64url_encode(json_encode($header)) . '.' . self::base64url_encode(json_encode($claims));
        $privatekey = openssl_pkey_get_private((string)$credentials['private_key']);
        if ($privatekey === false) {
            throw new \RuntimeException('The configured Google service account private key is invalid.');
        }

        $signature = '';
        if (!openssl_sign($signinginput, $signature, $privatekey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('The Google service account JWT could not be signed.');
        }

        return $signinginput . '.' . self::base64url_encode($signature);
    }

    /**
     * Base64url-encode binary data without padding.
     *
     * @param string|false $data Data to encode.
     * @return string Encoded data.
     */
    private static function base64url_encode($data): string {
        if ($data === false) {
            throw new \RuntimeException('Could not JSON encode Google service account token data.');
        }
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Build the persistent cache key for this service account.
     *
     * @return string Cache key.
     */
    private function access_token_cache_key(): string {
        return sha1(
            (string)$this->credentials['client_email'] . ':'
                . (string)($this->credentials['private_key_id'] ?? '') . ':'
                . (string)($this->delegateduser ?? '')
        );
    }

    /**
     * Create a Moodle curl client.
     *
     * @return \curl HTTP client.
     */
    private function new_curl(): \curl {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        return new \curl();
    }

    /**
     * Get the HTTP status from the curl client's last request.
     *
     * @param \curl $client HTTP client.
     * @return int HTTP status code.
     */
    private static function response_status(\curl $client): int {
        $info = $client->get_info();
        return (int)($info['http_code'] ?? 0);
    }

    /**
     * Add safe Google error response details to an HTTP failure message.
     *
     * @param string $fallback Base failure message.
     * @param string $response HTTP response body.
     * @return string Diagnostic message.
     */
    private static function http_error_message(string $fallback, string $response): string {
        $summary = self::google_error_summary($response);
        if ($summary === null) {
            return $fallback;
        }
        return $fallback . ' Google error: ' . $summary;
    }

    /**
     * Extract a safe, compact error summary from a Google JSON error response.
     *
     * @param string $response HTTP response body.
     * @return string|null Error summary, or null when the response is not useful JSON.
     */
    private static function google_error_summary(string $response): ?string {
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        $parts = [];
        $error = $decoded['error'] ?? null;
        if (is_array($error)) {
            foreach (['status', 'message'] as $field) {
                if (!empty($error[$field]) && is_scalar($error[$field])) {
                    $parts[] = (string)$error[$field];
                }
            }
            foreach (($error['errors'] ?? []) as $detail) {
                if (!is_array($detail)) {
                    continue;
                }
                foreach (['reason', 'message'] as $field) {
                    if (!empty($detail[$field]) && is_scalar($detail[$field])) {
                        $parts[] = (string)$detail[$field];
                    }
                }
            }
        } else if (is_scalar($error)) {
            $parts[] = (string)$error;
        }

        if (!empty($decoded['error_description']) && is_scalar($decoded['error_description'])) {
            $parts[] = (string)$decoded['error_description'];
        }

        $summary = implode('; ', array_values(array_unique(array_filter(array_map('trim', $parts)))));
        if ($summary === '') {
            return null;
        }

        return \core_text::substr($summary, 0, 500);
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
     * @param int|null $courseid Moodle course ID when known.
     * @param callable|null $calendaridcallback Callback returning the Google calendar ID when known.
     * @return void
     */
    private static function safely(
        callable $operation,
        string $action,
        int $eventid,
        ?int $courseid = null,
        ?callable $calendaridcallback = null
    ): void {
        try {
            $operation();
        } catch (\Throwable $exception) {
            $calendarid = $calendaridcallback ? $calendaridcallback() : null;
            self::log_failure($action, $eventid, $exception, $courseid, $calendarid);
            debugging(
                "icalsender: Google Calendar {$action} failed for Moodle event {$eventid}: " . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Write a Google synchronisation failure into Moodle's standard event log.
     *
     * @param string $action Action that failed.
     * @param int $eventid Moodle event ID.
     * @param \Throwable $exception Failure details.
     * @param int|null $courseid Moodle course ID when known.
     * @param string|null $calendarid Google calendar ID when known.
     * @return void
     */
    private static function log_failure(
        string $action,
        int $eventid,
        \Throwable $exception,
        ?int $courseid,
        ?string $calendarid
    ): void {
        try {
            $context = \context_system::instance();
            if ($courseid !== null && $courseid > 0) {
                $context = \context_course::instance($courseid);
            }

            \local_icalsender\event\google_sync_failed::create([
                'context' => $context,
                'objectid' => $eventid,
                'other' => [
                    'action' => $action,
                    'courseid' => (int)($courseid ?? 0),
                    'calendarid' => (string)($calendarid ?? ''),
                    'message' => self::safe_log_message($exception->getMessage()),
                    'exceptionclass' => get_class($exception),
                ],
            ])->trigger();
        } catch (\Throwable $loggingexception) {
            debugging(
                'icalsender: could not write Google Calendar failure to Moodle logs: '
                    . $loggingexception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Keep log messages compact and free of control characters.
     *
     * @param string $message Raw exception message.
     * @return string Safe log message.
     */
    private static function safe_log_message(string $message): string {
        $message = preg_replace('/[[:cntrl:]]+/', ' ', $message);
        return \core_text::substr(trim((string)$message), 0, 1000);
    }

    /**
     * Quote a short diagnostic value for logs.
     *
     * @param string $value Raw value.
     * @return string Safe quoted value.
     */
    private static function safe_diagnostic_value(string $value): string {
        $value = preg_replace('/[[:cntrl:]]+/', ' ', $value);
        return "'" . \core_text::substr(trim((string)$value), 0, 255) . "'";
    }
}
