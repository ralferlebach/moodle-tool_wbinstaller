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
 * Main web interface for the Wunderbyte installer tool.
 *
 * This page provides the primary entry point for the tool_wbinstaller plugin.
 * It handles bulk installation of plugins from remote ZIP URLs and renders
 * the initial installer view template for user interaction.
 *
 * @package     tool_wbinstaller
 * @author      Georg Maisser
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');

global $USER, $CFG;

// Set up the admin external page and retrieve plugin manager and installer instances.
admin_externalpage_setup('tool_wbinstaller');
$pluginman = core_plugin_manager::instance();
$installer = tool_installaddon_installer::instance();

// Configure the page URL, title, and heading.
$url = new moodle_url('/admin/tool/wbinstaller/index.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'tool_wbinstaller'));
$PAGE->set_heading(get_string('pluginname', 'tool_wbinstaller'));

// Retrieve optional parameters for step navigation and component selection.
$step = optional_param('step', '0', PARAM_INT);
$component = optional_param('component1', '', PARAM_TEXT);
$returnurl = new moodle_url('/admin/tool/wbinstaller/index.php');

// Handle confirmed bulk installation of plugins from a predefined list of ZIP URLs.
$installzipconfirm = optional_param('installzipconfirm', false, PARAM_BOOL);

if ($installzipconfirm) {
    require_sesskey();
    require_once($CFG->libdir . '/upgradelib.php');
    require_once($CFG->libdir . '/filelib.php');

    // Switch to maintenance layout and suppress popup notifications during installation.
    $PAGE->set_pagelayout('maintenance');
    $PAGE->set_popup_notification_allowed(false);

    // Define the list of plugin ZIP file URLs to be installed.
    $zipurls = [
        'https://moodle.org/plugins/download.php/32309/tool_clearbackupfiles_moodle44_2024060900.zip',
        'https://moodle.org/plugins/download.php/32294/local_chunkupload_moodle44_2024060400.zip',
        // Add more URLs as needed.
    ];
    $installable = [];

    // Download each ZIP file to a temporary directory and detect its plugin component.
    $tempdir = make_temp_directory('tool_wbinstaller');
    foreach ($zipurls as $url) {
        $zipfile = $tempdir . '/' . basename($url);
        if (download_file_content($url, null, null, true, 300, 20, true, $zipfile)) {
            $component = $installer->detect_plugin_component($zipfile);
            $installable[] = (object)[
                'component' => $component,
                'zipfilepath' => $zipfile,
            ];
        } else {
            echo $OUTPUT->notification(get_string('filedownloadfailed', 'tool_wbinstaller', $url), 'notifyproblem');
        }
    }

    // Trigger the Moodle upgrade process for all successfully downloaded plugins.
    if (!empty($installable)) {
        upgrade_install_plugins(
            $installable,
            $installzipconfirm,
            get_string('installfromzip', 'tool_wbinstaller'),
            new moodle_url('/admin/tool/wbinstaller/index.php', ['installzipconfirm' => 1])
        );
    } else {
        echo $OUTPUT->notification(get_string('nozipfilesfound', 'tool_wbinstaller'), 'notifyproblem');
    }
}

// Render the initial installer view template with user and context data.
$context = context_system::instance();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('tool_wbinstaller/initview', [
    'userid' => $USER->id,
    'contextid' => $context->id,
    'wwwroot' => $CFG->wwwroot,
]);

echo $OUTPUT->footer();
