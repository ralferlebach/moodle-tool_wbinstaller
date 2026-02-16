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
final class coursesinstaller_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Test the execute method to ensure courses are installed.
     */
    public function test_execute_installs_courses(): void {
        global $CFG;
        $recipe = ['path' => '/testcourses'];
        $extractpath = $CFG->tempdir . '/test_wbinstaller';
        $testcourse1 = $extractpath . $recipe['path'] . '/course1';
        $testcourse2 = $extractpath . $recipe['path'] . '/course2';

        @mkdir($extractpath . $recipe['path'], 0777, true);
        file_put_contents($testcourse1, 'Test Course 1');
        file_put_contents($testcourse2, 'Test Course 2');

        // Verify test files are created successfully.
        $this->assertFileExists($testcourse1, 'Test course file 1 was not created.');
        $this->assertFileExists($testcourse2, 'Test course file 2 was not created.');

        // Create instance of coursesInstaller.
        $installer = $this->getMockBuilder(coursesInstaller::class)
            ->setConstructorArgs([$recipe])
            ->onlyMethods(['change_courses_mod_urls', 'install_course'])
            ->getMock();

        // Mock the parent parameter.
        $mockparent = $this->createMock(\tool_wbinstaller\wbCheck::class);

        // Expect install_course to be called exactly 2 times.
        $installer->expects($this->exactly(2))
            ->method('install_course')
            ->withConsecutive(
                [$this->equalTo($testcourse1), $this->equalTo($mockparent)],
                [$this->equalTo($testcourse2), $this->equalTo($mockparent)]
            );

        $installer->expects($this->once())
            ->method('change_courses_mod_urls');

        // Run the execute method.
        $installer->execute($extractpath, $mockparent);

        // Clean up created directories and files after the test.
        @unlink($testcourse1);
        @unlink($testcourse2);
        @rmdir($extractpath . $recipe['path']);
        @rmdir($extractpath);

        // Ensure cleanup was successful.
        $this->assertDirectoryDoesNotExist($extractpath, 'Test directory was not removed.');
    }

    /**
     * Test precheck method to check if courses are detected.
     */
    public function test_precheck_detects_courses(): void {
        global $CFG;

        // Mock a recipe.
        $recipe = ['path' => '/testcourses'];
        $extractpath = $CFG->tempdir . '/test_wbinstaller';

        // Create a test directory and add a mock course file.
        @mkdir($extractpath . $recipe['path'] . '/course1', 0777, true); // Ensure the directory exists.
        file_put_contents($extractpath . $recipe['path'] . '/course1/moodle_backup.xml', '<xml>mock data</xml>');

        // Create instance of coursesInstaller.
        $installer = $this->getMockBuilder(coursesInstaller::class)
            ->setConstructorArgs([$recipe])
            ->onlyMethods(['precheck'])
            ->getMock();

        // Simulate precheck returning valid data.
        $installer->method('precheck')
            ->willReturn([
                'courseshortname' => 'test_shortname',
                'courseoriginalid' => 1234,
            ]);

        // Run check method and verify feedback.
        $feedback = [];
        $installer->feedback = &$feedback;
        $installer->check($extractpath, null);

        $this->assertArrayHasKey('needed', $installer->feedback);
        $this->assertArrayHasKey('test_shortname', $installer->feedback['needed']);
    }

    /**
     * Test install_course method to ensure it restores the course properly.
     */
    public function test_install_course_restores_correctly(): void {
        global $DB;

        $coursefile = '/somepath/testcourse/course1';
        $recipe = ['path' => '/testcourses'];
        $mockparent = $this->createMock(\tool_wbinstaller\wbCheck::class);

        // Simulate valid precheck results.
        $precheckresult = [
            'courseshortname' => 'test_shortname',
            'courseoriginalid' => 1234,
        ];

        // Create a mock instance of coursesInstaller.
        $installer = $this->getMockBuilder(coursesInstaller::class)
            ->setConstructorArgs([$recipe])
            ->onlyMethods(['precheck', 'restore_course', 'get_success_message'])
            ->getMock();

        $installer->method('precheck')->willReturn($precheckresult);
        // Expect restore_course to be called once with the correct arguments.
        $installer->expects($this->once())
            ->method('restore_course')
            ->with(
                $this->equalTo($precheckresult),
                $this->equalTo($mockparent)
            );

        // Simulate success message generation.
        $installer->expects($this->once())
            ->method('get_success_message')
            ->with($this->equalTo($precheckresult))
            ->willReturn('Course restored successfully.');

        // Use reflection to invoke the protected method install_course.
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('install_course');
        $method->setAccessible(true); // Bypass the protected access level.

        // Call the method using reflection.
        $result = $method->invoke($installer, $coursefile, $mockparent);

        // Assert the method returns the expected value.
        $this->assertEquals(1, $result, 'The install_course method did not return the expected value.');
    }

    /**
     * Test course_exists method to check if a course already exists.
     */
    public function test_course_exists(): void {
        global $DB;

        // Create a mock course in the database.
        $course = new stdClass();
        $course->shortname = 'test_shortname';
        $course->fullname = 'Test Course';
        $course->category = 1;
        $course->id = $DB->insert_record('course', $course);

        // Create instance of coursesInstaller.
        $installer = new coursesInstaller([]);

        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('course_exists');
        $method->setAccessible(true);

        // Call the method using reflection and check the result.
        $result = $method->invoke($installer, 'test_shortname');
        $this->assertNotNull($result);
        $this->assertEquals($course->id, $result->id);
    }

    /**
     * Test course_exists method to check if a course already exists.
     * @covers ::get_course_category
     */
    public function test_get_course_category(): void {
        global $DB;
        $DB = $this->createMock(\moodle_database::class);

        // Mock timestamp property.
        $installer = $this->getMockBuilder(\tool_wbinstaller\coursesInstaller::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['set_course_category'])
            ->getMock();
        $installer->timestamp = '2024-11-20_';

        // Expected parent category and subcategory.
        $expectedparentcategory = (object)[
            'id' => 1,
            'name' => 'WbInstall',
        ];

        $expectedsubcategory = (object)[
            'id' => 2,
            'name' => '2024-11-20_SubCategory',
        ];

        // Test case: Both parent and subcategory do not exist.
        $DB->expects($this->exactly(2))
            ->method('get_record')
            ->will($this->onConsecutiveCalls(null, null));

        $installer->expects($this->exactly(2))
            ->method('set_course_category')
            ->withConsecutive(
                ['WbInstall', null],
                ['2024-11-20_SubCategory', $expectedparentcategory]
            )
            ->will($this->onConsecutiveCalls($expectedparentcategory, $expectedsubcategory));

        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('get_course_category');
        $method->setAccessible(true);

        // Invoke the protected method.
        $result = $method->invoke($installer, 'SubCategory');

        // Assert the returned subcategory.
        $this->assertEquals($expectedsubcategory->name, $result->name, 'Expected subcategory was not returned.');
    }

    /**
     * Test the change_courses_mod_urls method to ensure URLs are updated correctly.
     * @covers ::change_courses_mod_urls
     */
    public function test_change_courses_mod_urls(): void {
        global $DB, $CFG;

        // Mock the database records for URL activities and matching course IDs.
        $CFG->wwwroot = 'http://example.com';

        $DB->insert_record('url', (object)[
            'course' => 2,
            'externalurl' => 'http://example.com/course/view.php?id=1',
        ]);

        // Create an instance of the installer with mock matching IDs.
        $recipe = ['path' => '/testcourses']; // Provide a valid $recipe array.
        // Create an instance of the installer.
        $installer = new coursesInstaller($recipe);
        $installer->matchingids = [
            'courses' => [
                3 => 2,
                1 => 200,
            ],
        ];

        // Use reflection to invoke the protected method change_courses_mod_urls.
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('change_courses_mod_urls');
        $method->setAccessible(true);

        // Call the method to perform the update.
        $method->invoke($installer);

        // Verify that the URL was updated in the database.
        $updatedurl = $DB->get_record('url', ['course' => 2]);
        $this->assertStringContainsString(
            '/course/view.php?id=200',
            $updatedurl->externalurl,
            'ID 23 should be transformed to 550.'
        );
    }
}
