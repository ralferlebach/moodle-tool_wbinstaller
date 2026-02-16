<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     tool_wbinstaller
 * @category    admin
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_wbinstaller_settings', new lang_string('pluginname', 'tool_wbinstaller'));
    $componentname = 'tool_wbinstaller';

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
              $componentname . '/apitoken',
              get_string('apitoken', $componentname),
              get_string('apitokendesc', $componentname),
              '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',
              PARAM_RAW
        ));
    }

    $ADMIN->add('tools', $settings);
    $ADMIN->add(
        'development',
        new admin_externalpage(
            'tool_wbinstaller', get_string('pluginname', 'tool_wbinstaller'),
            new moodle_url('/admin/tool/wbinstaller/index.php')
        )
    );
}
