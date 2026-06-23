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

/**
 * Language
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cancel'] = 'Hello {$a->name},<br><br>'
    . 'One of your calendar events has been cancelled: {$a->eventname} for course {$a->url}.<br><br>'
    . 'Regards,<br>Your LMS';
$string['invite'] = 'Hello {$a->name},<br><br>'
    . 'You have an event or training coming up: {$a->eventname} scheduled on {$a->date} for course {$a->url}<br>'
    . 'Please add this invite to your calendar to stay in the loop.<br><br>'
    . 'Regards,<br>Your LMS';
$string['pluginname'] = 'iCal Sender';
$string['privacy:metadata:googlecalendar'] = 'When enabled for a course, event details are sent to Google Calendar.';
$string['privacy:metadata:googlecalendar:description'] = 'The Moodle calendar event description.';
$string['privacy:metadata:googlecalendar:end'] = 'The Moodle calendar event end time.';
$string['privacy:metadata:googlecalendar:location'] = 'The Moodle calendar event location.';
$string['privacy:metadata:googlecalendar:start'] = 'The Moodle calendar event start time.';
$string['privacy:metadata:googlecalendar:summary'] = 'The Moodle calendar event name.';
$string['messageprovider:calendar_event'] = 'Calendar event';
$string['subjectcancel'] = 'Cancelling LMS event {$a->eventname}';
$string['subjectinvite'] = 'New LMS Event {$a->eventname} on {$a->date}';
$string['subjectupdate'] = 'Update LMS Event {$a->eventname} on {$a->date}';
$string['googleeventsource'] = 'View course in Moodle';
$string['googleoauthissuerid'] = 'Google OAuth 2 service';
$string['googleoauthissuerid_desc'] = 'Select the Google OAuth 2 service whose connected system account can manage shared calendars. Reconnect the system account after installing or upgrading this plugin so it grants the Google Calendar Events scope.';
$string['update'] = 'Hello {$a->name},<br><br>'
    . 'Your event or training has been updated: {$a->eventname} scheduled on {$a->date} for course {$a->url}.<br><br>'
    . 'Regards,<br>Your LMS';
