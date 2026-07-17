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

namespace local_icalsender\event;

/**
 * Google Calendar synchronisation failed.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_sync_failed extends \core\event\base {
    /**
     * Initialise event metadata.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'event';
    }

    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventgooglesyncfailed', 'local_icalsender');
    }

    /**
     * Return the event description for Moodle logs.
     *
     * @return string
     */
    public function get_description() {
        $action = $this->other['action'] ?? 'sync';
        $message = $this->other['message'] ?? '';
        $calendarid = $this->other['calendarid'] ?? '';

        $description = "Google Calendar {$action} failed for Moodle calendar event {$this->objectid}";
        if ($calendarid !== '') {
            $description .= " on Google calendar {$calendarid}";
        }
        if ($message !== '') {
            $description .= ': ' . rtrim($message, '.');
        }

        return $description . '.';
    }

    /**
     * Validate event data before it is logged.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        foreach (['action', 'message', 'exceptionclass'] as $field) {
            if (!isset($this->other[$field])) {
                throw new \coding_exception("The '{$field}' value must be set in other.");
            }
        }
    }
}
