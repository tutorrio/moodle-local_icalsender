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
 * Plugin settings.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_icalsender', get_string('pluginname', 'local_icalsender'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configselect(
        'local_icalsender/deliverymethod',
        get_string('deliverymethod', 'local_icalsender'),
        get_string('deliverymethod_desc', 'local_icalsender'),
        \local_icalsender\event_delivery::METHOD_ICS,
        \local_icalsender\event_delivery::method_options()
    ));

    $settings->add(new admin_setting_configtext(
        'local_icalsender/googleserviceaccountkeypath',
        get_string('googleserviceaccountkeypath', 'local_icalsender'),
        get_string('googleserviceaccountkeypath_desc', 'local_icalsender'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $settings->hide_if(
        'local_icalsender/googleserviceaccountkeypath',
        'local_icalsender/deliverymethod',
        'neq',
        \local_icalsender\event_delivery::METHOD_GOOGLE_API
    );

    $settings->add(new admin_setting_configtext(
        'local_icalsender/googledelegateduser',
        get_string('googledelegateduser', 'local_icalsender'),
        get_string('googledelegateduser_desc', 'local_icalsender'),
        '',
        PARAM_EMAIL
    ));
    $settings->hide_if(
        'local_icalsender/googledelegateduser',
        'local_icalsender/deliverymethod',
        'neq',
        \local_icalsender\event_delivery::METHOD_GOOGLE_API
    );
}
