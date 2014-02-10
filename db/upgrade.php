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


defined('MOODLE_INTERNAL') || die;

function xmldb_webexactivity_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2014013002) {
        echo "mod_webexactivity must be upgraded to at version 2014013002 before continuing."
        return false;
    }

    if ($oldversion < 2014020401) {

        // Set the introformat to 1 (html).
        $DB->execute('UPDATE {webexactivity} SET introformat = 1');

        // Webex Activity savepoint reached.
        upgrade_mod_savepoint(true, 2014020401, 'webexactivity');
    }

    if ($oldversion < 2014020403) {

        // Define field timemodified to be added to webexactivity_recording.
        $table = new xmldb_table('webexactivity_recording');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'visible');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Webex Activity savepoint reached.
        upgrade_mod_savepoint(true, 2014020403, 'webexactivity');
    }

    if ($oldversion < 2014020404) {

         // Define field deleted to be added to webexactivity_recording.
        $table = new xmldb_table('webexactivity_recording');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'visible');

        // Conditionally launch add field deleted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Webex Activity savepoint reached.
        upgrade_mod_savepoint(true, 2014020404, 'webexactivity');
    }

    return true;
}



