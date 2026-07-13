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
 * Selects how Moodle calendar events are delivered to external calendars.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_delivery {
    /** Generic delivery by email with ICS attachments. */
    public const METHOD_ICS = 'ics';

    /** Delivery through the Google Calendar API. */
    public const METHOD_GOOGLE_API = 'googleapi';

    /** @var string[] Supported delivery methods. */
    private const SUPPORTED_METHODS = [
        self::METHOD_ICS,
        self::METHOD_GOOGLE_API,
    ];

    /**
     * Return the admin setting choices for event delivery.
     *
     * @return array
     */
    public static function method_options(): array {
        return [
            self::METHOD_ICS => get_string('deliverymethod_ics', 'local_icalsender'),
            self::METHOD_GOOGLE_API => get_string('deliverymethod_googleapi', 'local_icalsender'),
        ];
    }

    /**
     * Get the configured delivery method.
     *
     * @return string
     */
    public static function get_method(): string {
        $method = (string)get_config('local_icalsender', 'deliverymethod');
        if (in_array($method, self::SUPPORTED_METHODS, true)) {
            return $method;
        }
        return self::METHOD_ICS;
    }

    /**
     * Whether the plugin should use ICS email delivery.
     *
     * @return bool
     */
    public static function uses_ics(): bool {
        return self::get_method() === self::METHOD_ICS;
    }

    /**
     * Whether the plugin should use an API provider.
     *
     * @return bool
     */
    public static function uses_api(): bool {
        return !self::uses_ics();
    }

    /**
     * Create an external calendar event through the configured API provider.
     *
     * @param \stdClass $eventrecord Moodle event record.
     * @param string $courseurl URL of the Moodle course.
     * @param array $users Users to add as attendees.
     * @return void
     */
    public static function event_created(\stdClass $eventrecord, string $courseurl, array $users): void {
        switch (self::get_method()) {
            case self::METHOD_GOOGLE_API:
                google_calendar::event_created($eventrecord, $courseurl, $users);
                return;
            case self::METHOD_ICS:
            default:
                return;
        }
    }

    /**
     * Update an external calendar event through the configured API provider.
     *
     * @param \stdClass $eventrecord Moodle event record.
     * @param string $courseurl URL of the Moodle course.
     * @param array $users Users to add as attendees.
     * @param bool $createifmissing Create the external event when no mapping exists.
     * @return void
     */
    public static function event_updated(
        \stdClass $eventrecord,
        string $courseurl,
        array $users,
        bool $createifmissing = true
    ): void {
        switch (self::get_method()) {
            case self::METHOD_GOOGLE_API:
                google_calendar::event_updated($eventrecord, $courseurl, $users, $createifmissing);
                return;
            case self::METHOD_ICS:
            default:
                return;
        }
    }

    /**
     * Delete an external calendar event through the configured API provider.
     *
     * @param int $eventid Moodle event ID.
     * @return void
     */
    public static function event_deleted(int $eventid): void {
        switch (self::get_method()) {
            case self::METHOD_GOOGLE_API:
                google_calendar::event_deleted($eventid);
                return;
            case self::METHOD_ICS:
            default:
                return;
        }
    }
}
