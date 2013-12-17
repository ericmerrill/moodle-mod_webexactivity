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
 * An activity to interface with WebEx.
 *
 * @package   mod_webexactvity
 * @copyright Eric Merrill (merrill@oakland.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('webexactivity/url', get_string('url', 'webexactivity'),
            get_string('url_help', 'webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/siteid', get_string('siteid', 'webexactivity'),
            get_string('siteid_help', 'webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/partnerid', get_string('partnerid', 'webexactivity'),
            get_string('partnerid_help', 'webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/apiusername', get_string('apiusername', 'webexactivity'),
            get_string('apiusername_help', 'webexactivity'), ''));

    $settings->add(new admin_setting_configpasswordunmask('webexactivity/apipassword', get_string('apipassword', 'webexactivity'),
            get_string('apipassword_help', 'webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/prefix', get_string('prefix', 'webexactivity'),
            get_string('prefix_help', 'webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/meetingclosegrace', get_string('meetingclosegrace', 'webexactivity'),
            get_string('meetingclosegrace_help', 'webexactivity'), ''));

}