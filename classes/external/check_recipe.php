<?php
namespace tool_wbinstaller\external;

declare(strict_types=1);

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
        require_login();
        $context = context::instance_by_id($contextid);
        require_capability('tool/wbinstaller:caninstall', $context);

        $filename = recipe_file::get_filename((int)$draftitemid);
        $base64 = recipe_file::get_base64_contents((int)$draftitemid);
        $wbcheck = new wbCheck($base64, $filename);
        $response = $wbcheck->execute();

        return [
            'feedback' => json_encode($response),
            'filename' => $filename,
            'optionalplugins' => json_encode(recipe_file::get_optional_plugins((int)$draftitemid)),
            'summary' => recipe_file::build_summary((int)$draftitemid),
        ];
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
