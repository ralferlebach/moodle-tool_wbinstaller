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
 * Local data installer for importing CSV and JSON data into Moodle.
 *
 * This class handles the import of local data files (CSV and JSON) as part of
 * the Wunderbyte installer process. It manages ID matching for catquiz scales,
 * courses, and quiz components, and supports nested JSON translation for
 * adaptive quiz configurations.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * Installer class for local data (CSV/JSON) within the Wunderbyte installer tool.
 *
 * Extends the base wbInstaller to provide functionality for importing local data
 * files, performing ID matching between old and new records, and translating
 * references (course IDs, scale IDs, component IDs) during the installation process.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class localdataInstaller extends wbInstaller {

    /** @var bool Flag indicating whether the current data row should be uploaded. */
    public $uploaddata;

    /** @var \tool_wbinstaller\wbCheck Reference to the parent installer holding shared matching IDs. */
    public $parent;

    /**
     * Constructor for the localdataInstaller.
     *
     * Initializes the installer with the given recipe configuration,
     * sets the progress counter to zero, and enables data upload by default.
     *
     * @param mixed $recipe The recipe configuration array containing path, translator, and matcher settings.
     */
    public function __construct($recipe) {
        $this->recipe = $recipe;
        $this->progress = 0;
        $this->uploaddata = true;
    }

    /**
     * Execute the local data installation process.
     *
     * Processes all CSV files first (for ID matching of catquiz scales),
     * then processes all JSON files (for inserting local data records into the database).
     * CSV files are used to build a translation map between old and new scale IDs.
     * JSON files contain the actual data records to be imported.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance providing shared matching IDs.
     * @return string Returns '1' on completion.
     */
    public function execute($extractpath, $parent = null) {
        $this->parent = $parent;
        $path = $extractpath . $this->recipe['path'];

        // Process CSV files first to build the scale ID matching map.
        foreach (glob("$path/*.csv") as $csvfile) {
            $this->process_csv_file($csvfile);
        }

        // Process JSON files and insert records into the database.
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
     * Pre-check the local data files before execution.
     *
     * Scans the installation path for JSON and CSV files. For JSON files,
     * it parses the contents and extracts IDs for matching (test IDs, component IDs,
     * course IDs, scale IDs). For CSV files, it registers their presence.
     * All found files are reported as success feedback.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance providing shared matching IDs.
     * @return string Returns '1' on completion.
     */
    public function check($extractpath, $parent) {
        $this->parent = $parent;
        $path = $extractpath . $this->recipe['path'];

        // Check JSON files and extract matching IDs for pre-validation.
        foreach (glob("$path/*.json") as $jsonfile) {
            $filename = basename($jsonfile);
            $fileinfo = pathinfo($filename, PATHINFO_FILENAME);
            $jsondata = file_get_contents($jsonfile);
            $decodeddata = json_decode($jsondata, true);
            $this->process_nested_json($decodeddata);
            $this->feedback['needed']['local_data']['success'][] =
                get_string('newlocaldatafilefound', 'tool_wbinstaller', $fileinfo);
        }

        // Check CSV files and report their availability.
        foreach (glob("$path/*.csv") as $csvfile) {
            $filename = basename($csvfile);
            $fileinfo = pathinfo($filename, PATHINFO_FILENAME);
            $this->feedback['needed']['local_data']['success'][] =
                get_string('newlocaldatafilefound', 'tool_wbinstaller', $fileinfo);
        }

        return '1';
    }

    /**
     * Extract and store matching IDs from nested JSON entries.
     *
     * Iterates over the decoded JSON entries and populates the matchingids array
     * with mappings for test IDs, component/quiz IDs, course IDs, and catquiz scale IDs.
     * These mappings are used later during the import process to translate old IDs
     * to their new equivalents.
     *
     * @param array $entries Array of decoded JSON entries, each containing ID fields.
     * @return void
     */
    protected function process_nested_json($entries) {
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
     * Parse a JSON file and insert its data records into the database.
     *
     * Reads a JSON file, decodes its contents, and iterates over each record.
     * For each record, it resolves the matching course and component IDs via the
     * parent's matching map, determines the correct catquiz scale, retrieves the
     * course module, translates changing columns (including nested JSON), and
     * performs a duplicate check before inserting the record into the database.
     *
     * @param string $file The absolute path to the JSON file to be processed.
     * @return bool Returns true on success, false if JSON decoding fails.
     */
    private function upload_json_file($file) {
        global $DB;

        $filename = basename($file);
        $fileinfo = pathinfo($filename, PATHINFO_FILENAME);

        // Verify file readability before attempting to process.
        if (!is_readable($file)) {
            $this->feedback['needed']['local_data']['error'][] =
                get_string('csvnotreadable', 'tool_wbinstaller', $fileinfo);
        } else {
            $filecontents = file_get_contents($file);
            $jsondata = json_decode($filecontents, true);

            // Validate JSON decoding result.
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->feedback['needed']['local_data']['error'][] =
                    get_string('jsoninvalid', 'tool_wbinstaller', $fileinfo);
                return false;
            }

            foreach ($jsondata as $row) {
                $this->uploaddata = true;
                $record = new stdClass();

                // Resolve the new component data using matched course and component IDs.
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

                // Map the old catscale ID to the newly resolved catscale ID.
                $newdata->catscaleid =
                    $this->parent->matchingids['catscales'][$row['catscaleid']];

                // Determine the module ID and course module ID for the component.
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

                // Build the record by translating changing columns and copying static values.
                foreach ($row as $key => $rowcol) {
                    if (isset($this->recipe['translator']['changingcolumn'][$key])) {
                        // Use the resolved new value if directly available.
                        if (isset($newdata->$key)) {
                            $record->$key = $newdata->$key;
                        } elseif ($this->recipe['translator']['changingcolumn'][$key]['nested']) {
                            // Translate nested JSON structures (e.g., adaptive quiz settings).
                            $record->$key = $this->update_nested_json(
                                $rowcol,
                                $newdata->catscaleid,
                                $this->recipe['translator']['changingcolumn'][$key]['keys'],
                                $moduleid,
                                $coursemoduleid
                            );
                        }
                    } elseif ($key != 'id') {
                        // Copy non-ID, non-changing fields directly.
                        $record->$key = $rowcol;
                    }
                }

                // Check for duplicates before inserting to avoid redundant data.
                $duplicatecheck = $this->duplicatecheck($fileinfo, $record);
                if ($duplicatecheck) {
                    $this->feedback['needed']['local_data']['warning'][] =
                        get_string('localdatauploadduplicate', 'tool_wbinstaller', $fileinfo);
                    break;
                } elseif ($this->uploaddata) {
                    // Insert the new record and store the old-to-new ID mapping.
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
     * Process a CSV file to build the catquiz scale ID matching map.
     *
     * Reads a semicolon-delimited CSV file containing catquiz scale definitions
     * (with 'id' and 'name' columns). For each row, it looks up the corresponding
     * scale in the local_catquiz_catscales database table by name and builds
     * a mapping from old scale IDs to newly resolved scale IDs in the parent's
     * matchingids array.
     *
     * @param string $file The absolute path to the CSV file to be processed.
     * @return bool Returns true on successful processing.
     */
    private function process_csv_file($file) {
        global $DB;

        $this->parent->matchingids['catscales'] = [];
        // Initialize the CSV reader and load the file contents.
        try {
            $csvoptions = $this->recipe['matcher'];
            $iid = \csv_import_reader::get_new_iid('wbinstaller');
            $csvreader = new \csv_import_reader($iid, 'wbinstaller');
            $csvreader->load_csv_content(
                file_get_contents($file),
                $csvoptions['encoding'] ?? '',
                $csvoptions['delimiter_name'] ?? 'semicolon'
            );
        } catch (\Exception $e) {
            $this->feedback['needed']['local_data']['error'][] =
                get_string('csvnotreadable', 'tool_wbinstaller', basename($file));
            return false;
        }

        $headers = $csvreader->get_columns();
        $csvreader->init();
        // Validate that the required 'id' and 'name' headers are present.
        if (!$headers || !in_array('id', $headers) || !in_array('name', $headers)) {
            $this->feedback['needed']['local_data']['error'][] =
                get_string('csvinvalidheaders', 'tool_wbinstaller', basename($file));
            return false;
        }

        // Iterate over each CSV row and build the scale ID translation map.
        $row = 1;

        while ($data = $csvreader->next()) {
            $row++;

            // Map row values to column headers and validate required fields.
            $data = array_combine($headers, $data);
            if (($data === false) || empty($data['id']) || empty($data['name'])) {
                $msgparams = new stdClass();
                $msgparams->line = $row;
                $msgparams->file = basename($file);
                $this->feedback['needed']['local_data']['error'][] =
                    get_string('csvmissingfields', 'tool_wbinstaller', $msgparams);
                continue;
            }

            // Look up the catquiz scale by name in the database.
            $scaleid = $DB->get_record(
                'local_catquiz_catscales',
                ['name' => $data['name']],
                'id'
            );

            $this->parent->matchingids['catscales'][$data['id']] = $scaleid->id;

            // Only store the mapping if a valid scale ID was found.
            if ($scaleid->id) {
                $this->parent->matchingids['catscales'][$data['id']] = $scaleid->id;
            }
        }

        // Release CSV reader resources.
        $csvreader->close();
        $csvreader->cleanup();

        return true;
    }

    /**
     * Check whether a duplicate record already exists in the database.
     *
     * Uses the duplicate check fields defined in the recipe's translator configuration
     * to query the target table. Returns true if a matching record is found.
     *
     * @param string $fileinfo The database table name (derived from the filename without extension).
     * @param object $record The record object to check for duplicates.
     * @return bool Returns true if a duplicate record exists, false otherwise.
     */
    public function duplicatecheck($fileinfo, $record) {
        global $DB;
        $duplicatecheck = $this->recipe['translator']['duplicatecheck'] ?? null;
        if (
            empty($fileinfo) ||
            empty($record) ||
            empty($duplicatecheck)
        ) {
            return false;
        }
        // Build the conditions array from the configured duplicate check fields.
        $conditions = [];
        foreach ($duplicatecheck as $field) {
            if (isset($record->$field)) {
                $conditions[$field] = $record->$field;
            }
        }
        return $DB->record_exists($fileinfo, $conditions);
    }

    /**
     * Translate and update a nested JSON structure with new IDs.
     *
     * Decodes a JSON string and replaces old scale IDs, module IDs, and course module IDs
     * with their new equivalents. Processes keys matching the specified changing key patterns,
     * extracts the old scale ID from the key suffix, and replaces it with the newly matched
     * scale ID. Also handles course ID translation and link rewriting within string values.
     *
     * @param string $json The JSON-encoded string containing the nested configuration data.
     * @param string $scaleid The new catquiz scale ID to assign.
     * @param array $keys Array of key prefixes identifying which JSON keys require ID translation.
     * @param int $moduleid The new module ID (e.g., for mod_adaptivequiz).
     * @param int $coursemoduleid The new course module ID.
     * @return string The re-encoded JSON string with all IDs translated to their new values.
     */
    public function update_nested_json($json, $scaleid, $keys, $moduleid, $coursemoduleid) {
        $json = json_decode($json, true);
        $translationscaleids = $this->get_scale_matcher($json, $scaleid);
        $newdata = [];

        foreach ($keys as $changingkey) {
            foreach ($json as $key => $value) {
                if ($key == 'catquiz_catscales') {
                    // Replace the top-level catscale reference with the new scale ID.
                    $json[$key] = $scaleid;
                } elseif ($key == 'module') {
                    // Replace the module reference with the new module ID.
                    $json[$key] = $moduleid;
                } elseif ($key == 'update' || $key == 'coursemodule') {
                    // Replace course module references with the new course module ID.
                    $json[$key] = $coursemoduleid;
                } elseif (str_contains($key, $changingkey)) {
                    // Extract the old scale ID from the key suffix and translate it.
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
                        // Translate course IDs or string links depending on configuration.
                        if (
                            isset($this->recipe['translator']['changingcourseids']) &&
                            str_contains($key, $this->recipe['translator']['changingcourseids'])
                        ) {
                            $newdata[$newkey] = $this->course_matching($value);
                        } else {
                            $newdata[$newkey] = $this->translate_string_links($value);
                        }
                    }
                    // Remove the old key so it is replaced by the newly generated key.
                    unset($json[$key]);
                }
            }
        }
        // Merge the translated keys back into the JSON structure.
        $json = array_merge($json, $newdata);
        return json_encode($json);
    }

    /**
     * Translate course links within feedback strings to point to the new course IDs.
     *
     * Searches for course/view.php?id=... patterns within a string value and replaces
     * old course IDs with their new equivalents from the parent's matching map.
     * Also updates the domain/root URL to match the current Moodle instance.
     * Uses a temporary uppercase 'ID=' placeholder to avoid double replacement.
     *
     * @param mixed $value The value to process. Only strings are modified; other types are returned unchanged.
     * @return mixed The value with all course links translated, or the original value if not a string.
     */
    public function translate_string_links($value) {
        global $CFG;
        if (!is_string($value)) {
            return $value;
        }

        // Find all course/view.php?id=<number> references in the string.
        preg_match_all('/course\/view\.php\?id=(\d+)/', $value, $matches);
        $ids = $matches[1];

        if (!empty($ids)) {
            foreach ($ids as $currentid) {
                $newid = $this->parent->matchingids['courses']['courses'][$currentid] ?? false;
                if ($newid) {
                    // Temporarily use uppercase 'ID=' to prevent double replacement.
                    $value = preg_replace(
                        '/id=' . $currentid . '/',
                        'ID=' . $newid,
                        $value
                    );
                    // Replace the old domain root with the current Moodle wwwroot.
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
            // Revert the temporary uppercase 'ID=' placeholders back to lowercase 'id='.
            $value = preg_replace(
                '/ID=/',
                'id=',
                $value
            );
        }
        return $value;
    }

    /**
     * Translate an array of old course IDs to their new equivalents.
     *
     * Iterates over the given array of old course IDs and resolves each one
     * using the parent's course matching map. If any course ID cannot be resolved,
     * an error is reported and further data upload for the current record is disabled.
     *
     * @param array $values Array of old course IDs to be translated.
     * @return array Array of corresponding new course IDs.
     */
    public function course_matching($values) {
        $courseids = [];
        foreach ($values as $value) {
            if (isset($this->parent->matchingids['courses']['courses'][$value])) {
                $courseids[] = $this->parent->matchingids['courses']['courses'][$value];
            } else {
                if ($this->uploaddata) {
                    $this->feedback['needed']['local_data']['error'][] =
                        get_string('courseidmismatchlocaldata', 'tool_wbinstaller');
                }
                // Disable upload for the current record due to missing course mapping.
                $this->uploaddata = false;
            }
        }
        return $courseids;
    }

    /**
     * Build a translation map from old to new catquiz scale IDs based on JSON keys.
     *
     * Parses the JSON keys to extract old scale IDs from the 'catquiz_courses_<scaleid>_<index>'
     * pattern. Then resolves each old scale ID to its new equivalent using the parent's
     * catscales matching map. Updates the parent's catscales matching map with the resolved mappings.
     *
     * @param array $json The decoded JSON array whose keys are inspected for scale ID references.
     * @param string $scaleid The new primary catquiz scale ID (currently unused in matching logic).
     * @return array Associative array mapping old scale IDs to new scale IDs.
     */
    public function get_scale_matcher($json, $scaleid) {
        $oldscales = [];

        // Extract all unique old scale IDs from keys matching the catquiz_courses pattern.
        foreach ($json as $key => $value) {
            if (preg_match('/catquiz_courses_(\d+)_\d+/', $key, $matches)) {
                $oldscales[] = (int)$matches[1];
            }
        }

        // Resolve each old scale ID to its new equivalent via the parent's matching map.
        $matcher = [];
        foreach ($oldscales as $oldscale) {
            if (array_key_exists($oldscale, $this->parent->matchingids['catscales'])) {
                $matcher[$oldscale] = $this->parent->matchingids['catscales'][$oldscale];
            } else {
                $this->feedback['needed']['local_data']['error'][] =
                    get_string('scalemismatchlocaldata', 'tool_wbinstaller', $oldscale);
                return false;
            }
        }
        
        if (count($matcher) == 0) {
            $this->uploaddata = false;
            return false;
        }

        // Update the parent's catscales map with the resolved matcher.
        $this->parent->matchingids['catscales'] = $matcher;

        return $matcher;
    }
}
