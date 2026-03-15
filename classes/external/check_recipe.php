<?php
declare(strict_types=1);

namespace tool_wbinstaller\external;

defined('MOODLE_INTERNAL') || die();

use context;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use tool_wbinstaller\local\recipe_file;
use tool_wbinstaller\wbCheck;

require_once($CFG->libdir . '/externallib.php');

class check_recipe extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'contextid' => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'draftitemid' => new external_value(PARAM_INT, 'recipe draft item id', VALUE_REQUIRED),
        ]);
    }

    public static function execute($userid, $contextid, $draftitemid): array {
        global $USER;

        require_login();
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'contextid' => $contextid,
            'draftitemid' => $draftitemid,
        ]);

        $context = context::instance_by_id((int)$params['contextid']);
        self::validate_context($context);
        require_capability('tool/wbinstaller:caninstall', $context);

        if ((int)$params['userid'] !== (int)$USER->id) {
            throw new \moodle_exception('invaliduser');
        }

        try {
            $filename = recipe_file::get_filename((int)$params['draftitemid']);
            $base64 = recipe_file::get_base64_contents((int)$params['draftitemid']);
            $wbcheck = new wbCheck($base64, $filename);
            $response = $wbcheck->execute();

            return [
                'feedback' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'filename' => $filename,
                'optionalplugins' => json_encode(recipe_file::get_optional_plugins((int)$params['draftitemid']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'summary' => recipe_file::build_summary((int)$params['draftitemid']),
            ];
        } catch (\Throwable $e) {
            throw new \moodle_exception('recipecheckfailed', 'tool_wbinstaller', '', $e->getMessage());
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'feedback' => new external_value(PARAM_RAW, 'Feedback as JSON string'),
            'filename' => new external_value(PARAM_FILE, 'Uploaded filename'),
            'optionalplugins' => new external_value(PARAM_RAW, 'Optional plugins as JSON string'),
            'summary' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Section title'),
                    'count' => new external_value(PARAM_INT, 'Number of items'),
                    'items' => new external_multiple_structure(
                        new external_single_structure([
                            'text' => new external_value(PARAM_RAW, 'Item text'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
