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


$ADMIN->add('modsettings', new admin_category('modwebexactivityfolder', new lang_string('pluginname', 'mod_webexactivity'),
        $module->is_enabled() === false));

$settings = new admin_settingpage($section, get_string('settings', 'mod_webexactivity'), 'moodle/site:config',
        $module->is_enabled() === false);


if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('apisettings', get_string('apisettings', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/url', get_string('url', 'mod_webexactivity'),
            get_string('url_help', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/siteid', get_string('siteid', 'mod_webexactivity'),
            get_string('siteid_help', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/partnerid', get_string('partnerid', 'mod_webexactivity'),
            get_string('partnerid_help', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/apiusername', get_string('apiusername', 'mod_webexactivity'),
            get_string('apiusername_help', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_configpasswordunmask('webexactivity/apipassword',
            get_string('apipassword', 'mod_webexactivity'), get_string('apipassword_help', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/prefix', get_string('prefix', 'mod_webexactivity'),
            get_string('prefix_help', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_heading('meetingsettings', get_string('meetingsettings', 'mod_webexactivity'), ''));

    $settings->add(new admin_setting_configtext('webexactivity/meetingclosegrace',
            get_string('meetingclosegrace', 'mod_webexactivity'),
            get_string('meetingclosegrace_help', 'mod_webexactivity'), '120'));

    $settings->add(new admin_setting_heading('recordingsettings', get_string('recordingsettings', 'mod_webexactivity'), ''));

    // TODO - Impliment.
    $settings->add(new admin_setting_configtext('webexactivity/recordingtrashtime',
            get_string('recordingtrashtime', 'mod_webexactivity'),
            get_string('recordingtrashtime_help', 'mod_webexactivity'), '48'));

    $settings->add(new admin_setting_configcheckbox('webexactivity/manageallrecordings',
            get_string('manageallrecordings', 'mod_webexactivity'),
            get_string('manageallrecordings_help', 'mod_webexactivity'), 0));
}

$ADMIN->add('modwebexactivityfolder', $settings);
// Tell core we already added the settings structure.
$settings = null;
