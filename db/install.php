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
 * Post-installation script for the tool_wbinstaller plugin.
 *
 * This file is executed after the plugin's database schema has been installed.
 * It creates the 'installmanager' role with appropriate context levels and
 * capabilities, and sets default plugin configuration values.
 *
 * @package     tool_wbinstaller
 * @category    upgrade
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom code to be run on first installation of the plugin.
 *
 * Creates the 'installmanager' role if it does not already exist, assigns it
 * to the system context level, grants the required capabilities
 * (caninstall, canexport), and inserts default configuration settings
 * into the config_plugins table.
 *
 * @return bool Always returns true on successful installation.
 */
function xmldb_tool_wbinstaller_install() {
    global $DB;

    // Check whether the 'installmanager' role already exists.
    $role = $DB->get_record('role', ['shortname' => 'installmanager']);
    if (empty($role->id)) {
        // Determine the next available sort order for the new role.
        $sql = "SELECT MAX(sortorder)+1 AS id FROM {role}";
        $max = $DB->get_record_sql($sql, []);

        $role = (object) [
            'name' => 'Install Manager',
            'shortname' => 'installmanager',
            'description' => get_string('wbinstallerroledescription', 'tool_wbinstaller'),
            'sortorder' => $max->id,
            'archetype' => '',
        ];
        $role->id = $DB->insert_record('role', $role);
    }

    // Ensure the role is assignable at the system context level.
    $chk = $DB->get_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_SYSTEM]);
    if (empty($chk->id)) {
        $DB->insert_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_SYSTEM]);
    }

    // Grant the required capabilities to the installmanager role.
    $ctx = \context_system::instance();
    $caps = [
        'tool/wbinstaller:caninstall',
        'tool/wbinstaller:canexport',
    ];
    foreach ($caps as $cap) {
        $chk = $DB->get_record('role_capabilities', [
                'contextid' => $ctx->id,
                'roleid' => $role->id,
                'capability' => $cap,
                'permission' => 1,
            ]);
        if (empty($chk->id)) {
            $DB->insert_record('role_capabilities', [
                'contextid' => $ctx->id,
                'roleid' => $role->id,
                'capability' => $cap,
                'permission' => 1,
                'timemodified' => time(),
                'modifierid' => 2,
            ]);
        }
    }

    // Insert default plugin configuration settings.
    $defaultsettings = [
        'apitoken' => '',
    ];

    $componentname = 'tool_wbinstaller';
    foreach ($defaultsettings as $name => $value) {
        if (!$DB->record_exists('config_plugins', ['plugin' => 'pluginname', 'name' => $componentname])) {
            $record = new stdClass();
            $record->plugin = 'pluginname';
            $record->name = $name;
            $record->value = $value;
            $DB->insert_record('config_plugins', $record);
        }
    }

    return true;
}
