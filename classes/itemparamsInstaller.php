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
 * Item parameters installer for importing catquiz item parameters from CSV files.
 *
 * This class handles the import of item parameter data from CSV files using
 * a configurable importer class (typically from local_catquiz) as part of
 * the Wunderbyte installer process.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

use Exception;

/**
 * Installer class for catquiz item parameters imported from CSV files.
 *
 * Extends the base wbInstaller to provide functionality for discovering CSV
 * item parameter files, validating the availability of the configured importer
 * class, and executing the CSV import via the external importer.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemparamsInstaller extends wbInstaller {
    /**
     * Constructor for the itemparamsInstaller.
     *
     * Initializes the installer with the given recipe configuration
     * and sets the progress counter to zero.
     *
     * @param array $recipe The recipe configuration array containing path and matcher settings.
     */
    public function __construct($recipe) {
        $this->recipe = $recipe;
        $this->progress = 0;
    }

    /**
     * Execute the item parameter import process.
     *
     * Iterates over all CSV files in the item parameters directory. For each file,
     * verifies that the configured importer class exists, then delegates the import
     * to the import_itemparams() method. Reports errors if the importer class
     * is not available or the import fails.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance.
     * @return int Returns 1 on completion.
     */
    public function execute($extractpath, $parent = null) {
        $path = $extractpath . $this->recipe['path'];

        foreach (glob("$path/*.csv") as $file) {
            if (
                isset($this->recipe['matcher']) &&
                class_exists($this->recipe['matcher']['name'])
            ) {
                try {
                    $this->import_itemparams($file);
                } catch (Exception $e) {
                    $this->feedback['needed'][basename($file)]['error'][] = $e;
                }
            } else {
                // Importer class not found — report error.
                $this->feedback['needed'][basename($file)]['error'][] =
                  get_string(
                      'itemparamsinstallernotfoundexecute',
                      'tool_wbinstaller',
                      $this->recipe['matcher']['name']
                  );
            }
        }
        return 1;
    }

    /**
     * Pre-check the item parameter files and importer availability.
     *
     * Scans the item parameters directory for CSV files and verifies that
     * the configured importer class exists. Reports file discovery and
     * importer availability as success or warning feedback.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance.
     * @return void
     */
    public function check(string $extractpath, \tool_wbinstaller\wbCheck $parent): void {
        $path = $extractpath . $this->recipe['path'];

        foreach (glob("$path/*.csv") as $file) {
            $this->feedback['needed'][basename($file)]['success'][] =
              get_string('itemparamsfilefound', 'tool_wbinstaller');

            if (
                isset($this->recipe['matcher']) &&
                class_exists($this->recipe['matcher']['name'])
            ) {
                // Importer class is available.
                $this->feedback['needed'][basename($file)]['success'][] =
                  get_string(
                      'itemparamsinstallerfilefound',
                      'tool_wbinstaller',
                      $this->recipe['matcher']['name']
                  );
            } else {
                // Importer class is not installed — report warning.
                $this->feedback['needed'][basename($file)]['warning'][] =
                  get_string(
                      'itemparamsinstallernotfound',
                      'tool_wbinstaller',
                      $this->recipe['matcher']['name']
                  );
            }
        }
    }

    /**
     * Import item parameters from a CSV file using the configured importer.
     *
     * Verifies that questions exist in the system, then instantiates the
     * configured importer class and executes the CSV import with the
     * delimiter, encoding, and date format settings from the recipe.
     *
     * @param string $filename The absolute path to the CSV file to import.
     * @return void
     */
    private function import_itemparams(string $filename): void {
        global $DB;
        $questions = $DB->get_records('question');

        if (!$questions) {
            // No questions found — import cannot proceed without existing questions.
            $this->feedback['needed'][$filename]['error'][] = 'No questions found in system.';
        } else if (
            isset($this->recipe['matcher']) &&
            class_exists($this->recipe['matcher']['name'])
        ) {
            $installeroptions = $this->recipe['matcher'];
            $importerclass = $installeroptions['name'];

            // Instantiate the importer and execute the CSV import.
            $importer = new $importerclass();
            $content = file_get_contents($filename);
            $importer->execute_testitems_csv_import(
                (object) [
                    'delimiter_name' => $installeroptions['delimiter_name'] ?? 'semicolon',
                    'encoding' => $installeroptions['encoding'] ?? null,
                    'dateparseformat' => $installeroptions['dateparseformat'] ?? null,
                ],
                $content
            );
            $this->feedback['needed'][basename($filename)]['success'][] =
              get_string('itemparamsinstallersuccess', 'tool_wbinstaller', $installeroptions['name']);
        } else {
            // Importer class not found during execution.
            $this->feedback['needed'][basename($filename)]['error'][] =
              get_string('itemparamsinstallernotfoundexecute', 'tool_wbinstaller');
        }
    }
}
