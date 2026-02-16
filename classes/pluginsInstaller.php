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
 * Entities Class to display list of entity records.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_wbinstaller;

use core_plugin_manager;
use moodle_url;
use context_system;
use core_component;
use Exception;
use stdClass;
use tool_installaddon_installer;

/**
 * Class tool_wbinstaller
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../../../lib/setup.php');
global $CFG;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/upgradelib.php');

\require_login();
\require_capability('moodle/site:config', context_system::instance());
// Set up the admin external page.
\admin_externalpage_setup('tool_wbinstaller');

/**
 * Class pluginsInstaller
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pluginsInstaller extends wbInstaller {
    /** @var object Known subplugins. */
    public $knownsubplugins;
    /** @var tool_installaddon_installer Plugin installer. */
    protected $addoninstaller;
    /** @var string version file. */
    protected $plugincontent;

    /**
     * Entities constructor.
     * @param array $recipe
     */
    public function __construct($recipe) {
        $this->recipe = $recipe;
        $this->progress = 0;
        $this->addoninstaller = tool_installaddon_installer::instance();
        $this->plugincontent = null;
        $this->knownsubplugins = [
          'adaptivequizcatmodel_catquiz' => 'mod',
        ];
    }

    /**
     * Exceute the installer.
     * @param string $extractpath
     * @param \tool_wbinstaller\wbCheck $parent
     * @return int
     */
    public function execute($extractpath, $parent = null) {
        global $PAGE, $DB;
        $this->parent = $parent;
        // Set the page context.
        $PAGE->set_context(context_system::instance());
        $installer = tool_installaddon_installer::instance();
        $installables = [];
        if (isset($this->recipe)) {
            foreach ($this->recipe as $type => $plugins) {
                if ($type != "subplugins") {
                    foreach ($plugins as $gitzipurl) {
                        if (
                            $type != 'optional' ||
                            in_array($gitzipurl, $this->parent->optionalplugins)
                        ) {
                            $install = $this->check_plugin_compability($gitzipurl, $type, true);
                            if ($install != 2) {
                                $installable = $this->download_install_plugins_testing($gitzipurl, $type, $installer, $install);
                                if (isset($installable->component)) {
                                    $installables[] = $installable->component;
                                }
                                $this->manual_install_plugins($installable, $gitzipurl);
                            }
                        }
                    }
                }
            }
            if (!empty($installables)) {
                $this->trigger_upgrade_after_plugin_install($installables);
            }
        }
        return 1;
    }

    /**
     * Exceute the installer.
     * @param string $gitzipurl
     * @param string $type
     * @param mixed $installer
     * @param string $install
     */
    public function download_install_plugins_testing($gitzipurl, $type, $installer, $install) {
        global $CFG;
        $zipfile = $CFG->dataroot . '/wbinstaller/' . $install . '.zip';

        if (!file_exists($CFG->dataroot . '/wbinstaller')) {
            mkdir($CFG->dataroot . '/wbinstaller', 0777, true);
        }
        if (download_file_content($gitzipurl, null, null, true, 300, 20, true, $zipfile)) {
            $component = $installer->detect_plugin_component($zipfile);
            $plugin = $this->parse_version_file($this->plugincontent);
            return (object)[
                'component' => $component,
                'zipfilepath' => $zipfile,
                'url' => $gitzipurl,
                'type' => $type,
                'version' => $plugin['version'],
            ];
        } else {
            $this->feedback[$type][$gitzipurl]['error'][] = get_string('filedownloadfailed', 'tool_wbinstaller', $gitzipurl);
            $this->set_status(2);
        }
    }
    /**
     * Exceute the installer.
     * @param string $extractpath
     * @param \tool_wbinstaller\wbCheck $parent
     */
    public function check($extractpath, $parent) {
        foreach ($this->recipe as $type => $plugins) {
            if ($type != "subplugins") {
                foreach ($plugins as $gitzipurl) {
                    $this->check_plugin_compability($gitzipurl, $type);
                }
            }
        }
    }

    /**
     * Exceute the installer.
     * @param string $gitzipurl
     * @param string $type
     * @param bool $execute
     * @return int
     */
    public function check_plugin_compability($gitzipurl, $type, $execute = false) {
        global $CFG;
        $this->plugincontent = $this->get_github_file_content($gitzipurl);
        $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'tool_wbinstaller_settings']);
        if ($this->plugincontent) {
            $plugin = $this->parse_version_file($this->plugincontent);
            if (isset($plugin['component'])) {
                $installedversion = $this->is_component_installed($plugin['component']);
                $a = new stdClass();
                $a->name = $plugin['component'] ?? '';
                $a->installedversion = (int)$installedversion ?? '';
                $a->componentversion = (int)$plugin['version'] ?? '';
                $targetdir = $this->get_target_dir($plugin['component'], $type);

                if (!is_writable($targetdir)) {
                    $feedbacktarget = $targetdir;
                    $permissions = fileperms($targetdir);
                    $permissions = substr(sprintf('%o', $permissions), -4);
                    if ($permissions != '0') {
                        $feedbacktarget .= ' (' . $permissions . ')';
                    }
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                        get_string('targetdirnotwritable', 'tool_wbinstaller', $feedbacktarget);
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                        get_string('targetdirwritablecommand', 'tool_wbinstaller', $targetdir);
                    $this->set_status(2);
                    if ($execute) {
                        return 2;
                    }
                }
                if ($a->installedversion == 0) {
                    if ($execute) {
                        return $plugin['component'];
                    } else {
                        $this->feedback[$type][$plugin['component']]['success'][] =
                            get_string('pluginnotinstalled', 'tool_wbinstaller', $plugin['component']);
                    }
                } else if ($a->installedversion > $a->componentversion) {
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                      get_string('pluginduplicate', 'tool_wbinstaller', $a);
                    if ($execute) {
                        return $plugin['component'];
                    }
                } else if ($a->installedversion == $a->componentversion) {
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                      get_string('pluginsame', 'tool_wbinstaller', $a);
                    if ($execute) {
                        return 2;
                    }
                } else if ($a->installedversion < $a->componentversion) {
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                      get_string('pluginolder', 'tool_wbinstaller', $a);
                    if ($execute) {
                        return 2;
                    }
                }
            } else {
                $this->feedback[$type][$plugin['component']]['error'][] =
                  get_string('pluginfailedinformation', 'tool_wbinstaller', $settingsurl->out());
                $this->set_status(2);
                if ($execute) {
                    return 2;
                }
            }
        } else {
            $this->feedback[$type][$gitzipurl]['error'][] =
              get_string('pluginfailedinformation', 'tool_wbinstaller', $settingsurl->out());
            $this->set_status(2);
            if ($execute) {
                return 2;
            }
        }
        return 1;
    }

    /**
     * Exceute the installer.
     * @param string $url
     * @return string
     */
    public function get_github_file_content($url) {
        $urlparts = explode('/', parse_url($url, PHP_URL_PATH));
        $owner = $urlparts[1];
        $repo = $urlparts[2];
        $branchtag = null;
        if ($urlparts[5] == 'tags') {
            $branchtag = "?ref=refs/tags/" . str_replace('.zip', '', $urlparts[6]);
        } else if ($urlparts[6] != null) {
            $branchtag = "?ref=" . str_replace('.zip', '', $urlparts[6]);
        }
        $apiurl = "https://api.github.com/repos/" . $owner . "/" . $repo . "/contents/version.php" . $branchtag;
        $token = null;
        $apitoken = get_config('tool_wbinstaller', 'apitoken');
        if ($apitoken) {
            $token = 'token ' . $apitoken;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PHP-cURL-Request',
            'Authorization: ' . $token,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['content'])) {
            return base64_decode($data['content']);
        }
        return null;
    }

    /**
     * Exceute the installer.
     * @param string $content
     * @return array
     */
    public function parse_version_file($content) {
        $plugin = [];
        if (preg_match('/\$plugin->component\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $matches)) {
            $plugin['component'] = $matches[1];
        }
        if (preg_match('/\$plugin->version\s*=\s*([0-9]+)\s*;/', $content, $matches)) {
            $plugin['version'] = $matches[1];
        }
        return $plugin;
    }

    /**
     * Exceute the installer.
     * @param string $componentname
     */
    public function is_component_installed($componentname) {
        global $DB;
        $installedplugin = $DB->get_record('config_plugins', ['plugin' => $componentname, 'name' => 'version']);
        if ($installedplugin) {
            return $installedplugin->value;
        }
        return false;
    }

    /**
     * Exceute the installer.
     * @param string $componentname
     * @param string $type
     * @return string
     */
    public function get_target_dir($componentname, $type) {
        global $CFG;
        [$plugintype, $pluginname] = core_component::normalize_component($componentname);
        $pluginman = core_plugin_manager::instance();
        $targetdir = $pluginman->get_plugintype_root($plugintype);
        if (
            $targetdir == null &&
            isset($this->recipe['subplugins'][$plugintype])
        ) {
            $targetdir = $CFG->dirroot . $this->recipe['subplugins'][$plugintype];
            $this->feedback[$type][$componentname]['warning'][] =
                get_string('targetdirsubplugin', 'tool_wbinstaller', $this->recipe['subplugins'][$plugintype]);
        }
        return $targetdir;
    }

    /**
     * Exceute the installer.
     * @param array $installable
     */
    public function upgrade_install_plugins_recipe($installable) {
        if (!empty($installable)) {
            try {
                ob_start();
                upgrade_install_plugins(
                    $installable,
                    1,
                    get_string('installfromzip', 'tool_wbinstaller'),
                    new moodle_url('/admin/tool/wbinstaller/index.php', ['installzipconfirm' => 1])
                );
                ob_end_clean();
            } catch (Exception $e) {
                $this->feedback['plugins']['error'][] = 'Plugin installation error: ' . $e->getMessage();
            }
        }
    }

    /**
     * Manual plugin installation.
     * @param stdClass $installable
     * @param string $giturl
     */
    public function manual_install_plugins($installable, $giturl) {
        global $CFG, $DB;
        if (!empty($installable)) {
            $zipfile = $installable->zipfilepath;
            $component = $installable->component;
            if (!$component) {
                $this->feedback[$installable->type][$installable->component]['error'][] =
                    get_string('plugincomponentdetectfailed', 'tool_wbinstaller');
            }
            $targetdir = $this->get_target_dir($component, $installable->type);
            [$plugintype, $pluginname] = core_component::normalize_component($component);

            // Check if it's a core plugin or a subplugin.
            if (!is_dir($targetdir)) {
                $result = mkdir($targetdir, 0777, true);
                if (!$result) {
                    // Check if the directory was not created due to insufficient permissions.
                    if (is_dir($targetdir)) {
                        $this->feedback[$installable->type][$component]['error'][] =
                          get_string('jsonfailalreadyexist', 'tool_wbinstaller', $targetdir);
                    } else {
                        $this->feedback[$installable->type][$component]['error'][] =
                          get_string('jsonfailinsufficientpermission', 'tool_wbinstaller', $targetdir);
                    }
                }
            }
            $zip = new \ZipArchive();
            if ($zip->open($zipfile) === true) {
                $tempdirplugin = str_replace('.zip', '', $zipfile);
                if (!is_dir($tempdirplugin)) {
                    $result = mkdir($tempdirplugin, 0777, true);
                    if (!$result) {
                        // Similar check for temporary directory creation.
                        if (is_dir($tempdirplugin)) {
                            $this->feedback[$installable->type][$component]['error'][] =
                              get_string('jsonfailalreadyexist', 'tool_wbinstaller', $tempdirplugin);
                        } else {
                            $this->feedback[$installable->type][$component]['error'][] =
                              get_string('jsonfailinsufficientpermission', 'tool_wbinstaller', $tempdirplugin);
                        }
                    }
                }
                $zip->extractTo($tempdirplugin);
                $zip->close();
                $extracteddirname = null;
                $handle = opendir($tempdirplugin);
                while (($entry = readdir($handle)) !== false) {
                    if ($entry != '.' && $entry != '..' && is_dir($tempdirplugin . '/' . $entry)) {
                        $extracteddirname = $entry;
                        break;
                    }
                }
                closedir($handle);
                if ($extracteddirname) {
                    $finaldir = $targetdir . '/' . $pluginname;
                    rename($tempdirplugin . '/' . $extracteddirname, $finaldir);
                    rmdir($tempdirplugin);
                    self::connect_to_github_repository($finaldir, $giturl);
                    $this->feedback[$installable->type][$installable->component] = [
                        'success' => [
                          get_string('upgradeplugincompleted', 'tool_wbinstaller', $installable->component),
                        ],
                    ];
                } else {
                    $this->feedback[$installable->type][$installable->component]['error'][] =
                      get_string('installerfailfinddir', 'tool_wbinstaller', $installable->component);
                    $this->set_status(2);
                }
                unlink($zipfile);
            } else {
                $this->feedback[$installable->type][$installable->component]['error'][] =
                  get_string('installerfailextract', 'tool_wbinstaller', $installable->component);
                $this->set_status(2);
            }
        }
    }

    /**
     * Function to trigger Moodle's upgrade.php script.
     * @param string $directorypath
     * @param string $gitzipurl
     */
    private static function connect_to_github_repository($directorypath, $gitzipurl) {
        if (!is_dir($directorypath)) {
            return;
        }
        self::initialize_git_drectory($directorypath);
        self::mark_directory_as_save($directorypath);
        self::set_remote_repository($directorypath, $gitzipurl);
        return;
    }

    /**
     * Initialize the git repo
     * @param string $directorypath
     * @param string $gitzipurl
     */
    private static function set_remote_repository($directorypath, $gitzipurl) {
        $gitrepositoryurl = self::get_git_url_from_zip_url($gitzipurl);
        $cmd = sprintf('cd %s && git remote add origin %s 2>&1', escapeshellarg($directorypath), escapeshellarg($gitrepositoryurl));
        exec($cmd, $output, $retval);
        return;
    }

    /**
     * Initialize the git repo
     * @param string $directorypath
     */
    private static function mark_directory_as_save($directorypath) {
        $cmd = sprintf(
            "git -C %s config --add safe.directory %s 2>&1",
            escapeshellarg($directorypath),
            escapeshellarg($directorypath)
        );
        $output = [];
        $retval = null;
        exec($cmd, $output, $retval);
        return;
    }

    /**
     * Initialize the git repo
     * @param string $directorypath
     */
    private static function initialize_git_drectory($directorypath) {
        $cmd = sprintf('cd %s && git init 2>&1', escapeshellarg($directorypath));
        $output = [];
        $retval = null;
        exec($cmd, $output, $retval);
        return;
    }

    /**
     * Extract the git url from the zip url.
     * @param string $zipurl
     */
    private static function get_git_url_from_zip_url($zipurl) {
        $parsedurl = parse_url($zipurl);
        $path = $parsedurl['path'];
        $gitpath = preg_replace('#/archive/refs/(heads|tags)/.*$#', '.git', $path);
        return $parsedurl['scheme'] . '://' . $parsedurl['host'] . $gitpath;
    }

    /**
     * Function to trigger Moodle's upgrade.php script.
     * @param array $installables
     */
    private function trigger_upgrade_after_plugin_install($installables) {
        global $CFG;
        $output = null;
        $retval = null;
        if (!function_exists('exec')) {
            $this->set_status(3);
            $this->feedback['needed']['phpcli']['error'][] = get_string(
                'execdisabled',
                'tool_wbinstaller'
            );
            return;
        }
        $phptocli = get_config('core', 'pathtophp');
        if (empty($phptocli)) {
            $this->set_status(3);
            return;
        }
        $cmd = $phptocli . ' ' . escapeshellarg($CFG->dirroot . '/admin/cli/upgrade.php') . ' --non-interactive 2>&1';
        exec($cmd, $output, $retval);
        if ($retval === 0) {
            if ($output) {
                $this->feedback['needed']['phpcli']['warning'][] =
                      get_string('installerwarningextractcode', 'tool_wbinstaller', implode("\n", $output));
            }
            foreach ($installables as $installable) {
                $instconfig = get_config($installable);
                if (!isset($instconfig->version)) {
                    $this->set_status(3);
                }
            }
            $this->set_status(0);
        } else {
            $this->set_status(3);
            if (!empty($output)) {
                $this->feedback['needed']['phpcli']['error'][] =
                      get_string('installerfailextract', 'tool_wbinstaller', implode("\n", $output));
            } else {
                $this->feedback['needed']['phpcli']['error'][] =
                      get_string('installerfailextractcode', 'tool_wbinstaller', $retval);
            }
        }
    }
}
