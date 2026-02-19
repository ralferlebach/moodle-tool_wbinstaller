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
 * Course installer for restoring Moodle courses from .mbz backup files.
 *
 * This class handles the extraction, validation, and restoration of Moodle courses
 * from .mbz backup archives as part of the Wunderbyte installer process. It manages
 * course ID matching, activity component ID mapping, category creation, and
 * post-restore URL rewriting.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

use backup;
use restore_controller;
use stdClass;

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../../../lib/setup.php');
global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_login();

/**
 * Installer class for restoring Moodle courses from .mbz backup archives.
 *
 * Extends the base wbInstaller to provide functionality for extracting .mbz files,
 * reading course metadata from backup XML, restoring courses via the Moodle
 * restore controller, mapping old-to-new course and component IDs, updating
 * internal URL references, and managing course categories.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursesInstaller extends wbInstaller {
    /** @var string Timestamp used for unique category naming to avoid collisions. */
    public $timestamp;

    /**
     * Constructor for the coursesInstaller.
     *
     * Initializes the installer with the given recipe, sets the progress counter
     * to zero, and generates a timestamp for unique directory identification.
     *
     * @param mixed $recipe The recipe configuration array containing the courses path.
     */
    public function __construct($recipe) {
        $this->recipe = $recipe;
        $this->progress = 0;
        $this->timestamp = date('Y-m-d_H-i-s');
    }

    /**
     * Execute the course installation process.
     *
     * Iterates over all .mbz files in the courses directory, installs each course
     * via the restore controller, and then updates internal URL module references
     * to point to the newly created course IDs.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance.
     * @return string Returns '1' on completion.
     */
    public function execute($extractpath, $parent = null) {
        $coursespath = $extractpath . $this->recipe['path'];
        foreach (glob("$coursespath/*") as $coursefile) {
            $this->install_course($coursefile, $parent);
        }
        // Update URL module references after all courses are restored.
        $this->change_courses_mod_urls();
        return '1';
    }

    /**
     * Pre-check all course backup files before execution.
     *
     * Iterates over all .mbz files in the courses directory and validates
     * each backup file by extracting and reading its metadata XML.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance.
     * @return string Returns '1' on completion.
     */
    public function check($extractpath, $parent) {
        $coursespath = $extractpath . $this->recipe['path'];
        foreach (glob("$coursespath/*") as $coursefile) {
            $precheck = $this->precheck($coursefile);
            if ($precheck) {
                $this->feedback['needed'][$precheck['courseshortname']]['success'][] =
                    get_string('newcoursefound', 'tool_wbinstaller', $precheck['courseshortname']);
            } else {
                $this->feedback['needed'][pathinfo($coursefile)['basename']]['error'][] =
                    get_string('errorextractingmbz', 'tool_wbinstaller', pathinfo($coursefile)['basename']);
            }
        }
        return '1';
    }

    /**
     * Install a single course from a backup file.
     *
     * Runs the precheck validation, then performs the full course restore
     * and generates a success feedback message.
     *
     * @param string $coursefile The absolute path to the .mbz backup file.
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance.
     * @return int Returns 1 on completion.
     */
    protected function install_course($coursefile, $parent) {
        $precheck = $this->precheck($coursefile);
        if ($precheck) {
            $this->restore_course($precheck, $parent);
            $this->feedback['needed'][$precheck['courseshortname']]['success'][] =
                $this->get_success_message($precheck);
        }
        return 1;
    }

    /**
     * Generate a success message for a restored course.
     *
     * Retrieves the target category name and builds a localized success
     * message including the course shortname and category.
     *
     * @param array $precheck The precheck result array containing 'courseshortname'.
     * @return string The localized success message string.
     */
    protected function get_success_message($precheck): string {
        global $DB;
        $msgparams = new stdClass();
        $msgparams->courseshortname = $precheck['courseshortname'];
        $msgparams->category = $DB->get_field('course_categories', 'name', ['id' => 1]);
        return get_string('coursessuccess', 'tool_wbinstaller', $msgparams);
    }

    /**
     * Validate and extract metadata from a course backup file.
     *
     * Extracts the .mbz file, reads the moodle_backup.xml to obtain the course
     * shortname and original ID, extracts activity IDs for adaptivequiz and quiz
     * components, and checks for duplicate courses by shortname.
     *
     * @param string $coursefile The absolute path to the .mbz backup file.
     * @return array|null|int Associative array with keys 'courseshortname', 'courseoriginalid', 'tempdir';
     *                        null if extraction failed; 0 if validation failed or course already exists.
     */
    protected function precheck($coursefile) {
        $tempdir = $this->extract_mbz_file($coursefile);
        if ($tempdir) {
            $xml = simplexml_load_file($tempdir . '/moodle_backup.xml');
            $courseshortname = $this->get_course_short_name($xml);
            $courseoriginalid = $this->get_course_og_id($xml);

            // Extract adaptivequiz activity IDs for component matching.
            foreach (glob($coursefile . "/activities/adaptivequiz_*") as $activityfolder) {
                $activityid = $this->extract_activity_id($activityfolder, 'adaptivequiz');
                if ($activityid) {
                    $this->matchingids['components'][$activityid] = $activityid;
                }
            }

            // Extract quiz activity IDs for component matching.
            foreach (glob($coursefile . "/activities/quiz_*") as $activityfolder) {
                $activityid = $this->extract_activity_id($activityfolder, 'quiz');
                if ($activityid) {
                    $this->matchingids['quizid'][$activityid] = $activityid;
                }
            }

            if (!$courseshortname || !$courseoriginalid) {
                $this->feedback['needed'][$coursefile]['error'][] =
                  get_string('coursesnoshortname', 'tool_wbinstaller', $coursefile);
                return 0;
            } else if ($course = $this->course_exists($courseshortname)) {
                // Course with this shortname already exists — map IDs but skip restore.
                $this->matchingids['courses'][$courseoriginalid] = $course->id;
                $this->feedback['needed'][$courseshortname]['warning'][] =
                  get_string('coursesduplicateshortname', 'tool_wbinstaller', $courseshortname);
                return 0;
            } else {
                // Store a placeholder mapping for the original ID.
                $this->matchingids['courses'][$courseoriginalid] = $courseoriginalid;
            }
            return [
                "courseshortname" => $courseshortname,
                "courseoriginalid" => $courseoriginalid,
                "tempdir" => $tempdir,
            ];
        }
        return null;
    }

    /**
     * Extract a .mbz backup archive to a temporary directory.
     *
     * Decompresses the gzip-compressed .mbz file to a .tar file, then extracts
     * the tar archive contents into a destination directory.
     *
     * @param string $mbzfile The absolute path to the .mbz backup file.
     * @return string|null The extraction destination directory path, or null on failure.
     */
    private function extract_mbz_file($mbzfile) {
        if (!file_exists($mbzfile)) {
            return null;
        }

        $destination = str_replace('.mbz', '', $mbzfile);
        if (!is_dir($destination) && !mkdir($destination, 0777, true)) {
            return null;
        }

        // Decompress gzip to tar.
        $tarfile = str_replace('.mbz', '.tar', $mbzfile);
        $gz = gzopen($mbzfile, 'rb');
        $outfile = fopen($tarfile, 'wb');

        if (!$gz || !$outfile) {
            return null;
        }

        while (!gzeof($gz)) {
            fwrite($outfile, gzread($gz, 4096));
        }

        gzclose($gz);
        fclose($outfile);

        // Extract tar archive contents.
        $phar = new \PharData($tarfile);
        $phar->extractTo($destination, null, true);
        unlink($tarfile);

        return $destination;
    }

    /**
     * Extract an activity ID from an activity XML file within a backup.
     *
     * Reads the component-specific XML file (e.g., adaptivequiz.xml or quiz.xml)
     * and returns the 'id' attribute of the root element.
     *
     * @param string $activityfolder The absolute path to the activity backup folder.
     * @param string $component The activity component name (e.g., 'adaptivequiz', 'quiz').
     * @return string|null The activity ID string, or null if the XML file does not exist.
     */
    protected function extract_activity_id($activityfolder, $component) {
        $xmlpath = $activityfolder . '/' . $component . '.xml';
        if (!file_exists($xmlpath)) {
            return null;
        }
        $xml = simplexml_load_file($xmlpath);
        $activityid = (string)$xml['id'];
        return $activityid;
    }

    /**
     * Extract the course shortname from the backup XML metadata.
     *
     * @param \SimpleXMLElement $xml The parsed moodle_backup.xml document.
     * @return string The original course shortname.
     */
    private function get_course_short_name($xml) {
        return (string)$xml->information->original_course_shortname;
    }

    /**
     * Extract the original course ID from the backup XML metadata.
     *
     * @param \SimpleXMLElement $xml The parsed moodle_backup.xml document.
     * @return string The original course ID.
     */
    private function get_course_og_id($xml) {
        return (string)$xml->information->original_course_id;
    }

    /**
     * Check whether a course with the given shortname already exists.
     *
     * @param string $shortname The course shortname to check.
     * @return object|false The course record object if found, or false otherwise.
     */
    protected function course_exists($shortname) {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id');
        return $course;
    }

    /**
     * Perform the full course restore from a backup directory.
     *
     * Creates a temporary course placeholder, copies the backup files to the
     * Moodle temp/backup directory, executes the Moodle restore controller,
     * forces the restored course to be hidden, maps old-to-new component IDs,
     * and cleans up the temporary course entry.
     *
     * @param array $precheck The precheck result array with 'courseshortname', 'courseoriginalid', and 'tempdir'.
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance providing the filename for category naming.
     * @return void
     */
    protected function restore_course($precheck, $parent) {
        global $USER, $CFG, $DB;
        $destination = $CFG->tempdir . '/backup/' . basename($precheck['tempdir']);
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }
        if (!$this->copy_directory($precheck['tempdir'], $destination)) {
            $this->feedback['needed'][$precheck['courseshortname']]['error'][] =
              get_string('coursesfailextract', 'tool_wbinstaller');
            return;
        }

        // Determine or create the target course category.
        $subcategoryname = preg_replace('/\.[^.]+$/', '', $parent->filename);
        $category = $this->get_course_category($subcategoryname);
        if (!$category) {
            $this->feedback['needed'][$precheck['courseshortname']]['error'][] =
              get_string('coursescategorynotfound', 'tool_wbinstaller');
            return;
        }
        $this->feedback['needed'][$precheck['courseshortname']]['success'][] =
              get_string('coursescategoryfound', 'tool_wbinstaller', $category->name);

        // Create a temporary placeholder course for the restore target.
        $newcourse = new stdClass();
        $newcourse->fullname = 'Temporary Course Fullname';
        $newcourse->shortname = 'temp_' . uniqid();
        $newcourse->category = $category->id;
        $newcourse->format = 'topics';
        $newcourse->visible = 1;
        $newcourse->timecreated = time();
        $newcourse->timemodified = time();
        $newcourse->newsitems = 0;
        $newcourse = create_course($newcourse);

        // Update the old-to-new course ID mapping.
        $this->matchingids['courses'][$precheck['courseoriginalid']] = $newcourse->id;

        // Execute the restore using the Moodle restore controller.
        $this->restore_with_controller(
            $precheck['tempdir'],
            $newcourse,
            $precheck['courseshortname']
        );

        // Ensure the restored course remains hidden.
        $this->force_course_visibility($newcourse->id);

        // Map old adaptivequiz component IDs to new ones.
        $this->update_matching_componentids(
            $precheck['tempdir'],
            $newcourse->id,
            '/activities/adaptivequiz_*',
            'adaptivequiz',
            'components'
        );

        // Map old quiz component IDs to new ones.
        $this->update_matching_componentids(
            $precheck['tempdir'],
            $newcourse->id,
            '/activities/quiz_*',
            'quiz',
            'quizid'
        );

        // Remove the temporary placeholder course and clean up empty categories.
        $this->delete_temporary_courses_categories($newcourse->shortname);
        return;
    }

    /**
     * Update URL module references in all restored courses to point to new course IDs.
     *
     * Iterates over all restored courses and their URL module instances. For any URL
     * pointing to course/view.php?id=..., replaces the old course ID with the newly
     * mapped course ID and updates the domain to the current Moodle wwwroot.
     *
     * @return void
     */
    protected function change_courses_mod_urls() {
        global $DB, $CFG;
        foreach ($this->matchingids['courses'] as $newid) {
            $courseurls = $DB->get_records('url', ['course' => $newid]);
            foreach ($courseurls as $courseurl) {
                if (str_contains($courseurl->externalurl, '/course/view.php?id=')) {
                    // Parse the URL to extract the old linked course ID.
                    $parsedurl = parse_url($courseurl->externalurl);
                    $query = $parsedurl['query'];
                    parse_str($query, $params);
                    $oldlinkedid = $params['id'] ?? null;

                    // Replace with the new course ID if a mapping exists.
                    if (array_key_exists($oldlinkedid, $this->matchingids['courses'])) {
                        $newid = $this->matchingids['courses'][$oldlinkedid];
                        $courseurl->externalurl = $CFG->wwwroot . '/course/view.php?id=' . $newid;
                        $DB->update_record('url', $courseurl);
                    }
                }
            }
        }
    }

    /**
     * Delete the temporary placeholder course and remove empty parent categories.
     *
     * Looks up the temporary course by its unique shortname, deletes it from the
     * database, and checks whether the parent category is now empty. If so,
     * the category is fully deleted as well.
     *
     * @param string $tempcourseshortname The unique shortname of the temporary course to delete.
     * @return void
     */
    protected function delete_temporary_courses_categories($tempcourseshortname) {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $tempcourseshortname]);
        if ($course) {
            $DB->delete_records('course', ['id' => $course->id]);
            $category = \core_course_category::get($course->category);

            // Remove the category if it no longer contains any courses.
            $categorycourses = $category->get_courses();
            if (!$categorycourses) {
                $category->delete_full();
            }
        }
    }

    /**
     * Update the component ID matching map after course restore.
     *
     * Compares old activity IDs extracted from the backup with new activity IDs
     * created during restore, and builds a mapping between them. This allows
     * other installers (e.g., localdataInstaller) to reference the correct new IDs.
     *
     * @param string $coursefile The path to the extracted backup directory.
     * @param string $newcourseid The ID of the newly restored course.
     * @param string $componentfolder The glob pattern for activity folders (e.g., '/activities/adaptivequiz_*').
     * @param string $componenttable The database table name for the component (e.g., 'adaptivequiz').
     * @param string $matchinglabel The key under which to store the ID mapping (e.g., 'components').
     * @return void
     */
    protected function update_matching_componentids(
        $coursefile,
        $newcourseid,
        $componentfolder,
        $componenttable,
        $matchinglabel
    ) {
        global $DB;
        $manager = $DB->get_manager();

        // Only process if the component's database table exists.
        if ($manager->table_exists($componenttable)) {
            $ogcomponentids = [];
            foreach (glob($coursefile . $componentfolder) as $activityfolder) {
                $activityid = $this->extract_activity_id($activityfolder, $componenttable);
                if ($activityid) {
                    $ogcomponentids[] = $activityid;
                }
            }

            $newcoursefile = $DB->get_records($componenttable, ['course' => $newcourseid], null, 'id');
            $newcoursefileids = array_keys($newcoursefile);

            // Build the mapping only if both arrays have the same count.
            if (
                count($ogcomponentids) > 0 &&
                count($ogcomponentids) == count($newcoursefile)
            ) {
                $componentmatch = array_combine($ogcomponentids, $newcoursefileids);
                foreach ($componentmatch as $ogid => $newid) {
                    $this->matchingids[$matchinglabel][$ogid] = $newid;
                }
            }
        }
    }

    /**
     * Get or create the course category hierarchy for the installation.
     *
     * Creates a parent category named 'WbInstall' (if not already existing) and a
     * timestamped subcategory within it. The timestamp ensures uniqueness across
     * multiple installation runs.
     *
     * @param string $subcategoryname The base subcategory name (derived from the archive filename).
     * @return mixed The subcategory record object, or false on failure.
     */
    protected function get_course_category($subcategoryname) {
        global $DB;
        $timestamedsubcategoryname = $this->timestamp . $subcategoryname;
        $parentcategoryname = 'WbInstall';

        // Get or create the parent 'WbInstall' category.
        $parentcategory = $DB->get_record(
            'course_categories',
            ['name' => $parentcategoryname],
            'id, name',
        );

        if (!$parentcategory) {
            $parentcategory = $this->set_course_category($parentcategoryname, $parentcategory);
        }

        // Get or create the timestamped subcategory.
        $subcategory = $DB->get_record(
            'course_categories',
            [
              'name' => $timestamedsubcategoryname,
              'parent' => $parentcategory->id,
            ],
            'id, name',
        );
        if (!$subcategory) {
            $subcategory = $this->set_course_category($timestamedsubcategoryname, $parentcategory);
        }
        return $subcategory;
    }

    /**
     * Create a new course category in the database.
     *
     * Inserts a new course category record with the given name and parent,
     * then updates the category path to include its own ID.
     *
     * @param string $parentcategoryname The name for the new category.
     * @param mixed $parentcategory The parent category object (with 'id' property), or null/false for top-level.
     * @return object The newly created category record with updated path.
     */
    protected function set_course_category($parentcategoryname, $parentcategory) {
        global $DB;
        $newcategory = new stdClass();
        $newcategory->name = $parentcategoryname;
        $newcategory->idnumber = null;
        $newcategory->description = $parentcategoryname;
        $newcategory->descriptionformat = FORMAT_HTML;
        $newcategory->parent = $parentcategory->id ?? 0;
        $newcategory->sortorder = 0;
        $newcategory->visible = 0;
        $newcategory->visibleold = 0;
        $newcategory->timemodified = time();
        $newcategory->depth = 1;
        $newcategory->path = '';

        // Insert the new category into the database.
        $newcategory->id = $DB->insert_record('course_categories', $newcategory);

        // Update the path to include the newly generated ID.
        $newcategory->path = '/' . $newcategory->id;
        $DB->update_record('course_categories', $newcategory);

        return $newcategory;
    }

    /**
     * Force a restored course to be visible (but in invisible category).
     *
     * Sets the course visibility to 1 and rebuilds the course cache
     * to ensure the change takes effect immediately.
     *
     * @param string $courseid The ID of the course to hide.
     * @return void
     */
    protected function force_course_visibility($courseid) {
        global $DB;
        $DB->set_field('course', 'visible', 1, ['id' => $courseid]);
        $DB->set_field('course', 'visibleold', 1, ['id' => $courseid]);
        rebuild_course_cache($courseid, true);
    }

    /**
     * Execute the Moodle restore controller for a course backup.
     *
     * Copies the backup files to the Moodle temp/backup directory, creates a
     * restore controller in non-interactive import mode targeting a new course,
     * runs the precheck, executes the restore plan, and cleans up resources.
     *
     * @param string $coursefile The path to the extracted backup directory.
     * @param object $newcourse The target course object (must have 'id' and 'shortname').
     * @param string $courseshortname The original course shortname for feedback reporting.
     * @return void
     */
    protected function restore_with_controller(
        $coursefile,
        $newcourse,
        $courseshortname
    ) {
        global $USER, $CFG;

        $restorepath = $coursefile;
        $destination = $CFG->tempdir . '/backup/' . basename($coursefile);
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        // Copy course backup content to the Moodle temp backup directory.
        $this->copy_directory($coursefile, $destination);

        // Create the restore controller targeting the new course.
        $rc = new restore_controller(
            basename($restorepath),
            $newcourse->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        try {
            // Run the precheck to detect warnings and errors.
            if (!$rc->execute_precheck()) {
                $results = $rc->get_precheck_results();
                foreach ($results['warnings'] as $warning) {
                    $this->feedback['needed'][$courseshortname]['warning'][] = $warning;
                }
                foreach ($results['errors'] as $error) {
                    $this->feedback['needed'][$courseshortname]['error'][] = $error;
                }
                $rc->destroy();
                fulldelete($destination);
                return;
            }

            // Execute the restore plan.
            $rc->execute_plan();
        } catch (\Exception $e) {
            $this->feedback['needed'][$newcourse->shortname]['error'][] =
                get_string('restoreerror', 'tool_wbinstaller', $e->getMessage());
        } finally {
            // Always clean up the restore controller and temporary files.
            $rc->destroy();
            fulldelete($destination);
        }
    }

    /**
     * Recursively copy a directory and all its contents.
     *
     * Copies all files and subdirectories from the source to the destination path.
     * Creates the destination directory if it does not exist.
     *
     * @param string $src The source directory path.
     * @param string $dst The destination directory path.
     * @return bool Returns true on success.
     */
    public function copy_directory($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copy_directory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
        return true;
    }
}
