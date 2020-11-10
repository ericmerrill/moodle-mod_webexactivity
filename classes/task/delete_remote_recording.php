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
 * Task to download a recording from Webex into Moodle.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexactivity\task;

use mod_webexactivity\recording;
use mod_webexactivity\recording_downloader;

defined('MOODLE_INTERNAL') || die();

/**
 * Task to download a recording from Webex into Moodle.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2020 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_remote_recording extends \core\task\adhoc_task {

    /**
     * Returns default concurrency limit for this task.
     *
     * @return int default concurrency limit
     */
    protected function get_default_concurrency_limit(): int {
        return 5; // TODO - setting.
    }

    /**
     * Do the task.
     */
    public function execute() {
        $data = $this->get_custom_data();

        $recording = new recording($data->recordingid);

        // We are only using this if there is an internally stored recording.
        if ($data->forcedelete || $recording->has_internal_file(true)) {
            $recording->delete_remote_recording();
        }

    }
}
