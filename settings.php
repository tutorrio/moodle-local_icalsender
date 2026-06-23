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

    $issuers = [0 => get_string('none')];
    foreach (\core\oauth2\api::get_all_issuers(true) as $issuer) {
        if ($issuer->get('servicetype') === 'google') {
            $issuers[$issuer->get('id')] = $issuer->get('name');
        }
    }
    $settings->add(new admin_setting_configselect(
        'local_icalsender/googleoauthissuerid',
        get_string('googleoauthissuerid', 'local_icalsender'),
        get_string('googleoauthissuerid_desc', 'local_icalsender'),
        0,
        $issuers
    ));
}
