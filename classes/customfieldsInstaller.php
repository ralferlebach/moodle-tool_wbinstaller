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
 * Custom fields installer for creating custom field categories and fields.
 *
 * This class handles the creation of Moodle custom field categories and their
 * associated fields as defined in the Wunderbyte installer recipe. It supports
 * custom handler namespaces and duplicate detection for existing fields.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

/**
 * Installer class for Moodle custom fields and custom field categories.
 *
 * Extends the base wbInstaller to provide functionality for creating
 * custom field categories and inserting custom field definitions using
 * the Moodle core_customfield API.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfieldsInstaller extends wbInstaller {
    /** @var \core_customfield\handler|null The custom field handler instance for the current category. */
    public $handler;

    /**
     * Constructor for the customfieldsInstaller.
     *
     * Initializes the installer with the given recipe configuration,
     * sets the progress counter to zero, and initializes the handler to null.
     *
     * @param array $recipe Array of custom field category definitions, each containing fields.
     */
    public function __construct($recipe) {
        $this->recipe = $recipe;
        $this->progress = 0;
        $this->handler = null;
    }

    /**
     * Execute the custom fields installation process.
     *
     * Iterates over all custom field category definitions in the recipe. For each
     * category, creates or retrieves the category, then processes each field definition.
     * Skips fields that already exist (by shortname) and reports success or error feedback.
     *
     * @param string $extractpath The base extraction path of the installer package (unused).
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance (unused).
     * @return int Returns 1 on completion.
     */
    public function execute($extractpath, $parent = null) {
        global $DB;
        $customfieldfields = $DB->get_records('customfield_field', null, null, 'shortname');

        foreach ($this->recipe as $customfields) {
            // Create or retrieve the custom field category.
            $categoryid = $this->upload_category($customfields);

            foreach ($customfields['fields'] as $customfield) {
                if (!$categoryid) {
                    // Category creation failed — report error for all fields.
                    $this->feedback['needed'][$customfields['name']]['error'][] =
                      get_string('customfieldfailupload', 'tool_wbinstaller');
                } else if (isset($customfieldfields[$customfield['shortname']])) {
                    // Field already exists — report as duplicate.
                    $this->feedback['needed'][$customfields['name']]['success'][] =
                    get_string('customfieldduplicate', 'tool_wbinstaller', $customfield['shortname']);
                } else {
                    // Create the new custom field.
                    try {
                        $this->upload_fieldset($customfield, $categoryid);
                        $this->feedback['needed'][$customfields['name']]['success'][] =
                            get_string('customfieldsuccesss', 'tool_wbinstaller', $customfield['name']);
                    } catch (\Exception $e) {
                        $this->feedback['needed'][$customfields['name']]['error'][] = get_string(
                            'customfielderror',
                            'tool_wbinstaller',
                            $e->getMessage()
                        );
                    }
                }
            }
        }
        return 1;
    }

    /**
     * Create or retrieve a custom field category.
     *
     * Looks up an existing category by name. If not found, creates a new one
     * using the appropriate custom field handler. Supports custom handler namespaces
     * defined in the recipe configuration.
     *
     * @param array $customfields The category definition array with keys 'name', 'component', 'area', and optional 'namespace'.
     * @return int The category ID (existing or newly created).
     */
    public function upload_category($customfields) {
        global $DB;
        $category = $DB->get_record('customfield_category', ['name' => $customfields['name']], 'id');

        // Determine the handler namespace (default to core_customfield).
        $namespace = "\\core_customfield\\handler";
        if (
            isset($customfields['namespace']) &&
            class_exists($customfields['namespace'])
        ) {
            $namespace = $customfields['namespace'];
        }

        $this->handler = $namespace::get_handler(
            $customfields['component'],
            $customfields['area']
        );

        if ($category) {
            return $category->id;
        }
        return $this->handler->create_category($customfields['name']);
    }

    /**
     * Create a single custom field within a category.
     *
     * Instantiates a new field controller, sets the shortname, name, and config data,
     * then saves the field configuration using the custom field handler.
     *
     * @param array $fieldset The field definition array with keys 'type', 'shortname', 'name', 'configdata', etc.
     * @param int $categoryid The ID of the custom field category to which the field belongs.
     * @return void
     */
    public function upload_fieldset($fieldset, $categoryid) {
        $record = new \stdClass();
        $record->categoryid = $categoryid;
        $record->type = $fieldset['type'];
        $fieldcontroller = \core_customfield\field_controller::create(0, $record);

        $fieldcontroller->set('shortname', $fieldset['shortname']);
        $fieldcontroller->set('name', $fieldset['name']);
        $fieldcontroller->set('configdata', $fieldset['configdata']);

        $this->handler->save_field_configuration($fieldcontroller, (object)[
            'shortname' => $fieldset['shortname'],
            'name' => $fieldset['name'],
            'type' => $fieldset['type'],
            'description' => $fieldset['description'] ?? '',
            'descriptionformat' => $fieldset['descriptionformat'] ?? 0,
            'configdata' => $fieldset['configdata'],
        ]);
    }

    /**
     * Pre-check the custom fields before execution.
     *
     * Iterates over all custom field definitions and checks whether each field
     * already exists by shortname. Reports duplicates and new fields accordingly.
     *
     * @param string $extractpath The base extraction path of the installer package (unused).
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance (unused).
     * @return void
     */
    public function check($extractpath, $parent) {
        global $DB;
        $customfieldfields = $DB->get_records('customfield_field', null, null, 'shortname');

        foreach ($this->recipe as $customfields) {
            foreach ($customfields['fields'] as $customfield) {
                if (isset($customfieldfields[$customfield['shortname']])) {
                    $this->feedback['needed'][$customfields['name']]['success'][] =
                      get_string('customfieldduplicate', 'tool_wbinstaller', $customfield['shortname']);
                } else {
                    $this->feedback['needed'][$customfields['name']]['success'][] =
                      get_string('customfieldnewfield', 'tool_wbinstaller', $customfield['name']);
                }
            }
        }
    }
}
