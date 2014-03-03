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

namespace mod_webexactivity;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/tablelib.php');

/**
 * A table to show and manage WebEx recordings.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2014 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_recordings_table extends \table_sql implements \renderable {
    /**
     * Determine output for the name column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_name($recording) {
        if (\core_text::strlen($recording->name) > 60) {
            return \core_text::substr($recording->name, 0, 55).'&#8230;';
        }
        return $recording->name;
    }

    /**
     * Determine output for the timecreated column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_timecreated($recording) {
        $format = get_string('strftimedatetimeshort', 'langconfig');
        return userdate($recording->timecreated, $format);
    }

    /**
     * Determine output for the duration column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_duration($recording) {
        if ($this->is_downloading()) {
            return $recording->duration;
        } else {
            return format_time($recording->duration);
        }
    }

    /**
     * Determine output for the filesize column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_filesize($recording) {
        if ($this->is_downloading()) {
            return $recording->filesize;
        } else {
            return display_size($recording->filesize);
        }
    }

    /**
     * Determine output for the fileurl column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_fileurl($recording) {
        if ($this->is_downloading()) {
            return $recording->fileurl;
        } else {
            return '<a href="'.$recording->fileurl.'">Download</a>';
        }
    }

    /**
     * Determine output for the streamurl column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_streamurl($recording) {
        if ($this->is_downloading()) {
            return $recording->streamurl;
        } else {
            return '<a href="'.$recording->streamurl.'">Stream</a>';
        }
    }

    /**
     * Determine output for the deletion column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_deleted($recording) {
        if ($this->is_downloading()) {
            return $recording->deleted;
        } else {
            if ($recording->deleted == 0) {
                $params = array('action' => 'delete', 'recordingid' => $recording->id);
                $urlobj = new \moodle_url($this->baseurl, $params);
                $params = array('url' => $urlobj->out(false));
                return get_string('deletelink', 'mod_webexactivity', $params);
            } else {
                $out = '';

                $holdtime = get_config('webexactivity', 'recordingtrashtime');

                $timeleft = $recording->deleted - (time() - ($holdtime * 3600));
                if ($timeleft < 0) {
                    $timeleft = 0;
                }

                if ($timeleft > 0) {
                    $params = array('time' => format_time($timeleft));
                    $out .= get_string('deletionin', 'mod_webexactivity', $params);
                } else {
                    $out .= get_string('deletionsoon', 'mod_webexactivity');
                }

                $params = array('action' => 'undelete', 'recordingid' => $recording->id);
                $urlobj = new \moodle_url($this->baseurl, $params);
                $params = array('url' => $urlobj->out(false));
                $out .= get_string('undeletelink', 'mod_webexactivity', $params);

                return $out;
            }
        }
    }

    /**
     * Determine output for the webexid column.
     *
     * @param stdClass   $recording The recording row being worked on.
     * @return string    The output to display.
     */
    public function col_webexid($recording) {
        if (isset($recording->webexid)) {
            $cm = get_coursemodule_from_instance('webexactivity', $recording->webexid);
            if ($cm) {
                $returnurl = new \moodle_url('/mod/webexactivity/view.php', array('id' => $cm->id));
                if ($this->is_downloading()) {
                    return $returnurl->out(false);
                } else {
                    return '<a href="'.$returnurl->out(false).'">Activity</a>';
                }
            } else {
                return '-';
            }
        } else {
            return '-';
        }
    }

    /**
     * Get any extra classes names to add to this row in the HTML.
     *
     * @param $row stdClass The data for this row.
     * @return string       Class added to the class="" attribute of the tr.
     */
    public function get_row_class($row) {
        if ($row->deleted == 0) {
            return '';
        } else {
            return 'webexrecordingdeleted';
        }
    }
}
