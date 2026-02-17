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
 * Core installer class for the Wunderbyte installer tool.
 *
 * This file contains the main wbInstaller class that orchestrates the entire
 * installation process. It handles ZIP extraction, recipe parsing, step-by-step
 * execution of installer sub-classes, progress tracking, and status management.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

use moodle_exception;
use stdClass;
use ZipArchive;

/**
 * Main installer class that manages recipe-based installation workflows.
 *
 * This class serves as the base installer and orchestrator for the tool_wbinstaller
 * plugin. It extracts uploaded ZIP recipes, processes installation steps defined
 * in the recipe JSON, delegates execution to specialised installer sub-classes
 * (e.g., localdataInstaller, coursesInstaller), and tracks progress and feedback
 * in the database. Sub-classes extend this class to implement specific install logic.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wbInstaller {

    /** @var int ID of the installation status database record. */
    public $dbid;

    /** @var mixed The recipe content (base64-encoded ZIP data or decoded structure). */
    public $recipe;

    /** @var string The original filename of the uploaded recipe ZIP file. */
    public $filename;

    /** @var int The current installation progress step counter. */
    public $progress;

    /** @var array Accumulated feedback messages (success, warning, error) per step type. */
    public $feedback;

    /** @var array|null List of optional plugins selected by the user for installation. */
    public $optionalplugins;

    /** @var int The overall installation status (0 = pending, 1 = in progress, 2 = error). */
    public $status;

    /** @var int Timestamp indicating whether an upgrade is currently running (0 = not running). */
    public $upgraderunning;

    /** @var array Associative array mapping old IDs to new IDs across different entity types. */
    public $matchingids;

    /** @var mixed Reference to the parent installer instance for shared state access. */
    public $parent;

    /** @var wbHelper Helper instance providing file and directory utility functions. */
    public $wbhelper;

    /**
     * Constructor for the wbInstaller class.
     *
     * Initializes the installer with the given recipe data, filename, and optional
     * plugins list. Sets up the helper instance and initializes all state properties
     * to their default values.
     *
     * @param string $recipe The recipe content (typically base64-encoded ZIP data).
     * @param string|null $filename The original filename of the uploaded recipe.
     * @param string|null $optionalplugins JSON-encoded list of optional plugins to install.
     */
    public function __construct($recipe, $filename = null, $optionalplugins = null) {
        $this->wbhelper = new wbHelper();
        $this->filename = $filename;
        $this->recipe = $recipe;
        $this->progress = 0;
        $this->feedback = [];
        $this->optionalplugins = json_decode($optionalplugins);
        $this->status = 0;
        $this->upgraderunning = 0;
        $this->matchingids = [];
    }

    /**
     * Increment the installation progress counter by one step.
     *
     * @return void
     */
    public function add_step() {
        $this->progress++;
    }

    /**
     * Execute the full installation process for the uploaded recipe.
     *
     * Cleans the installation directory, extracts the uploaded ZIP file,
     * processes the recipe steps, and returns a result array containing
     * feedback messages and the finished status. If extraction fails,
     * returns immediately with error feedback.
     *
     * @param string $extractpath The base extraction path (unused, extraction is handled internally).
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance for shared state.
     * @return array Associative array with 'feedback', 'status', and 'finished' keys.
     */
    public function execute($extractpath, $parent = null) {
        $this->wbhelper->clean_installment_directory();
        raise_memory_limit(MEMORY_EXTRA);

        // Extract the uploaded ZIP file to the temporary extraction directory.
        $extracted = $this->wbhelper->extract_save_zip_file(
            $this->recipe,
            $this->feedback,
            $this->filename,
            'extracted/'
        );

        // If extraction produced errors, set error status.
        if (isset($this->feedback['wbinstaller']['error'])) {
            $this->set_status(2);
        }

        // Return early if extraction failed entirely.
        if (!$extracted) {
            return [
                'feedback' => $this->feedback,
                'finished' => [
                    'status' => false,
                    'currentstep' => 0,
                    'maxstep' => 0,
                ],
            ];
        }

        // Execute the recipe steps and clean up afterwards.
        $response = $this->execute_recipe($extracted);
        $this->wbhelper->clean_installment_directory();
        return $response;
    }

    /**
     * Process and execute the recipe steps from the extracted directory.
     *
     * Reads the recipe JSON from the extraction directory, determines the current
     * installation step, and iterates over the step types defined for that step.
     * For each step type, it instantiates the corresponding installer sub-class,
     * delegates execution, collects feedback and matching IDs, and updates the
     * overall status. After processing, advances the step counter.
     *
     * @param string $extracted The path to the extracted recipe directory.
     * @return array Associative array with 'feedback', 'status', and 'finished' keys.
     */
    public function execute_recipe($extracted) {
        $directorydata = $this->wbhelper->get_directory_data('/zip/extracted/');

        // Determine which step to execute based on the persisted progress.
        $currentstep = $this->get_current_step(
            $directorydata['jsonstring'],
            count($directorydata['jsoncontent']['steps'])
        );

        // Iterate over each step type in the current installation step.
        foreach ($directorydata['jsoncontent']['steps'][$currentstep] as $steptype) {
            $installerclass = __NAMESPACE__ . '\\' . $steptype . 'Installer';
            if (
                class_exists($installerclass) &&
                isset($directorydata['jsoncontent'][$steptype])
            ) {
                // Instantiate the specialised installer and execute it.
                $instance = new $installerclass($directorydata['jsoncontent'][$steptype]);
                if ($instance !== null) {
                    $instance->execute($directorydata['extractpath'], $this);

                    // Propagate upgrade-running flag if any sub-installer triggers an upgrade.
                    if ($instance->upgraderunning != 0) {
                        $this->upgraderunning = $instance->upgraderunning;
                    }

                    // Collect feedback and matching IDs from the sub-installer.
                    $this->feedback[$steptype] = $instance->get_feedback();
                    $this->matchingids[$steptype] = $instance->get_matchingids();
                    $this->set_status($instance->get_status());
                } else {
                    $this->feedback[$steptype]['needed'][$steptype]['error'][] =
                        get_string('classnotfound', 'tool_wbinstaller', 'TESTING');
                }
            } else {
                $this->feedback[$steptype]['needed'][$steptype]['error'][] =
                    get_string('classnotfound', 'tool_wbinstaller', $steptype);
            }
        }

        // Advance the step counter and determine whether installation is complete.
        $finished = $this->set_current_step($directorydata['jsonstring']);

        return [
            'feedback' => $this->feedback,
            'status' => $this->status,
            'finished' => $finished,
        ];
    }

    /**
     * Advance the current installation step and persist the progress.
     *
     * Retrieves the current step record from the database by matching the recipe
     * JSON content. Increments the step counter and either updates the record or
     * deletes it if all steps have been completed.
     *
     * @param string $jsonstring The JSON string identifying the current installation.
     * @return array Associative array with 'status' (bool), 'currentstep', and 'maxstep' keys.
     */
    public function set_current_step($jsonstring): array {
        global $DB, $USER;

        // Find the installation progress record by matching the recipe content.
        $sql = "SELECT id, currentstep, maxstep
            FROM {tool_wbinstaller_install}
            WHERE " . $DB->sql_compare_text('content') . " = " . $DB->sql_compare_text(':content');

        $record = $DB->get_record_sql($sql, ['content' => $jsonstring]);
        $record->currentstep += 1;

        $finished = [
            'status' => false,
            'currentstep' => $record->currentstep,
            'maxstep' => $record->maxstep,
        ];

        if ($record->currentstep == $record->maxstep) {
            // All steps completed; remove the tracking record.
            $DB->delete_records('tool_wbinstaller_install', ['id' => $record->id]);
            $finished['status'] = true;
        } else {
            // More steps remain; update the tracking record.
            $DB->update_record('tool_wbinstaller_install', $record);
        }

        return $finished;
    }

    /**
     * Retrieve or initialise the current installation step from the database.
     *
     * Looks up the installation progress record by matching the recipe JSON content.
     * If no record exists, creates a new one with step 0 and the given maximum step count.
     *
     * @param string $jsonstring The JSON string identifying the current installation.
     * @param int $maxstep The total number of installation steps defined in the recipe.
     * @return int The current step index (zero-based).
     */
    public function get_current_step($jsonstring, $maxstep): int {
        global $DB, $USER;

        // Search for an existing progress record matching the recipe content.
        $sql = "SELECT id, currentstep
            FROM {tool_wbinstaller_install}
            WHERE " . $DB->sql_compare_text('content') . " = " . $DB->sql_compare_text(':content');

        $record = $DB->get_record_sql($sql, ['content' => $jsonstring]);
        if ($record) {
            return $record->currentstep;
        }

        // No existing record found; create a new progress tracking record.
        $newrecord = new stdClass();
        $newrecord->userid = $USER->id;
        $newrecord->content = $jsonstring;
        $newrecord->currentstep = 0;
        $newrecord->maxstep = $maxstep;
        $newrecord->timecreated = time();
        $newrecord->timemodified = time();

        $DB->insert_record('tool_wbinstaller_install', $newrecord);
        return 0;
    }

    /**
     * Save the initial installation progress record to the database.
     *
     * Creates a new record in the tool_wbinstaller_install table to persist
     * the current installation state, including user, filename, recipe content,
     * and progress counters.
     *
     * @return int Always returns 1 on success.
     */
    public function save_install_progress() {
        global $DB, $USER;

        $record = new stdClass();
        $record->userid = $USER->id;
        $record->filename = $this->filename;
        $record->content = $this->recipe;
        $record->progress = $this->progress;
        $record->subprogress = 0;
        $record->status = 0;
        $record->timecreated = time();
        $record->timemodified = time();
        $this->dbid = $DB->insert_record('tool_wbinstaller_install', $record);

        return 1;
    }

    /**
     * Update the installation progress in the database.
     *
     * Increments the progress counter (unless a status flag is provided) and
     * updates the corresponding database record with the new progress value,
     * status, and modification timestamp.
     *
     * @param string $progresstype The progress field name to update (e.g., 'progress' or 'subprogress').
     * @param int|null $status The status value to set (0 = in progress, non-zero = completed/error).
     * @return int Always returns 1 on success.
     * @throws moodle_exception If the installation record cannot be found.
     */
    public function update_install_progress($progresstype, $status = 0) {
        global $DB;

        // Only increment the step counter if no explicit status is being set.
        if (!$status) {
            $this->add_step();
        }

        if ($record = $DB->get_record('tool_wbinstaller_install', ['id' => $this->dbid])) {
            $record->$progresstype = $this->progress;
            $record->timemodified = time();
            $record->status = $status;
            $DB->update_record('tool_wbinstaller_install', $record);
        } else {
            throw new moodle_exception('recordnotfound', 'tool_wbinstaller', '', $this->dbid);
        }

        return 1;
    }

    /**
     * Retrieve the current installation progress for a given filename.
     *
     * Queries the database for the most recent installation record matching
     * the given filename and returns its progress and sub-progress values.
     *
     * @param string $filename The filename of the recipe to look up.
     * @return object An object with 'progress' and 'subprogress' properties.
     * @throws moodle_exception If no matching installation record is found.
     */
    public static function get_install_progress($filename) {
        global $DB;

        $sql = "SELECT progress, subprogress FROM {tool_wbinstaller_install} ";
        $where = "WHERE filename = ? ORDER BY timecreated DESC LIMIT 1";
        $record = $DB->get_record_sql($sql . $where, [$filename]);

        if ($record) {
            return $record;
        } else {
            throw new moodle_exception('recordnotfound', 'tool_wbinstaller', '', $filename);
        }
    }

    /**
     * Return the accumulated feedback messages from the installation process.
     *
     * @return array Associative array of feedback messages grouped by step type and severity.
     */
    public function get_feedback() {
        return $this->feedback;
    }

    /**
     * Return the collected ID matching map from the installation process.
     *
     * The matching map contains old-to-new ID translations for courses,
     * components, scales, and other entities processed during installation.
     *
     * @return array Associative array mapping entity types to their old-to-new ID maps.
     */
    public function get_matchingids() {
        return $this->matchingids;
    }

    /**
     * Set the overall installation status, only escalating to higher severity.
     *
     * The status is only updated if the new value exceeds the current value,
     * ensuring that an error status is never downgraded by a subsequent success.
     *
     * @param int $status The new status value to set (higher values indicate more severe states).
     * @return void
     */
    public function set_status($status) {
        if ($this->status < $status) {
            $this->status = $status;
        }
    }

    /**
     * Return the current overall installation status.
     *
     * @return int The current status value (0 = pending, 1 = in progress, 2 = error).
     */
    public function get_status() {
        return $this->status;
    }
}
