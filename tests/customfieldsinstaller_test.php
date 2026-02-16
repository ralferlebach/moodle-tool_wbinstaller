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
final class customfieldsinstaller_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test the execute method to ensure custom fields are processed correctly.
     */
    public function test_execute_processes_customfields(): void {
        global $DB;

        // Mock a custom field recipe.
        $recipe = [
            [
                'name' => 'Test Category',
                'component' => 'tool_testcomponent',
                'area' => 'testarea',
                'namespace' => '\core_customfield\handler',
                'fields' => [
                    [
                        'shortname' => 'test_shortname',
                        'name' => 'Test Field',
                        'type' => 'text',
                        'data' => ['param1' => 'value1'],
                    ],
                  ],
            ],
        ];

        // Mock DB responses.
        $DB = $this->createMock(\moodle_database::class);
        $DB->method('get_records')
            ->willReturn([]);

        // Create the customfieldsInstaller instance.
        $installer = $this->getMockBuilder(customfieldsInstaller::class)
            ->setConstructorArgs([$recipe])
            ->onlyMethods(['upload_category', 'upload_fieldset'])
            ->getMock();

        // Expect the category to be uploaded once.
        $installer->expects($this->once())
            ->method('upload_category')
            ->with($recipe[0])
            ->willReturn(1); // Return the category ID.

        // Expect the fieldset to be uploaded once.
        $installer->expects($this->once())
            ->method('upload_fieldset')
            ->with($recipe[0]['fields'][0], 1);

        // Execute the installer.
        $installer->execute('/somepath/');
    }
}
