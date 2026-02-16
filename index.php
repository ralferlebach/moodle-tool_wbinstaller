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
 * Web interface for generating plugins.
 *
 * @package    tool_wbinstaller
 * @copyright  2024 Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');

global $USER, $CFG;

admin_externalpage_setup('tool_wbinstaller');
$pluginman = core_plugin_manager::instance();
$installer = tool_installaddon_installer::instance();

$url = new moodle_url('/admin/tool/wbinstaller/index.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'tool_wbinstaller'));
$PAGE->set_heading(get_string('pluginname', 'tool_wbinstaller'));

$step = optional_param('step', '0', PARAM_INT);
$component = optional_param('component1', '', PARAM_TEXT);
$returnurl = new moodle_url('/admin/tool/wbinstaller/index.php');

// Handle installation of plugins from ZIP files from a list of URLs.
$installzipconfirm = optional_param('installzipconfirm', false, PARAM_BOOL);

if ($installzipconfirm) {
    require_sesskey();
    require_once($CFG->libdir . '/upgradelib.php');
    require_once($CFG->libdir . '/filelib.php');

    $PAGE->set_pagelayout('maintenance');
    $PAGE->set_popup_notification_allowed(false);

    // List of plugin ZIP file URLs.
    $zipurls = [
        'https://moodle.org/plugins/download.php/32309/tool_clearbackupfiles_moodle44_2024060900.zip',
        'https://moodle.org/plugins/download.php/32294/local_chunkupload_moodle44_2024060400.zip',
        // Add more URLs as needed.
    ];
    $installable = [];

    // Download each ZIP file.
    $tempdir = make_temp_directory('tool_wbinstaller');
    foreach ($zipurls as $url) {
        $zipfile = $tempdir . '/' . basename($url);
        if (download_file_content($url, null, null, true, 300, 20, true, $zipfile)) {
            $component = $installer->detect_plugin_component($zipfile);
            $installable[] = (object)[
                'component' => $component, // Will be detected during the installation process.
                'zipfilepath' => $zipfile,
            ];
        } else {
            echo $OUTPUT->notification(get_string('filedownloadfailed', 'tool_wbinstaller', $url), 'notifyproblem');
        }
    }



    if (!empty($installable)) {
        upgrade_install_plugins(
            $installable,
            $installzipconfirm,
            get_string('installfromzip', 'tool_wbinstaller'),
            new moodle_url('/admin/tool/wbinstaller/index.php', ['installzipconfirm' => 1]));
    } else {
        echo $OUTPUT->notification(get_string('nozipfilesfound', 'tool_wbinstaller'), 'notifyproblem');
    }
}
$context = context_system::instance();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('tool_wbinstaller/initview', [
    'userid' => $USER->id,
    'contextid' => $context->id,
    'wwwroot' => $CFG->wwwroot,
]);

echo $OUTPUT->footer();
