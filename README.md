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

Each of these events will cause an email with ICS attachment to be sent to the attendee(s) of the calendar event AND to the creator(aka organizer) of the event.
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

Once installed, the plugin will automatically handle the specified events and send emails as configured.

### Optional Google Calendar synchronisation

Course and group events can additionally be created, updated and deleted in a shared Google calendar:

1. Create a Google OAuth 2 service under **Site administration > Server > OAuth 2 services** and connect its system account.
2. After installing or upgrading this plugin, reconnect that system account so Google grants the `calendar.events` scope requested by the plugin.
3. Select the service under **Site administration > Plugins > Local plugins > iCal Sender**.
4. Create a course custom field with the short name `calid`. Set its value on each participating course to the target Google calendar ID.
5. Give the OAuth system account permission to manage events in each target calendar.

The existing ICS email flow is unchanged. Google synchronisation is attempted only for course/group events whose course has a non-empty `calid`. Google API errors are reported through Moodle debugging and do not stop ICS delivery.

This plugin stores ICS delivery state in `local_icalsender_ics_events` and Google event mappings in `local_icalsender_gcal_events`.

