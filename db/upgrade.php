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
 * Plugin upgrade steps are defined here.
 *
 * This file contains the upgrade function that is called when the plugin
 * version is incremented. It manages database schema changes such as
 * creating the tool_wbinstaller_install table for tracking installation progress.
 *
 * @package     tool_wbinstaller
 * @category    upgrade
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute tool_wbinstaller upgrade from the given old version.
 *
 * Applies incremental database schema changes depending on the currently
 * installed version. Creates the tool_wbinstaller_install table if upgrading
 * from a version prior to 2024061004.
 *
 * For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
 * Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.
 *
 * @param int $oldversion The version number of the currently installed plugin.
 * @return bool Always returns true on successful upgrade.
 */
function xmldb_tool_wbinstaller_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024061004) {
        // Define table tool_wbinstaller_install to be created.
        $table = new xmldb_table('tool_wbinstaller_install');

        // Adding fields to table tool_wbinstaller_install.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('currentstep', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('maxstep', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding the primary key to table tool_wbinstaller_install.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table only if it does not already exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Wbinstaller savepoint reached.
        upgrade_plugin_savepoint(true, 2024061004, 'tool', 'wbinstaller');
    }

    return true;
}
