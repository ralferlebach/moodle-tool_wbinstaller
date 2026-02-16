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

namespace tool_wbinstaller;

use advanced_testcase;
use moodle_database;

/**
 * PHPUnit test case for the 'tool_wbinstaller' class in local_adele.
 *
 * @package     tool_wbinstaller
 * @author       tool_wbinstaller
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_wbinstaller
 */
final class learningpathsinstaller_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Test execute method to ensure it runs the recipe with the update flag.
     * @covers ::execute
     */
    public function test_execute_runs_recipe(): void {
        $recipe = ['path' => '/testlearningpaths'];
        $extractpath = sys_get_temp_dir() . '/test_wbinstaller';

        // Create an instance of learningpathsInstaller.
        $installer = $this->getMockBuilder(learningpathsInstaller::class)
            ->setConstructorArgs([$recipe])
            ->onlyMethods(['run_recipe'])
            ->getMock();

        // Verify that run_recipe is called during execute.
        $installer->expects($this->once())
            ->method('run_recipe')
            ->with($this->equalTo($extractpath));

        $installer->execute($extractpath, null);
        $this->assertTrue($installer->update);
    }

    /**
     * Test run_recipe method to ensure it reads JSON data and stores feedback.
     * @covers ::run_recipe
     */
    public function test_run_recipe_stores_feedback(): void {
        global $DB;

        $recipe = ['path' => '/testlearningpaths'];
        $extractpath = sys_get_temp_dir() . '/test_wbinstaller';
        $learningpathdata = [
            [
                'name' => 'Test Learning Path',
                'json' => json_encode(['step' => 'Introduction']),
            ],
        ];

        // Mock a learning path JSON file.
        @mkdir($extractpath . $recipe['path'], 0777, true);
        file_put_contents(
            $extractpath . $recipe['path'] . '/learningpath1.json',
            json_encode($learningpathdata)
        );

        $installer = new learningpathsInstaller($recipe);
        $installer->run_recipe($extractpath);

        // Check that feedback was set correctly.
        $this->assertArrayHasKey('needed', $installer->feedback);
        $this->assertArrayHasKey('Test Learning Path', $installer->feedback['needed']);
        $this->assertArrayHasKey('success', $installer->feedback['needed']['Test Learning Path']);
    }

    /**
     * Test check_table_exists method to ensure it flags missing tables.
     * @covers ::check_table_exists
     */
    public function test_check_table_exists_unvalid_valid(): void {
        global $DB;

        $recipe = [];
        $learningpath = ['name' => 'Test Learning Path'];
        $installer = new learningpathsInstaller($recipe);

        $installer->fileinfo = 'non_existing_table';
        $installer->check_table_exists([], $learningpath);
        $this->assertArrayHasKey('needed', $installer->feedback);
        $this->assertArrayHasKey('warning', $installer->feedback['needed']['Test Learning Path']);
        $this->assertStringContainsString(
            get_string('dbtablenotfound', 'tool_wbinstaller', $installer->fileinfo),
            $installer->feedback['needed']['Test Learning Path']['warning'][0]
        );
        $installer->feedback = null;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table($installer->fileinfo);
        $table->add_field('id', \XMLDB_TYPE_INTEGER, '10', null, \XMLDB_NOTNULL, \XMLDB_SEQUENCE, null);
        $table->add_field('name', \XMLDB_TYPE_CHAR, '255', null, \XMLDB_NOTNULL, null, null);
        $table->add_key('primary', \XMLDB_KEY_PRIMARY, ['id']);

        // Create the table if it doesn't already exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $installer->check_table_exists([], $learningpath);
        $this->assertNull($installer->feedback);
    }

    /**
     * Test check_path_exists method to ensure it flags missing tables.
     * @covers ::check_path_exists
     */
    public function test_check_path_exists_unvalid_valid(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('test_table');
        $table->add_field('id', \XMLDB_TYPE_INTEGER, '10', null, \XMLDB_NOTNULL, \XMLDB_SEQUENCE, null);
        $table->add_field('name', \XMLDB_TYPE_CHAR, '255', null, \XMLDB_NOTNULL, null, null);
        $table->add_key('primary', \XMLDB_KEY_PRIMARY, ['id']);

        // Create the table if it doesn't already exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $recipe = [];
        $learningpath = ['name' => 'Test Learning Path'];
        $installer = new learningpathsInstaller($recipe);
        $installer->fileinfo = 'test_table';

        $installer->check_path_exists([], $learningpath);
        $this->assertNull($installer->feedback);

        // Insert a valid path.
        $DB->insert_record('test_table', (object)['name' => $learningpath['name']]);
        $installer->check_path_exists([], $learningpath);
        $this->assertArrayHasKey('error', $installer->feedback['needed'][$learningpath['name']]);
        $this->assertStringContainsString(
            get_string('learningpathalreadyexistis', 'tool_wbinstaller'),
            $installer->feedback['needed'][$learningpath['name']]['error'][0]
        );
    }

    /**
     * Test check_path_exists method to ensure it flags missing tables.
     * @covers ::check_entity_id_exists
     */
    public function test_check_component_exists_unvalid_valid(): void {
        $recipe = [];
        $installer = new learningpathsInstaller($recipe);

        $missingentities = [];
        $data = 9999;  // Simulate a missing entity ID.
        $name = 'Test Learning Path';

        // Mock parent matching IDs to simulate a missing entity.
        $installer->parent = (object)['matchingids' => ['localdata' => ['id' => []]]];
        $installer->check_entity_id_exists($data, $name, $missingentities, 'localdata', 'id');

        // Assert feedback contains the missing entity error.
        $this->assertArrayHasKey('needed', $installer->feedback);
        $this->assertArrayHasKey('error', $installer->feedback['needed'][$name]);
        $this->assertStringContainsString(
            get_string('coursetypenotfound', 'tool_wbinstaller'),
            $installer->feedback['needed'][$name]['error'][0]
        );
    }

    /**
     * Test check_path_exists method to ensure it flags missing tables.
     * @covers ::get_value_by_path
     */
    public function test_get_value_by_path_retrieves_nested_values(): void {
        $installer = new learningpathsInstaller([]);

        $data = [
            'level1' => [
                'level2' => [
                    'target' => 'value_to_find',
                ],
              ],
        ];

        $pathvalid = 'level1->level2->target';
        $pathnotvalid = 'level1->level3->target';

        $valuevalid = $installer->get_value_by_path($data, $pathvalid);
        $valuenotvalid = $installer->get_value_by_path($data, $pathnotvalid);

        // Assert that the retrieved value matches the target value.
        $this->assertEquals('value_to_find', $valuevalid);
        $this->assertNull($valuenotvalid);
    }

    /**
     * Test check_path_exists method to ensure it flags missing tables.
     * @covers ::set_value_by_path
     */
    public function test_set_value_by_path_sets_value_correctly(): void {
        $installer = new learningpathsInstaller([]);

        $data = [
            'level1' => [
                'level2' => [
                  'target' => 'original_value',
                ],
                'level3' => [
                  'target' => 'original_value',
                ],
              ],
        ];

        $validpath = 'level1->level2->target';
        $invalidpath = 'level1->level4->target';

        // Set value at the valid path.
        $installer->set_value_by_path($data, $validpath, 'new_value');
        $installer->set_value_by_path($data, $invalidpath, 'new_value');

        $this->assertEquals('new_value', $data['level1']['level2']['target']);
        $this->assertArrayNotHasKey('level4', $data['level1']);
    }
}
