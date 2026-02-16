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
final class questionsinstaller_test extends advanced_testcase {

    /** @var moodle_database DB mock */
    protected $db;
    /** @var questionsInstaller question installer class */
    protected $installer;
    /** @var array Recipe for installer */
    protected $recipe;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        global $CFG;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->libdir . '/questionlib.php');

        global $DB;
        $this->db = $this->createMock(moodle_database::class);
        $DB = $this->db;

        $this->recipe = [
            'path' => '/some/question/path',
        ];

        $this->installer = new questionsInstaller($this->recipe);
    }

    /**
     * Test constructor and initialization.
     */
    public function test_constructor_initializes_correctly(): void {
        $this->assertInstanceOf(questionsInstaller::class, $this->installer);
        $this->assertEquals($this->recipe, $this->installer->recipe);
    }
}
