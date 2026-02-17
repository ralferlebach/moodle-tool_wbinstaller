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
 * Question installer for importing questions from Moodle XML files.
 *
 * This class handles the import of question bank questions from XML files
 * using Moodle's qformat_xml import functionality as part of the Wunderbyte
 * installer process.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wbinstaller;

use core_question\local\bank\question_edit_contexts;
use context_course;
use Exception;

/**
 * Installer class for importing Moodle XML question files into the question bank.
 *
 * Extends the base wbInstaller to provide functionality for discovering XML
 * question files, creating qformat_xml importers, and executing the question
 * import process.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionsInstaller extends wbInstaller {

    /**
     * Constructor for the questionsInstaller.
     *
     * Initializes the installer with the given recipe configuration
     * and sets the progress counter to zero.
     *
     * @param string $recipe The recipe configuration array containing the questions path.
     */
    public function __construct($recipe) {
        $this->recipe = $recipe;
        $this->progress = 0;
    }

    /**
     * Execute the question import process.
     *
     * Iterates over all XML files in the questions directory, creates a
     * qformat_xml importer for each file, and executes the import process.
     * Reports success or error feedback for each file.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance.
     * @return int Returns 1 on completion.
     */
    public function execute($extractpath, $parent = null) {
        $this->parent = $parent;
        $questionspath = $extractpath . $this->recipe['path'];

        foreach (glob("$questionspath/*") as $questionfile) {
            try {
                $qformat = $this->create_qformat($questionfile, 1);
                $qformat->importprocess();
                $this->feedback['needed'][basename($questionfile)]['success'][] =
                  get_string('questionsuccesinstall', 'tool_wbinstaller');
            } catch (Exception $e) {
                $this->feedback['needed'][basename($questionfile)]['error'][] = $e;
            }
        }
        return 1;
    }

    /**
     * Pre-check the question files before execution.
     *
     * Scans the questions directory for XML files and reports each
     * found file as a success feedback entry.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance.
     * @return void
     */
    public function check($extractpath, $parent) {
        $questionspath = $extractpath . $this->recipe['path'];
        foreach (glob("$questionspath/*") as $questionfile) {
            $this->feedback['needed'][basename($questionfile)]['success'][] =
              get_string('questionfilefound', 'tool_wbinstaller');
        }
    }

    /**
     * Create a qformat_xml object configured for importing a question file.
     *
     * Sets up the XML question format importer with the appropriate course context,
     * file paths, grade matching behaviour, and category/context import settings.
     *
     * NOTE: Import configuration pattern adapted from core qformat_xml_import_export_test.php.
     *
     * @param string $filename The absolute path to the XML question file.
     * @param int $courseid The course ID to use as the import context.
     * @return \qformat_xml The configured XML question format importer object.
     */
    private function create_qformat($filename, $courseid) {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->dirroot . '/question/format.php');

        $course = get_course($courseid);

        $qformat = new \qformat_xml();
        $qformat->setContexts((new question_edit_contexts(context_course::instance($courseid)))->all());
        $qformat->setCourse($course);
        // $qformat->setFilename(__DIR__ . '/../fixtures/' . $filename);
        $qformat->setRealfilename($filename);
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(1);
        $qformat->setContextfromfile(1);
        $qformat->setStoponerror(1);
        $qformat->setCattofile(1);
        $qformat->setContexttofile(1);
        $qformat->set_display_progress(false);
        $qformat->setFilename($filename);
        return $qformat;
    }
}
