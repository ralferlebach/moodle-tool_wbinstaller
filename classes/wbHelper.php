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
use ZipArchive;

/**
 * Class tool_wbinstaller
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wbHelper {
    /**
     * Extract and save the zipped file.
     * @return int
     *
     */
    public function clean_installment_directory() {
        global $CFG;
        $pluginpath = $CFG->tempdir . '/zip/';
        if (!is_dir($pluginpath)) {
            return false; // Directory doesn't exist, nothing to clean.
        }
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
     * Extract and save the zipped file.
     * @param string $directorysubfolder
     * @return array
     *
     */
    public function get_directory_data($directorysubfolder) {
        global $CFG;
        $directory = $CFG->tempdir . $directorysubfolder;
        $directorydata = [];
        $folders = scandir($directory);

        foreach ($folders as $folder) {
            // Skip current and parent directory pointers.
            $firstfolderchar = basename($folder)[0];
            if (
                $firstfolderchar === '.' ||
                $firstfolderchar === '_'
            ) {
                continue;
            }

            $directorydata['extractpath'] = $directory . $folder . DIRECTORY_SEPARATOR;
            // Check if the current item is a directory.
            if (is_dir($directorydata['extractpath'])) {
                $extractpathrecipe = $directorydata['extractpath'] . 'recipe.json';
                // Check if recipe.json exists in the folder.
                if (file_exists($extractpathrecipe)) {
                    // Optionally read and process the JSON file.
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
     * Extract and save the zipped file.
     * @param mixed $recipe
     * @param array $feedback
     * @param string $filename
     * @param string $extractdirectory
     * @return string
     *
     */
    public function extract_save_zip_file($recipe, &$feedback, $filename, $extractdirectory) {
        global $CFG;
        $base64string = $recipe;
        if (preg_match('/^data:application\/[a-zA-Z0-9\-+.]+;base64,/', $recipe)) {
            $base64string = preg_replace('/^data:application\/[a-zA-Z0-9\-+.]+;base64,/', '', $recipe);
        }

        if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $base64string) === 0) {
            $feedback['wbinstaller']['error'][] =
                get_string('installervalidbase', 'tool_wbinstaller');
            return false;
        }
        $filecontent = base64_decode($base64string, true);
        if ($filecontent === false || empty($filecontent)) {
            $feedback['wbinstaller']['error'][] =
                get_string('installerdecodebase', 'tool_wbinstaller');
            return false;
        }
        $pluginpath = $CFG->tempdir . '/zip/';
        $zipfilepath = $pluginpath .  $filename;
        if (!is_dir($pluginpath)) {
            mkdir($pluginpath, 0777, true);
        }
        if (file_put_contents($zipfilepath, $filecontent) === false) {
            $feedback['wbinstaller']['error'][] =
                get_string('installerwritezip', 'tool_wbinstaller');
            return false;
        }
        unset($filecontent);
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
