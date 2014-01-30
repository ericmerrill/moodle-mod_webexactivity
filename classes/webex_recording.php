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

namespace mod_webexactivity;

class webex_recording {
    private $recording = null;

    public function __construct($recording) {
        global $DB;

        if (is_object($recording)) {
            $this->recording = $recording;
        } else if (is_numeric($recording)) {
            $this->recording = $DB->get_record('webexactivity_recording', array('id' => $recording));
        }

        if (!$this->recording) {
            // TODO Throw exception.
            return false;
        }
    }

    public function show() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->recording->id;
        $update->visible = 1;

        $this->set_value('visible', 1);

        return $DB->update_record('webexactivity_recording', $update);
    }

    public function hide() {
        global $DB;

        $update = new \stdClass();
        $update->id = $this->recording->id;
        $update->visible = 0;

        $this->set_value('visible', 0);

        return $DB->update_record('webexactivity_recording', $update);
    }

    public function delete() {

    }

    public function get_value($name) {
        return $this->recording->$name;
    }

    public function set_value($name, $val) {
        $this->recording->$name = $val;
    }
}
