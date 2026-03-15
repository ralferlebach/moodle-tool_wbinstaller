<?php
namespace tool_wbinstaller\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class recipe_upload_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $draftitemid = $this->_customdata['draftitemid'] ?? 0;

        $mform->addElement('header', 'uploadheader', get_string('pluginname', 'tool_wbinstaller'));
        $mform->addElement(
            'filemanager',
            'recipefile',
            get_string('uploadbuttontext', 'tool_wbinstaller'),
            null,
            [
                'subdirs' => 0,
                'maxfiles' => 1,
                'accepted_types' => ['.zip'],
                'return_types' => FILE_INTERNAL,
                'maxbytes' => 0,
            ]
        );
        $mform->setDefault('recipefile', $draftitemid);
        $mform->addHelpButton('recipefile', 'uploadbuttontext', 'tool_wbinstaller');

        $mform->addElement('static', 'recipehint', '', get_string('vuechooserecipe', 'tool_wbinstaller'));
    }
}
