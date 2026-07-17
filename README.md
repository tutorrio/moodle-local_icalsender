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

## Delivery methods

The plugin supports two mutually exclusive delivery methods, configured in **Site administration > Plugins > Local plugins > iCal Sender**:

- **Generic - ICS events by email** sends calendar invitations, updates and cancellations as ICS email attachments.
- **API - Google Calendar** creates, updates and deletes events through the Google Calendar API.

The `calendarid` shared-calendar feature is only supported by the API delivery method. When the generic ICS method is selected, course `calendarid` values are ignored and no shared-calendar API calls are made.

## Google Calendar API delivery

When API delivery is configured, creating, updating or deleting a supported Moodle calendar event performs the corresponding operation in Google Calendar. The Google event contains the Moodle event name, description, location, start and end time, a link back to the Moodle course, and the relevant enrolled users as attendees.

### 1. Create and configure the Google service account

The plugin uses a Google service account JSON key to request short-lived access tokens for the Google Calendar API. It does not use Moodle's OAuth 2 system-account flow.

1. Create or select a project in the Google Cloud Console.
2. Enable the **Google Calendar API** for that project.
3. Create a service account in **IAM & Admin > Service Accounts**.
4. Enable **Domain-wide delegation** for the service account and note its OAuth 2 client ID.
5. In the Google Admin Console, authorise that client ID for the scope
   `https://www.googleapis.com/auth/calendar`.
6. Create and download a JSON key for the service account.
7. Store the JSON key file on the Moodle server outside the web root, readable by the web server user.
8. Note the service account email address from the JSON key file or Google Cloud Console.

The plugin impersonates a Google Workspace user through domain-wide delegation. The default delegated user is
`noreply@tutorrio.com`, and it can be changed in the plugin settings. For each target shared Google calendar,
open **Settings and sharing** in Google Calendar, share the calendar with the delegated user, and grant at
least **Make changes to events** permission.

Finally, go to **Site administration > Plugins > Local plugins > iCal Sender**, set **Calendar event delivery method** to **API - Google Calendar**, enter the absolute server path to the JSON key file in **Google service account key file**, and set **Google delegated user** to the Workspace user to impersonate. Leaving the key-file setting empty prevents Google API delivery from creating, updating or deleting events.

### 2. Create the `calendarid` course custom field

Google synchronisation is enabled per course through a Moodle course custom field:

1. Go to **Site administration > Courses > Course custom fields**.
2. Create a text input custom field. Its display name can be chosen freely, but its short name must be exactly `calendarid`.
3. The field does not need to be required, because courses without a Google calendar can leave it empty.
4. Edit each course that should use Google synchronisation and enter the target Google calendar ID in its `calendarid` field.

The calendar ID can be found in Google Calendar under the calendar's **Settings and sharing > Integrate calendar > Calendar ID** section. A shared calendar ID commonly looks like `example@group.calendar.google.com`.

Google API delivery is attempted only when the course has a non-empty `calendarid` value. A missing or empty field means there is no shared calendar target for that course.

### 3. Using the synchronisation

After the service account and course custom field are configured, no additional action is required from teachers:

- Creating a future Moodle course or group event creates an event in the calendar identified by `calendarid`.
- Updating the Moodle event updates the corresponding Google event.
- Deleting the Moodle event deletes the corresponding Google event.
- Changing `calendarid` and then updating a Moodle event removes it from the previous calendar and creates it in the new calendar.
- Enrolled course users are included as attendees of course events; group members are included as attendees of group events.
- User enrolment, unenrolment and group membership changes update the attendee list without creating duplicate events in the shared Google calendar.

Google Calendar notification emails are enabled for these API operations (`sendUpdates=all`). Attendees therefore receive Google notifications for event creation, updates and cancellation.

The plugin stores the Google event ID and calendar ID in `local_icalsender_gcal_events` so that later updates and deletions target the correct Google event. Clearing `calendarid` does not immediately remove events that were already synchronised. Deleting the corresponding Moodle event will still remove its mapped Google event; alternatively, move it by changing `calendarid` to another calendar ID and updating the event.

Google API failures are isolated from Moodle calendar changes. Failures are written to Moodle's standard logs
as **Google Calendar synchronisation failed** events, including the Moodle event ID, action, Google calendar ID
when known, and the safe portion of Google's error response. With Moodle developer debugging enabled, failures
are also reported with messages beginning with `icalsender: Google Calendar`.

This plugin stores ICS delivery state in `local_icalsender_ics_events` and Google event mappings in `local_icalsender_gcal_events`.
