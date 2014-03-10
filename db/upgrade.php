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

defined('MOODLE_INTERNAL') || die;

/**
 * Runs the upgrade between versions.
 *
 * @param int      $oldversion Version we are starting from.
 * @return bool    True on success, false on failure.
 */
function xmldb_webexactivity_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.6.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2014022500) {
        echo "mod_webexactivity must be upgraded to at version 2014022500 before continuing.";
        return false;
    }

    if ($oldversion < 2014030300) {

        // Define field manual to be added to webexactivity_user.
        $table = new xmldb_table('webexactivity_user');
        $field = new xmldb_field('manual', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'webexuserid');

        // Conditionally launch add field manual.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // WebEx Activity savepoint reached.
        upgrade_mod_savepoint(true, 2014030300, 'webexactivity');
    }

    if ($oldversion < 2014030602) {

        // Define field hostwebexid to be added to webexactivity.
        $table = new xmldb_table('webexactivity');
        $field = new xmldb_field('hostwebexid', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'creatorwebexid');

        // Conditionally launch add field hostwebexid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $sql = 'UPDATE {webexactivity} SET hostwebexid = creatorwebexid WHERE hostwebexid IS NULL';
        $DB->execute($sql);

        // WebEx Activity savepoint reached.
        upgrade_mod_savepoint(true, 2014030602, 'webexactivity');
    }

    return true;
}
