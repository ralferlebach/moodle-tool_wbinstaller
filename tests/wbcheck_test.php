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
final class wbcheck_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test the execute method, which combines several other functionalities.
     */
    public function test_execute(): void {
        // Mock recipe data and filename.
        $recipe = 'data:application/zip;base64,dGVzdGZpbGU=';
        $filename = 'testrecipe.zip';

        // Create an instance of wbCheck with mocked data.
        $check = $this->getMockBuilder(wbCheck::class)
            ->setConstructorArgs([$recipe, $filename])
            ->onlyMethods(['check_recipe'])
            ->addMethods(['extract_save_zip_file', 'clean_after_installment'])
            ->getMock();

        // Mock check_recipe to simulate checking the extracted content.
        $check->expects($this->once())
            ->method('check_recipe')
            ->with($this->callback(function ($path) {
                return strpos($path, '/temp/zip/precheck/') !== false;
            }))
            ->willReturn(true);

        // Call the execute method and check the result.
        $feedback = $check->execute();
        $this->assertIsArray($feedback);
    }
}
