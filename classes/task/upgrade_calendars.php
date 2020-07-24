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
 * A adhoc task to make sure that calendar events are created for meetings.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2019 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexactivity\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Make calendar events.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2019 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_calendars extends \core\task\adhoc_task {

    /**
     * Do the task.
     */
    public function execute() {
        global $DB;

        mtrace("Adding calendar events for existing meetings");

        // Take recent and future meetings and make calendar events.

        // Get the count and records.
        $total = $DB->count_records('webexactivity', ['calpublish' => 1]);
        $records = $DB->get_recordset('webexactivity', ['calpublish' => 1]);

        $done = 1;
        foreach ($records as $record) {
            mtrace("  Working on meeting {$record->id} ($done of $total)");
            try {
                $meeting = \mod_webexactivity\meeting::load($record);
                $meeting->save_calendar_event();
            } catch (Exception $e) {
                // Log it and keep going.
                mtrace("    Exception thrown while working on meeting {$record->id}");
                mtrace("    " . $e->getMessage());
            }
            mtrace("  Done with meeting {$record->id}");

            $done++;
        }

        $records->close();

        mtrace("Done processing events.");
    }
}
