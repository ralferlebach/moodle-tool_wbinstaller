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
 * Pre-check orchestrator for the Wunderbyte installer tool.
 *
 * This class manages the pre-check phase of the installation process,
 * validating the installation recipe and all its steps before actual
 * execution. It extracts the ZIP archive, discovers the recipe, and
 * delegates checks to each step-specific installer class.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

/**
 * Pre-check orchestrator class for the Wunderbyte installer.
 *
 * Coordinates the pre-validation of all installation steps by extracting
 * the archive, parsing the recipe, and invoking the check() method
 * on each step-specific installer. Collects feedback, matching IDs,
 * and installation progress state.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wbCheck {
    /** @var array The recipe content (base64-encoded ZIP data). */
    public $recipe;
    /** @var string The original filename of the uploaded archive. */
    public $filename;
    /** @var array Accumulated feedback messages from all installer checks. */
    public $feedback;
    /** @var array Accumulated matching ID maps from all installer checks. */
    public $matchingids;
    /** @var array Installation progress and status information. */
    public $finished;
    /** @var wbHelper Helper instance for directory and ZIP operations. */
    public $wbhelper;

    /**
     * Constructor for the wbCheck orchestrator.
     *
     * Initializes the pre-check with the given recipe data and filename,
     * and creates a helper instance for file operations.
     *
     * @param array $recipe The recipe content (base64-encoded ZIP data).
     * @param string $filename The original filename of the uploaded archive.
     */
    public function __construct($recipe, $filename) {
        $this->wbhelper = new wbHelper();
        $this->recipe = $recipe;
        $this->filename = $filename;
        $this->feedback = [];
        $this->matchingids = [];
        $this->finished = [];
    }

    /**
     * Execute the full pre-check process.
     *
     * Cleans any existing temporary files, extracts the uploaded archive,
     * runs the recipe check against all installation steps, and cleans up.
     * Returns accumulated feedback and progress information.
     *
     * @return array Associative array with 'feedback' and 'finished' keys.
     */
    public function execute() {
        $this->wbhelper->clean_installment_directory();
        raise_memory_limit(MEMORY_EXTRA);

        $extracted = $this->wbhelper->extract_save_zip_file(
            $this->recipe,
            $this->feedback,
            $this->filename,
            'precheck/'
        );

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

        $this->check_recipe($extracted);
        $this->wbhelper->clean_installment_directory();

        return [
            'feedback' => $this->feedback,
            'finished' => $this->finished,
        ];
    }

    /**
     * Validate all installation steps defined in the recipe.
     *
     * Reads the recipe.json from the extracted directory, iterates over each
     * installation step, instantiates the corresponding installer class, and
     * invokes its check() method. Collects feedback and matching IDs from
     * each installer.
     *
     * @param string $extracted The extraction path (unused directly, path is resolved via helper).
     * @return bool Returns true on completion.
     */
    public function check_recipe($extracted) {
        $directorydata = $this->wbhelper->get_directory_data('/zip/precheck/');

        if ($directorydata['jsoncontent']) {
            foreach ($directorydata['jsoncontent']['steps'] as $step) {
                foreach ($step as $steptype) {
                    // Dynamically resolve the installer class for this step type.
                    $installerclass = __NAMESPACE__ . '\\' . $steptype . 'Installer';
                    if (
                        class_exists($installerclass) &&
                        isset($directorydata['jsoncontent'][$steptype])
                    ) {
                        $instance = new $installerclass($directorydata['jsoncontent'][$steptype]);
                        $instance->check($directorydata['extractpath'], $this);
                        $this->feedback[$steptype] = $instance->get_feedback();
                        $this->matchingids[$steptype] = $instance->get_matchingids();
                    } else {
                        $this->feedback[$steptype]['needed'][$steptype]['error'][] =
                            get_string('classnotfound', 'tool_wbinstaller', $steptype);
                    }
                }
            }
        } else {
            $this->feedback['wbinstaller']['error'][] =
                get_string('installerfailopen', 'tool_wbinstaller');
        }
        return true;
    }

    /**
     * Retrieve or create the current installation step tracking record.
     *
     * Queries the tool_wbinstaller_install database table for an existing
     * progress record matching the recipe content. If no record exists,
     * creates a new one starting at step 0. Updates the finished state
     * with the current and maximum step counts.
     *
     * @param string $jsonstring The JSON recipe string used as the content identifier.
     * @param int $maxstep The total number of installation steps.
     * @return void
     */
    public function get_current_step($jsonstring, $maxstep) {
        global $DB, $USER;
        $sql = "SELECT id, currentstep
            FROM {tool_wbinstaller_install}
            WHERE " . $DB->sql_compare_text('content') . " = " . $DB->sql_compare_text(':content');

        $record = $DB->get_record_sql($sql, ['content' => $jsonstring]);

        if (!$record) {
            // Create a new progress tracking record at step 0.
            $record = new \stdClass();
            $record->userid = $USER->id;
            $record->content = $jsonstring;
            $record->currentstep = 0;
            $record->maxstep = $maxstep;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('tool_wbinstaller_install', $record);
        }

        $this->finished = [
            'status' => false,
            'currentstep' => $record->currentstep,
            'maxstep' => $maxstep,
        ];
    }
}
