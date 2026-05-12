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
class mailer
{
    /**
     * Sends mail using the Moodle messaging system with an ICS file attachment.
     * The email is sent from the noreply user to the specified user.
     *
     * @param \stdClass $user The recipient user object.
     * @param string $subject The subject of the email.
     * @param string $body The body of the email (can be in markdown format).
     * @param string $icsdata The content of the ICS file to be attached.
     *
     */
    public static function local_icalsender_send_ics_mail_from_noreply($user, $subject, $body, $icsdata) {
        global $CFG;

        // Create a message object for the email.
        $message = new \core\message\message();
        $message->component = 'local_icalsender';
        $message->name = 'calendar_event'; // Your notification name from message.php.
        $message->userfrom = \core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here.
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml = $body;
        $message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message.
        $message->contexturl = (new \moodle_url('/course/'))->out(false); // A relevant URL for the notification.
        $message->contexturlname = 'Course list'; // Link title explaining where users get to for the contexturl.

        // Attachments.
        $usercontext = \context_user::instance($user->id);
        $filerecord = new \stdClass();
        $filerecord->contextid = $usercontext->id;
        $filerecord->component = 'user';
        $filerecord->filearea = 'private';
        $filerecord->itemid = 0;
        $filerecord->filepath = '/';
        $filerecord->filename = 'invite.ics';
        $filerecord->source = 'ics';

        $fs = get_file_storage();
        if (
            $oldfile = $fs->get_file(
                $filerecord->contextid,
                $filerecord->component,
                $filerecord->filearea,
                $filerecord->itemid,
                $filerecord->filepath,
                $filerecord->filename
            )
        ) {
            $oldfile->delete();
        }
        $storedfile = $fs->create_file_from_string($filerecord, $icsdata);
        $message->attachment = $storedfile;
        $message->attachname = 'invite.ics';

        // Actually send the message.
        $messageid = message_send($message);
        if ($messageid === false) {
            debugging('local_icalsender: message_send returned false', DEBUG_DEVELOPER);
            return false;
        }
        return;
    }
}
