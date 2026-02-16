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

namespace tool_wbinstaller\exporter;

use backup;
use backup_controller;
use mod_booking\option\fields\json;
use moodle_exception;
use stdClass;

/**
 * Class tool_wbinstaller
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursesExporter {
    /**
     * Get all tests.
     * @param array $courseids
     * @return array
     */
    public static function get_courses($courseids) {
        $courses = $courseids;
        foreach ($courseids as $courseid) {
            $courses = array_merge($courses, self::get_related_courses($courseid));
        }
        self::download_course_backups(array_unique($courses));
        return $courses;
    }

    /**
     * Get all tests.
     * @param int $courseid
     * @return array
     */
    public static function get_related_courses($courseid) {
        global $DB;
        $select = 'SELECT lct.json ';
        $from = 'FROM {local_catquiz_tests} lct ';
        $where = 'WHERE lct.courseid = :courseid';
        $sql = $select . $from . $where;
        $params = ['courseid' => $courseid];
        $records = $DB->get_records_sql($sql, $params);
        return self::get_extracted_courses($records);
    }

    /**
     * Get all tests.
     * @param array $records
     * @return array
     */
    public static function get_extracted_courses($records) {
        $extractedcourses = [];
        foreach ($records as $record) {
            $record = (array)json_decode($record->json);
            foreach ($record as $key => $value) {
                if (strpos($key, 'catquiz_courses_') === 0) {
                    $extractedcourses = array_merge($extractedcourses, $value);
                }
            }
        }
        return $extractedcourses;
    }

    /**
     * Get all tests.
     * @param array $courseids
     * @return array
     */
    public static function download_course_backups($courseids) {
        global $CFG;
        $destinationdir = $CFG->dirroot . '/admin/tool/wbinstaller/download/';
        if (!is_dir($destinationdir)) {
            mkdir($destinationdir, 0777, true);
        }

        foreach ($courseids as $courseid) {
            $backupfile = $destinationdir . "backup_course_" . $courseid . ".mbz";
            $cmd = "php " . $CFG->dirroot . "/admin/cli/backup.php --courseid=$courseid --destination=$backupfile";
            exec($cmd, $output, $retval);
        }
    }

    /**
     * Get all tests.
     * @param string $source
     * @param string $dest
     * @return bool
     */
    public static function recursive_copy($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        $dir = opendir($source);
        if ($dir === false) {
            return false;
        }
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $sourcepath = $source . DIRECTORY_SEPARATOR . $file;
            $destpath = $dest . DIRECTORY_SEPARATOR . $file;
            if (is_dir($sourcepath)) {
                if (!self::recursive_copy($sourcepath, $destpath)) {
                    return false;
                }
            } else {
                if (!copy($sourcepath, $destpath)) {
                    return false;
                }
            }
        }
        closedir($dir);
        return true;
    }
}
