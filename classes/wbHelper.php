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
 * Helper utilities for the Wunderbyte installer tool.
 *
 * Provides shared functionality for directory management, ZIP file extraction,
 * and recipe file discovery used by the wbInstaller and wbCheck classes.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

use moodle_exception;
use ZipArchive;

/**
 * Helper class providing utility methods for the Wunderbyte installer.
 *
 * Contains methods for cleaning temporary directories, locating and parsing
 * recipe files, and decoding/extracting base64-encoded ZIP archives.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wbHelper {
    /**
     * Recursively clean the temporary installation directory.
     *
     * Removes all files and subdirectories within the Moodle temp/zip/ directory,
     * then removes the directory itself. This ensures a clean state before and
     * after installation processes.
     *
     * @return bool Returns true if the directory was successfully removed, false if it does not exist.
     */
    public function clean_installment_directory() {
        global $CFG;
        $pluginpath = $CFG->tempdir . '/zip/';

        if (!is_dir($pluginpath)) {
            return false;
        }

        // Traverse all files and directories in depth-first order for safe deletion.
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginpath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $path = $item->getRealPath();
            if ($item->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($pluginpath);
    }

    /**
     * Scan a subdirectory for a recipe.json file and return its contents.
     *
     * Iterates over folders within the given subdirectory (relative to Moodle's tempdir),
     * skipping hidden and underscore-prefixed entries. Returns the extraction path,
     * raw JSON string, and decoded JSON content of the first valid recipe.json found.
     *
     * @param string $directorysubfolder The subdirectory path relative to Moodle's tempdir.
     * @return array Associative array with keys 'extractpath', 'jsonstring', and 'jsoncontent'.
     * @throws moodle_exception If the recipe.json file contains invalid JSON.
     */
    public function get_directory_data($directorysubfolder) {
        global $CFG;
        $directory = $CFG->tempdir . $directorysubfolder;
        $directorydata = [];
        $folders = scandir($directory);

        foreach ($folders as $folder) {
            // Skip hidden files/folders (starting with '.') and internal folders (starting with '_').
            $firstfolderchar = basename($folder)[0];
            if (
                $firstfolderchar === '.' ||
                $firstfolderchar === '_'
            ) {
                continue;
            }

            $directorydata['extractpath'] = $directory . $folder . DIRECTORY_SEPARATOR;

            // Only process actual directories containing a recipe.json file.
            if (is_dir($directorydata['extractpath'])) {
                $extractpathrecipe = $directorydata['extractpath'] . 'recipe.json';
                if (file_exists($extractpathrecipe)) {
                    $directorydata['jsonstring'] = file_get_contents($extractpathrecipe);
                    $directorydata['jsoncontent'] = json_decode($directorydata['jsonstring'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new moodle_exception('norecipefound', 'tool_wbinstaller');
                    }
                    break;
                }
            }
        }
        return $directorydata;
    }

    /**
     * Decode a base64-encoded ZIP file, save it to disk, and extract its contents.
     *
     * Validates the base64 string, decodes it, writes the resulting ZIP file to
     * the Moodle temp directory, and extracts its contents into a specified subdirectory.
     * Reports errors to the feedback array at each validation step.
     *
     * @param mixed $recipe The base64-encoded ZIP file content (optionally with data URI prefix).
     * @param array $feedback Reference to the feedback array for error reporting.
     * @param string $filename The filename to use when saving the ZIP file.
     * @param string $extractdirectory The subdirectory name for extraction within the temp/zip/ path.
     * @return string|false The full extraction path on success, or false on failure.
     */
    public function extract_save_zip_file($recipe, &$feedback, $filename, $extractdirectory) {
        global $CFG;
        $base64string = $recipe;

        // Strip optional data URI prefix (e.g., "data:application/zip;base64,").
        if (preg_match('/^data:application\/[a-zA-Z0-9\-+.]+;base64,/', $recipe)) {
            $base64string = preg_replace('/^data:application\/[a-zA-Z0-9\-+.]+;base64,/', '', $recipe);
        }

        // Validate that the string is valid base64.
        if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $base64string) === 0) {
            $feedback['wbinstaller']['error'][] =
                get_string('installervalidbase', 'tool_wbinstaller');
            return false;
        }

        // Decode the base64 string and validate the result.
        $filecontent = base64_decode($base64string, true);
        if ($filecontent === false || empty($filecontent)) {
            $feedback['wbinstaller']['error'][] =
                get_string('installerdecodebase', 'tool_wbinstaller');
            return false;
        }

        // Ensure the target directory exists and write the ZIP file.
        $pluginpath = $CFG->tempdir . '/zip/';
        $zipfilepath = $pluginpath . $filename;
        if (!is_dir($pluginpath)) {
            mkdir($pluginpath, 0777, true);
        }
        if (file_put_contents($zipfilepath, $filecontent) === false) {
            $feedback['wbinstaller']['error'][] =
                get_string('installerwritezip', 'tool_wbinstaller');
            return false;
        }

        // Free memory after writing the file.
        unset($filecontent);

        // Verify the written file exists and is readable.
        if (!file_exists($zipfilepath)) {
            $feedback['wbinstaller']['error'][] =
                get_string('installerwritezip', 'tool_wbinstaller', $zipfilepath);
            return false;
        }
        if (!is_readable($zipfilepath)) {
            $feedback['wbinstaller']['error'][] =
                get_string('installerfilenotreadable', 'tool_wbinstaller', $zipfilepath);
            return false;
        }

        // Extract the ZIP archive to the target subdirectory.
        $zip = new ZipArchive();
        $extractpath = $pluginpath . $extractdirectory;
        if ($zip->open($zipfilepath) === true) {
            if (!is_dir($extractpath)) {
                mkdir($extractpath, 0777, true);
            }
            $zip->extractTo($extractpath);
            $zip->close();
        } else {
            $feedback['wbinstaller']['error'][] =
                get_string('installerfailopen', 'tool_wbinstaller');
        }
        return $extractpath;
    }
}
