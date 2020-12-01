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

/**
 * A testing class for mod_webexactivity\webex.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexactivity;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/webexactivity/tests/helper.php');

class webex_test extends \webexactivity_testcase {

    public function test_username_excluded_from_delete() {
        $this->resetAfterTest();

        unset_config('deleteexcludeusers', 'webexactivity');

        $this->assertFalse(webex::username_excluded_from_delete('anything'));

        // Need to re-null the static object.
        $this->set_protected_property(webex::class, 'deleteexcludeusers', null);
        // Try empty.
        $value = "";
        set_config('deleteexcludeusers', $value, 'webexactivity');

        $this->assertFalse(webex::username_excluded_from_delete('anything'));

        $this->set_protected_property(webex::class, 'deleteexcludeusers', null);
        // Now lets try some values.
        $value = "user1\n user2\nuser_3 \n user-4 ";
        set_config('deleteexcludeusers', $value, 'webexactivity');

        $this->assertTrue(webex::username_excluded_from_delete('user1'));
        $this->assertTrue(webex::username_excluded_from_delete('user2'));
        $this->assertTrue(webex::username_excluded_from_delete('user_3'));
        $this->assertTrue(webex::username_excluded_from_delete('user-4'));
        $this->assertFalse(webex::username_excluded_from_delete('anything'));
        $this->assertFalse(webex::username_excluded_from_delete(' user2'));
        $this->assertFalse(webex::username_excluded_from_delete('user'));
    }


}
