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

class mod_webexactivity_mod_form extends \moodleform_mod {
    public function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        $mform->addElement('text', 'name', get_string('webexactivityname', 'webexactivity'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(false);

        //$mform->addElement('editor', 'agenda', get_string('agenda', 'webexactivity'),
        //        array('rows' => 10), array('context' => $this->context, 'subdirs' => true));

        //$mform->setType('agenda', PARAM_RAW); // no XSS prevention here, users must be trusted

        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'webexactivity'));
        $mform->addRule('starttime', null, 'required', null, 'client');

        $mform->addElement('text', 'duration', get_string('duration', 'webexactivity'), array('size' => '4'));
        $mform->setType('duration', PARAM_INT);
        $mform->addRule('duration', null, 'required', null, 'client');
        $mform->setDefault('duration', 20);

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}