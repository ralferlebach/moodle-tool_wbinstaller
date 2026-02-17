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
 * Configuration installer for setting plugin config values during installation.
 *
 * This class handles importing and applying Moodle plugin configuration settings
 * as defined in the Wunderbyte installer recipe. It supports both execution
 * (applying config values) and pre-checking (verifying config fields exist).
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

/**
 * Installer class for Moodle plugin configuration settings.
 *
 * Extends the base wbInstaller to provide functionality for reading
 * configuration values from the recipe and applying them to the
 * corresponding Moodle plugin config entries via set_config().
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configInstaller extends wbInstaller {
    /** @var \core_customfield\handler|null Custom field handler instance (unused in this installer). */
    public $handler;

    /**
     * Constructor for the configInstaller.
     *
     * Initializes the installer with the given recipe configuration,
     * sets the progress counter to zero, and initializes the handler to null.
     *
     * @param array $recipe Associative array keyed by plugin name, each containing config field => value pairs.
     */
    public function __construct($recipe) {
        $this->recipe = $recipe;
        $this->progress = 0;
        $this->handler = null;
    }

    /**
     * Execute the configuration installation process.
     *
     * Iterates over all plugins and their configuration fields defined in the recipe.
     * For each field, checks whether the configuration entry already exists in Moodle.
     * If found, the value is updated via set_config(). If not found, an error is reported.
     *
     * @param string $extractpath The base extraction path of the installer package (unused).
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance (unused).
     * @return int Returns 1 on completion.
     */
    public function execute($extractpath, $parent = null) {
        global $DB;
        foreach ($this->recipe as $pluginname => $configfields) {
            foreach ($configfields as $configfield => $value) {
                $currentvalue = get_config($pluginname, $configfield);
                if ($currentvalue !== false) {
                    // Config field exists — update it with the recipe value.
                    set_config($configfield, $value, $pluginname);
                    $this->feedback['needed'][$pluginname]['success'][] =
                        get_string('configvalueset', 'tool_wbinstaller', $configfield);
                } else {
                    // Config field does not exist — report error.
                    $this->feedback['needed'][$pluginname]['error'][] =
                      get_string('confignotfound', 'tool_wbinstaller', $configfield);
                }
            }
        }
        return 1;
    }

    /**
     * Pre-check the configuration settings before execution.
     *
     * Iterates over all plugins and their configuration fields defined in the recipe.
     * For each field, checks whether the configuration entry exists in Moodle.
     * Reports success if found, or a warning if the config field is missing.
     *
     * @param string $extractpath The base extraction path of the installer package (unused).
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance (unused).
     * @return int Returns 1 on completion.
     */
    public function check($extractpath, $parent = null) {
        global $DB;
        foreach ($this->recipe as $pluginname => $configfields) {
            foreach ($configfields as $configfield => $value) {
                $currentvalue = get_config($pluginname, $configfield);
                if ($currentvalue !== false) {
                    $this->feedback['needed'][$pluginname]['success'][] =
                        get_string('configsettingfound', 'tool_wbinstaller', $configfield);
                } else {
                    $this->feedback['needed'][$pluginname]['warning'][] =
                      get_string('confignotfound', 'tool_wbinstaller', $configfield);
                }
            }
        }
        return 1;
    }
}
