<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/form/recipe_upload_form.php');

admin_externalpage_setup('tool_wbinstaller');

$url = new moodle_url('/admin/tool/wbinstaller/index.php');
$context = context_system::instance();
$draftitemid = file_get_unused_draft_itemid();
file_prepare_draft_area($draftitemid, context_user::instance($USER->id)->id, 'user', 'draft', 0, ['subdirs' => 0, 'maxfiles' => 1]);

$mform = new \tool_wbinstaller\form\recipe_upload_form($url->out(false), [
    'draftitemid' => $draftitemid,
    'context' => $context,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'tool_wbinstaller'));
$PAGE->set_heading(get_string('pluginname', 'tool_wbinstaller'));
$PAGE->requires->js_call_amd('tool_wbinstaller/app', 'init', [
    'contextid' => $context->id,
    'userid' => $USER->id,
    'selectors' => [
        'root' => '#tool-wbinstaller-app',
        'draftitemid' => 'input[name="recipefile"]',
        'check' => '[data-action="check-recipe"]',
        'install' => '[data-action="install-recipe"]',
        'optionalplugins' => '[data-region="optional-plugins"]',
        'summary' => '[data-region="recipe-summary"]',
        'feedback' => '[data-region="feedback"]',
        'status' => '[data-region="status"]',
        'loader' => '[data-region="loader"]',
        'filename' => '[data-region="filename"]',
    ],
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('tool_wbinstaller/initview', [
    'form' => $mform->render(),
    'userid' => $USER->id,
    'contextid' => $context->id,
]);
echo $OUTPUT->footer();
