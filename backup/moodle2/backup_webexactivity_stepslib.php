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
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2014 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Define the complete choice structure for backup, with file and id annotations
 */     
class backup_webexactivity_activity_structure_step extends backup_activity_structure_step {
 
    protected function define_structure() {
 
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');
 
        // Define each element separated
        $webex = new backup_nested_element('webexactivity', array('id'), array(
            'name', 'intro', 'introformat', 'type',
            'starttime', 'endtime', 'duration', 'allchat',
            'studentdownload', 'password'));
 
        // Build the tree
 
        // Define sources
 
        // Define id annotations
 
        // Define file annotations
 
        // Return the root element (choice), wrapped into standard activity structure
        return $this->prepare_activity_structure($webex);
    }
}

