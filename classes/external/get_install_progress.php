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
 * This class contains a list of webservice functions related to the adele Module by Wunderbyte.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace tool_wbinstaller\external;

use context;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use tool_wbinstaller\wbInstaller;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local catquiz.
 *
 * @package     tool_installer
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_install_progress extends external_api {
    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'filename'  => new external_value(PARAM_TEXT, 'filename', VALUE_REQUIRED),
            ]);
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $userid
     * @param int $contextid
     * @param string $filename
     * @return bool
     */
    public static function execute(
        $userid,
        $contextid,
        $filename
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'contextid' => $contextid,
            'filename' => $filename,
        ]);

        require_login();

        $context = context::instance_by_id($contextid);
        require_capability('tool/wbinstaller:caninstall', $context);
        $progress = wbInstaller::get_install_progress($params['filename']);
        return (array)$progress;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'progress' => new external_value(PARAM_INT, 'Result Status'),
            'subprogress' => new external_value(PARAM_INT, 'Result Status'),
            ]);
    }
}
