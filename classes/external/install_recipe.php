<?php
declare(strict_types=1);

namespace tool_wbinstaller\external;

defined('MOODLE_INTERNAL') || die();

use context;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use tool_wbinstaller\local\recipe_file;
use tool_wbinstaller\wbInstaller;

require_once($CFG->libdir . '/externallib.php');

class install_recipe extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'contextid' => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'draftitemid' => new external_value(PARAM_INT, 'recipe draft item id', VALUE_REQUIRED),
            'optionalplugins' => new external_value(PARAM_RAW, 'optional plugins', VALUE_DEFAULT, '[]'),
        ]);
    }

    public static function execute($userid, $contextid, $draftitemid, $optionalplugins): array {
        global $USER;

        require_login();
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'contextid' => $contextid,
            'draftitemid' => $draftitemid,
            'optionalplugins' => $optionalplugins,
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

            $wbinstaller = new wbInstaller($base64, $filename, (string)$params['optionalplugins']);
            $response = $wbinstaller->execute('ajax');

            return [
                'feedback' => json_encode($response['feedback'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => (int)$response['status'],
                'finished' => json_encode($response['finished'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'filename' => $filename,
            ];
        } catch (\Throwable $e) {
            throw new \moodle_exception('recipeinstallfailed', 'tool_wbinstaller', '', $e->getMessage());
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'feedback' => new external_value(PARAM_RAW, 'Feedback message'),
            'status' => new external_value(PARAM_INT, 'Status'),
            'finished' => new external_value(PARAM_RAW, 'Finished status'),
            'filename' => new external_value(PARAM_FILE, 'Uploaded filename'),
        ]);
    }
}
