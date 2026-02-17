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
 * External functions and web service definitions for the Wunderbyte installer tool.
 *
 * This file registers all AJAX-callable external functions provided by the
 * tool_wbinstaller plugin, including recipe installation, progress tracking,
 * course export retrieval, recipe download, and recipe pre-check.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // Installs an uploaded recipe by processing its steps sequentially.
    'tool_wbinstaller_install_recipe' => [
        'classname' => 'tool_wbinstaller\external\install_recipe',
        'classpath' => '',
        'description' => 'Install an uploaded installer recipe.',
        'type' => 'write',
        'ajax' => true,
    ],
    // Retrieves the current installation progress for the active recipe.
    'tool_wbinstaller_get_install_progress' => [
        'classname' => 'tool_wbinstaller\external\get_install_progress',
        'classpath' => '',
        'description' => 'Get the current installation progress.',
        'type' => 'write',
        'ajax' => true,
    ],
    // Returns a list of courses that are available for export.
    'tool_wbinstaller_get_exportable_courses' => [
        'classname' => 'tool_wbinstaller\external\get_exportable_courses',
        'classpath' => '',
        'description' => 'Get a list of exportable courses.',
        'type' => 'write',
        'ajax' => true,
    ],
    // Downloads a generated recipe as a ZIP archive.
    'tool_wbinstaller_download_recipe' => [
        'classname' => 'tool_wbinstaller\external\download_recipe',
        'classpath' => '',
        'description' => 'Download a generated installer recipe.',
        'type' => 'write',
        'ajax' => true,
    ],
    // Pre-checks an uploaded recipe for validity before installation.
    'tool_wbinstaller_check_recipe' => [
        'classname' => 'tool_wbinstaller\external\check_recipe',
        'classpath' => '',
        'description' => 'Pre-check an uploaded recipe before installation.',
        'type' => 'write',
        'ajax' => true,
    ],
];
