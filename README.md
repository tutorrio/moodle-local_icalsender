#  Moodle Plugin iCal Sender

## Overview

The iCal Sender plugin will automatically send an mail with an ICS attachment whenever teacher/admin creates a course or group calendar event in Moodle.
The logic is triggered by listening  to following Moodle system event:
- \core\event\calendar_event_created
- \core\event\calendar_event_deleted
- \core\event\calendar_event_updated
- \core\event\user_enrolment_created
- \core\event\user_enrolment_deleted
- \core\event\group_member_added
- \core\event\group_member_removed

Each of these events will cause an email with ICS attachment to be sent to the attendee(s) of the calendar event AND to the creator(aka organizer) or of the event.
This way attendees and organizer can use their calendar application for RSVP'ing, following up who is attending,...


## Supported and unsupported scenarios

ICS invite is sent in following scenario's:

- when organizer creates/deletes 'Course' calendar event  --> to all users enrolled in course (manually or through cohorts)
- when organizer creates/deletes 'Group' calendar event  --> to all users in group
- when organizer (un)enrolling a user to/from a course that is linked to a calendar event
- when organizer adds/removes a user to/from a group that is in a course linked to a calendar event
- when organizer updates the event (like change the date/hour/location)

Currently not supported:

- other calendar event types (site, user, category) will not trigger any ICS invite
- some other Moodle plugins like 'attendance, SurveyPro' also create calendar events in Moodle. This are ignored and will not trigger any ICS invite mail


## Usage

Once installed, the plugin will automatically handle the specified events and send emails as configured. No additional setup is required.

This plugin creates 1 table in the database `ics_event_log` which will contain the calendar invites that this plugin has acted upon. It needs to keep some state of these events to handle properly eventual updates to the event.



