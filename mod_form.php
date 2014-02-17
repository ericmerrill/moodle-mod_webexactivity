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

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Class the creates the mod_form.
 *
 * @package    mod_webexactvity
 * @copyright  2014 Eric Merrill (merrill@oakland.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_webexactivity_mod_form extends \moodleform_mod {
    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('webexactivityname', 'webexactivity'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(false);

        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'webexactivity'));
        $mform->setDefault('starttime', (time() + (3600 * 1)));
        $mform->addRule('starttime', null, 'required', null, 'client');

        $duration = array();
        $duration[] =& $mform->createElement('text', 'duration', '', array('size' => '4'));
        $duration[] =& $mform->createElement('static', 'durationname', '', '('.get_string('minutes').')');
        $mform->addGroup($duration, 'durationgroup', get_string('duration', 'webexactivity'), array(' '), false);
        $mform->setType('duration', PARAM_INT);
        $mform->addRule('durationgroup', null, 'required', null, 'client');
        $mform->setDefault('duration', 20);
        $mform->addHelpButton('durationgroup', 'duration', 'webexactivity');

        $mform->addElement('header', 'additionalsettings', get_string('additionalsettings', 'webexactivity'));

        $mform->addElement('checkbox', 'studentdownload', get_string('studentdownload', 'webexactivity'));
        $mform->setDefault('studentdownload', 1);
        $mform->addHelpButton('studentdownload', 'studentdownload', 'webexactivity');

        $mform->addElement('checkbox', 'longavailability', get_string('longavailability', 'webexactivity'));
        $mform->setDefault('longavailability', 0);
        $mform->addHelpButton('longavailability', 'longavailability', 'webexactivity');

        $mform->addElement('date_time_selector', 'endtime', get_string('availabilityendtime', 'webexactivity'));
        $mform->setDefault('endtime', (time() + (3600 * 24 * 14)));
        $mform->addRule('starttime', null, 'required', null, 'client');
        $mform->disabledIf('endtime', 'longavailability');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Any data processing needed before the form is displayed.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$data) {
        if (isset($data['endtime'])) {
            $data['longavailability'] = 1;
        } else {
            $data['longavailability'] = 0;
            $data['endtime'] = time() + (3600 * 24 * 14);
        }
    }
}