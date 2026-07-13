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

namespace local_icalsender;

/**
 * Tests for calendar event delivery selection.
 *
 * @package    local_icalsender
 * @copyright  2025 Mario Vitale <mario.vitale@tutorrio.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_icalsender\event_delivery
 */
final class event_delivery_test extends \advanced_testcase {
    /**
     * ICS delivery is used when the setting is empty or invalid.
     */
    public function test_default_delivery_method_is_ics(): void {
        $this->resetAfterTest(true);

        $this->assertSame(event_delivery::METHOD_ICS, event_delivery::get_method());
        $this->assertTrue(event_delivery::uses_ics());
        $this->assertFalse(event_delivery::uses_api());

        set_config('deliverymethod', 'unsupported', 'local_icalsender');

        $this->assertSame(event_delivery::METHOD_ICS, event_delivery::get_method());
    }

    /**
     * Google API delivery can be selected explicitly.
     */
    public function test_google_api_delivery_method(): void {
        $this->resetAfterTest(true);

        set_config('deliverymethod', event_delivery::METHOD_GOOGLE_API, 'local_icalsender');

        $this->assertSame(event_delivery::METHOD_GOOGLE_API, event_delivery::get_method());
        $this->assertFalse(event_delivery::uses_ics());
        $this->assertTrue(event_delivery::uses_api());
    }
}
