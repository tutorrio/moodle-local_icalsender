# How To Create A Google Service Account

This plugin uses a Google service account with domain-wide delegation to create, update and delete events in shared Google calendars as a configured Google Workspace user. Each target calendar must be shared with that delegated user.

## 1. Create Or Select A Google Cloud Project

1. Open the Google Cloud Console: https://console.cloud.google.com/
2. Select an existing project, or create a new project.
3. Make sure you are working in the correct project before continuing.

## 2. Enable The Google Calendar API

1. In Google Cloud Console, go to **APIs & Services > Library**.
2. Search for **Google Calendar API**.
3. Open **Google Calendar API**.
4. Click **Enable**.

## 3. Create The Service Account

1. Go to **IAM & Admin > Service Accounts**.
2. Click **Create service account**.
3. Enter a service account name, for example `moodle-icalsender`.
4. Click **Create and continue**.
5. You do not need to grant project roles for this plugin.
6. Click **Continue**.
7. Optional: add users who are allowed to manage the service account.
8. Click **Done**.

## 4. Create The JSON Key

1. In **IAM & Admin > Service Accounts**, open the service account you created.
2. Go to the **Keys** tab.
3. Click **Add key > Create new key**.
4. Select **JSON**.
5. Click **Create**.
6. Save the downloaded JSON file securely.

Keep this JSON file private. It contains the private key for the service account.

## 5. Enable Domain-Wide Delegation

1. In **IAM & Admin > Service Accounts**, open the service account you created.
2. Enable **Domain-wide delegation** for the service account and note its OAuth 2 client ID.
3. In the Google Admin Console, authorise that client ID for this scope:

```text
https://www.googleapis.com/auth/calendar
```

## 6. Install The Key On The Moodle Server

1. Copy the JSON key file to the Moodle server.
2. Store it outside the web root.
3. Make it readable by the web server user.
4. Note the absolute path to the file, for example:

```text
/var/moodle-secrets/moodle-icalsender-service-account.json
```

## 7. Share Google Calendars With The Delegated User

1. Open Google Calendar.
2. Open the target calendar's **Settings and sharing** page.
3. Under **Share with specific people or groups**, add the delegated Google Workspace user.
4. Grant **Make changes to events** permission.
5. Repeat this for every calendar that Moodle should manage.

The delegated user is the **Google delegated user** configured in Moodle. The default is:

```text
noreply@tutorrio.com
```

> If the option **Make changes to events** is not clickable, ask your Google Workspace admin to allow outsiders to change calendars under the external sharing options.

## 8. Configure The Plugin In Moodle

1. Go to **Site administration > Plugins > Local plugins > iCal Sender**.
2. Set **Calendar event delivery method** to **Google Calendar API**.
3. Set **Google service account key file** to the absolute server path of the JSON key file.
4. Set **Google delegated user** to the Workspace user that has access to the target calendars.
5. Save changes.

Courses still need a `calendarid` course custom field value. That value must be the Google Calendar ID of a calendar shared with the delegated user.
