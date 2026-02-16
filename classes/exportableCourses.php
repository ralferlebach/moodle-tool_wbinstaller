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
 * Entities Class to display list of entity records.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

use moodle_exception;
use stdClass;
use ZipArchive;

/**
 * Class tool_wbinstaller
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exportableCourses {
    /**
     * Entities constructor.
     */
    public function __construct() {
    }

    /**
     * Extract and save the zipped file.
     * @return array
     *
     */
    public static function get_courses() {
        global $DB;

        $select = 'SELECT DISTINCT c.id, c.fullname ';
        $from = 'FROM {course} c
            JOIN {course_modules} cm ON cm.course = c.id
            JOIN {modules} m ON cm.module = m.id ';
        $where = 'WHERE m.name = :modname';
        $sql = $select . $from . $where;
        $params = ['modname' => 'adaptivequiz'];

        $records = $DB->get_records_sql($sql, $params);
        $courses = [];
        foreach ($records as $record) {
            $courses[] = [
                'id' => $record->id,
                'fullname' => $record->fullname,
            ];
        }
        return $courses;
    }
}
