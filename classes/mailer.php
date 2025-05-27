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
 * Mail function used in icalsender.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mailer {

    /**
     * Sends an email with an ICS file attachment from the noreply user.
     *
     * @param object $user Recipient user object.
     * @param string $subject Email subject.
     * @param string $message Email message (HTML).
     * @param string $icsdata ICS file content.
     * @return void
     */
    public static function local_icalsender_send_ics_mail_from_noreply($user, $subject, $message, $icsdata) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/moodlelib.php');

        $filename = 'invite.ics';
        $filepath = $CFG->tempdir . '/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        file_put_contents($filepath, $icsdata);

        $from = \core_user::get_noreply_user();
        $attachments = [
            'path' => $filepath,
            'name' => $filename,
            'mimetype' => 'text/calendar',
        ];

        $success = email_to_user(
                $user,
                $from,
                $subject,
                $message,
                $message,
                $attachments['path'],
                $attachments['name'],
                $attachments['mimetype']);
        if (!$success) {
            debugging("icalsender: failed to send mail to $user->email", DEBUG_DEVELOPER);
        }
    }
}
