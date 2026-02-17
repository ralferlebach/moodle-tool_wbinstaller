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
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

use local_catquiz\data\dataapi;
use stdClass;

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../../../lib/setup.php');
global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_login();

/**
 * Class tool_wbinstaller
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class localdataInstaller extends wbInstaller
{
    /** @var bool Check if data will be uploaded */
    public $uploaddata;
    /** @var \tool_wbinstaller\wbCheck Parent matching ids. */
    public $parent;

    /**
     * Entities constructor.
     * @param mixed $recipe
     */
    public function __construct($recipe)
    {
        $this->recipe = $recipe;
        $this->progress = 0;
        $this->uploaddata = true;
    }

    /**
     * Execute the installer.
     * @param string $extractpath
     * @param \tool_wbinstaller\wbCheck $parent
     * @return string
     */
    public function execute($extractpath, $parent = null)
    {
        $this->parent = $parent;
        $path = $extractpath . $this->recipe['path'];

        // Check for csv data for id matching.
        foreach (glob("$path/*.csv") as $csvfile) {
            $this->process_csv_file($csvfile);
        }

        foreach (glob("$path/*.json") as $jsonfile) {
            try {
                $this->upload_json_file($jsonfile);
            } catch (\Exception $e) {
                $this->feedback['needed']['local_data']['error'][] =
                  get_string('jsoninvalid', 'tool_wbinstaller', $jsonfile);
            }
        }

        return '1';
    }

    /**
     * Exceute the installer.
     * @param string $extractpath
     * @param \tool_wbinstaller\wbCheck $parent
     * @return string
     */
    public function check($extractpath, $parent)
    {
        $this->parent = $parent;
        $path = $extractpath . $this->recipe['path'];

        // Check for json local data to be installed.
        foreach (glob("$path/*.json") as $jsonfile) {
            $filename = basename($jsonfile);
            $fileinfo = pathinfo($filename, PATHINFO_FILENAME);
            $jsondata = file_get_contents($jsonfile);
            $decodeddata = json_decode($jsondata, true);
            $this->process_nested_json($decodeddata);
            $this->feedback['needed']['local_data']['success'][] =
                get_string('newlocaldatafilefound', 'tool_wbinstaller', $fileinfo);
        }
        
        // Check for csv data for id matching.
        foreach (glob("$path/*.csv") as $csvfile) {
            $filename = basename($csvfile);
            $fileinfo = pathinfo($filename, PATHINFO_FILENAME);
            $this->feedback['needed']['local_data']['success'][] =
                get_string('newlocaldatafilefound', 'tool_wbinstaller', $fileinfo);
        }

        return '1';
    }

    /**
     * Process nested JSON objects.
     * @param array $entries
     */
    protected function process_nested_json($entries)
    {
        foreach ($entries as $entry) {
            if (isset($entry['id'])) {
                $this->matchingids['testid'][$entry['id']] = $entry['id'];
            }
            if (isset($entry['componentid'])) {
                $this->matchingids['componentid'][$entry['componentid']] = $entry['componentid'];
                $this->matchingids['quizid'][$entry['componentid']] = $entry['componentid'];
            }
            if (isset($entry['courseid'])) {
                $this->matchingids['testid_courseid'][$entry['courseid']] = $entry['courseid'];
            }
            if (isset($entry['catscaleid'])) {
                $this->matchingids['scales'][$entry['catscaleid']] = $entry['catscaleid'];
            }
        }
    }

    /**
     * Install a single course.
     * @param string $file
     * @return int
     */
    private function upload_json_file($file)
    {
        global $DB;

        $filename = basename($file);
        $fileinfo = pathinfo($filename, PATHINFO_FILENAME);

        // Ensure the file is readable and accessible.
        if (!is_readable($file)) {
            $this->feedback['needed']['local_data']['error'][] =
                get_string('csvnotreadable', 'tool_wbinstaller', $fileinfo);
        } else {
            $filecontents = file_get_contents($file);
            $jsondata = json_decode($filecontents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->feedback['needed']['local_data']['error'][] =
                    get_string('jsoninvalid', 'tool_wbinstaller', $fileinfo);
                return false;
            }

            foreach ($jsondata as $row) {
                $this->uploaddata = true;
                $record = new stdClass();

                if (isset($this->parent->matchingids['courses']['courses'][$row['courseid']])) {
                    $newdata = $DB->get_record_sql(
                        $this->recipe['translator']['sql'],
                        [
                          'componentid' => $this->parent->matchingids['courses']['components'][$row['componentid']],
                        ]
                    );
                    if (!$newdata) {
                        $this->feedback['needed']['local_data']['error'][] =
                            get_string('noadaptivequizfound', 'tool_wbinstaller');
                        break;
                    }
                }

                // Set the right CAT scale.
                $newdata->catscaleid = 
                    $this->parent->matchingids['catscales'][$row['catscaleid']];
                             
                $modulename = substr($row['component'], strpos($row['component'], '_') + 1);
                $moduleid = $DB->get_field('modules', 'id', ['name' => $modulename]);
                
                $cm = get_coursemodule_from_instance(
                    $modulename,
                    $newdata->componentid,
                    $newdata->courseid
                );
                
                $coursemoduleid = 0;
                if ($cm) {
                    $coursemoduleid = $cm->id;
                }
                
                foreach ($row as $key => $rowcol) {
                
                    if (isset($this->recipe['translator']['changingcolumn'][$key])) {
                        if (isset($newdata->$key)) {
                            $record->$key = $newdata->$key;
                        } elseif ($this->recipe['translator']['changingcolumn'][$key]['nested']) {
                            $record->$key = $this->update_nested_json(
                                $rowcol,
                                $newdata->catscaleid,
                                $this->recipe['translator']['changingcolumn'][$key]['keys'],
                                $moduleid,
                                $coursemoduleid
                            );
                        }
                    } elseif ($key != 'id') {
                        $record->$key = $rowcol;
                    }
                }
                $duplicatecheck = $this->duplicatecheck($fileinfo, $record);
                if ($duplicatecheck) {
                    $this->feedback['needed']['local_data']['warning'][] =
                        get_string('localdatauploadduplicate', 'tool_wbinstaller', $fileinfo);
                    break;
                } elseif ($this->uploaddata) {
                    $newid = $DB->insert_record($fileinfo, $record);
                    $this->matchingids['testid'][$row['id']] = $newid;
                    $this->feedback['needed']['local_data']['success'][] =
                        get_string('localdatauploadsuccess', 'tool_wbinstaller', $fileinfo);
                } else {
                    $this->feedback['needed']['local_data']['error'][] =
                        get_string('localdatauploadmissingcourse', 'tool_wbinstaller', $fileinfo);
                }
            }
        }
        return true;
    }

    /**
     * Install a single course.
     * @param string $file
     * @return int
     */
    private function process_csv_file($file) {
        global $DB;
    
        // Open the file and ensure it is readable and accessible.
        try {
            $csvoptions = $this->recipe['matcher'];
            $iid = \csv_import_reader::get_new_iid('wbinstaller');
            $csvreader = new \csv_import_reader($iid, 'wbinstaller');
            $csvreader->load_csv_content(
                file_get_contents($file), 
                '', // $csvoptions['encoding'] ?? '',
                'semicolon' // $csvoptions['delimiter_name'] ?? 'semicolon'
            );
            $csvreader->init();
            $headers = $csvreader->get_columns();
        } catch (\Exception $e) {
            $this->feedback['needed']['local_data']['error'][] =
            get_string('csvnotreadable', 'tool_wbinstaller', basename($file));
            //return false;
        }

        // Check the headers.
        if (!$headers || !in_array('id', $headers) || !in_array('name', $headers)) {
            $this->feedback['needed']['local_data']['error'][] =
                get_string('csvinvalidheaders', 'tool_wbinstaller', basename($file));
            //return false;
        }

        // Process each row in the CSV file.
        $row = 1;
        $this->parent->matchingids['catscales'] = [];
          
        while ($data = $csvreader->next()) {
            $row ++;
            
            // Map the row data to the headers and validate.
            $data = array_combine($headers, $data);
            if (($data === false) || empty($data['id']) || empty($data['name'])) {
                $this->feedback['needed']['local_data']['error'][] =
                    get_string('csvmissingfields', 'tool_wbinstaller', ['line'=>$row, 'file'=>basename($file)]);
                continue;
            }

// Try fetching scale id from database.
            $scaleid = $DB->get_record(
                'local_catquiz_catscales',
                ['name' => $data['name']],
                'id'
            );
     
            $this->parent->matchingids['catscales'][$data['id']] = $scaleid->id;
            
            if ($scaleid->id) {
                $this->parent->matchingids['catscales'][$data['id']] = $scaleid->id;
            }
            
        }
                    
        // Cleanup the CSV reader.
        $csvreader->close();
        $csvreader->cleanup();

        return true;
    }

    /**
     * Check if course already exists.
     * @param string $fileinfo
     * @param object $record
     * @return bool
     */
    public function duplicatecheck($fileinfo, $record)
    {
        global $DB;
        $duplicatecheck = $this->recipe['translator']['duplicatecheck'] ?? null;
        if (
            empty($fileinfo) ||
            empty($record) ||
            empty($duplicatecheck)
        ) {
            return false;
        }
        $conditions = [];
        foreach ($duplicatecheck as $field) {
            if (isset($record->$field)) {
                $conditions[$field] = $record->$field;
            }
        }
        return $DB->record_exists($fileinfo, $conditions);
    }

    /**
     * Check if course already exists.
     * @param string $json
     * @param string $scaleid
     * @param array $keys
     * @param int $moduleid
     * @param int $coursemoduleid
     * @return string
     */
    public function update_nested_json($json, $scaleid, $keys, $moduleid, $coursemoduleid)
    {
        $json = json_decode($json, true);
        $translationscaleids = $this->get_scale_matcher($json, $scaleid);
        $newdata = [];
        foreach ($keys as $changingkey) {
            foreach ($json as $key => $value) {
                if ($key == 'catquiz_catscales') {
                    $json[$key] = $scaleid;
                } elseif ($key == 'module') {
                    $json[$key] = $moduleid;
                } elseif ($key == 'update' || $key == 'coursemodule') {
                    $json[$key] = $coursemoduleid;
                } elseif (str_contains($key, $changingkey)) {
                    $postfix = str_replace($changingkey . '_', '', $key);
                    $matches = explode('_', $postfix);
                    $oldid = (int)$matches[0];
                    if (
                        isset($translationscaleids[$oldid]) &&
                        count($matches) >= 1
                    ) {
                        $newid = $translationscaleids[$oldid];
                        $newkey = $changingkey . "_{$newid}";
                        if (isset($matches[1])) {
                            $newkey .= "_{$matches[1]}";
                        }
                        if (
                            isset($this->recipe['translator']['changingcourseids']) &&
                            str_contains($key, $this->recipe['translator']['changingcourseids'])
                        ) {
                            $newdata[$newkey] = $this->course_matching($value);
                        } else {
                            $newdata[$newkey] = $this->translate_string_links($value);
                        }
                    } else {
                        // $newdata[$key] = NULL;
                    }
                    unset($json[$key]);
                }
            }
        }
        $json = array_merge($json, $newdata);
        return json_encode($json);
    }

    /**
     * Translate the links inside feedbacks strings that ref to courses.
     * @param mixed $value
     * @return mixed
     */
    public function translate_string_links($value)
    {
        global $CFG;
        if (!is_string($value)) {
            return $value;
        }
        preg_match_all('/course\/view\.php\?id=(\d+)/', $value, $matches);
        $ids = $matches[1];
        if (!empty($ids)) {
            foreach ($ids as $currentid) {
                $newid = $this->parent->matchingids['courses']['courses'][$currentid] ?? false;
                if ($newid) {
                    $value = preg_replace(
                        '/id=' . $currentid . '/',
                        'ID=' . $newid,
                        $value
                    );
                    // Replace the old root with the new root in the value.
                    $value = preg_replace(
                        '/https?:\/\/[^\/]+\/(course\/view\.php\?ID=' . $newid . ')/',
                        $CFG->wwwroot . '/$1',
                        $value
                    );
                } else {
                    $this->feedback['needed']['local_data']['error'][] =
                        get_string('courseidmismatchlocaldatalink', 'tool_wbinstaller', $currentid);
                }
            }
            $value = preg_replace(
                '/ID=/',
                'id=',
                $value
            );
        }
        return $value;
    }

    /**
     * Check if course already exists.
     * @param array $values
     * @return array
     */
    public function course_matching($values)
    {
        $courseids = [];
        foreach ($values as $value) {
            if (isset($this->parent->matchingids['courses']['courses'][$value])) {
                $courseids[] = $this->parent->matchingids['courses']['courses'][$value];
            } else {
                if ($this->uploaddata) {
                    $this->feedback['needed']['local_data']['error'][] =
                        get_string('courseidmismatchlocaldata', 'tool_wbinstaller');
                }
                $this->uploaddata = false;
            }
        }
        return $courseids;
    }

    /**
     * Check if course already exists.
     * @param object $json
     * @param string $scaleid
     * @return mixed
     */
    public function get_scale_matcher($json, $scaleid)
    {
        $oldscales = [];

        foreach ($json as $key => $value) {
            if (preg_match('/catquiz_courses_(\d+)_\d+/', $key, $matches)) {
                $oldscales[] = (int)$matches[1];
            }
        }

        $matcher = [];
        foreach ($oldscales as $oldscale) {
                    
            if (array_key_exists($oldscale, $this->parent->matchingids['catscales'])) {
                $matcher[$oldscale] = $this->parent->matchingids['catscales'][$oldscale];
            } else {
                $this->feedback['needed']['local_data']['error'][] =
                get_string('scalemismatchlocaldata', 'tool_wbinstaller', $oldscale);
            }
        }
        /*
        if (count($matcher) == 0) {
            $this->uploaddata = false;
            return false;
        }
        */
        $this->parent->matchingids['catscales'] = $matcher;

        return $matcher;
    }
}
