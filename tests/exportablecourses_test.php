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
use stdClass;

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
final class exportablecourses_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test the execute method to ensure courses are installed.
     */
    public function test_get_courses_returns_empty_array_when_no_courses_exist(): void {
        global $DB;
        $DB = $this->createMock(\moodle_database::class);
        $DB->expects($this->once())
            ->method('get_records_sql')
            ->with(
                $this->stringContains('SELECT DISTINCT c.id, c.fullname'),
                $this->equalTo(['modname' => 'adaptivequiz'])
            )
            ->willReturn([]);

        $courses = exportableCourses::get_courses();
        $this->assertIsArray($courses);
        $this->assertEmpty($courses);
    }

    public function test_get_courses_returns_courses_array(): void {
        global $DB;
        $DB = $this->createMock(\moodle_database::class);
        $dbrecords = [
            (object) ['id' => 1, 'fullname' => 'Course 1'],
            (object) ['id' => 2, 'fullname' => 'Course 2'],
        ];

        $DB->expects($this->once())
            ->method('get_records_sql')
            ->with(
                $this->stringContains('SELECT DISTINCT c.id, c.fullname'),
                $this->equalTo(['modname' => 'adaptivequiz'])
            )
            ->willReturn($dbrecords);

        $courses = exportableCourses::get_courses();

        $this->assertIsArray($courses);
        $this->assertCount(2, $courses);
        $this->assertEquals([
            ['id' => 1, 'fullname' => 'Course 1'],
            ['id' => 2, 'fullname' => 'Course 2'],
        ], $courses);
    }
}
