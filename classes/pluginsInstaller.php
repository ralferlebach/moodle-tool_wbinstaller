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
 * Plugin installer for downloading and installing Moodle plugins from GitHub.
 *
 * This class handles the automated download, compatibility checking, extraction,
 * and installation of Moodle plugins from GitHub ZIP archives. It supports
 * required, optional, and sub-plugin types, and can initialize git repositories
 * in the installed plugin directories.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_wbinstaller;

use core_plugin_manager;
use moodle_url;
use context_system;
use core_component;
use Exception;
use stdClass;
use tool_installaddon_installer;

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../../../lib/setup.php');
global $CFG;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/upgradelib.php');

\require_login();
\require_capability('moodle/site:config', context_system::instance());
\admin_externalpage_setup('tool_wbinstaller');

/**
 * Installer class for Moodle plugins downloaded from GitHub repositories.
 *
 * Extends the base wbInstaller to provide functionality for downloading plugin
 * ZIP archives from GitHub, checking version compatibility against installed plugins,
 * extracting and placing plugin files in the correct directory, initializing git
 * repositories, and triggering the Moodle upgrade process.
 *
 * @package     tool_wbinstaller
 * @author      Jacob Viertel
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pluginsInstaller extends wbInstaller {
    /** @var object Associative array of known subplugin types and their parent plugin types. */
    public $knownsubplugins;
    /** @var tool_installaddon_installer Moodle's built-in addon installer instance. */
    protected $addoninstaller;
    /** @var string|null Raw content of the plugin's version.php file fetched from GitHub. */
    protected $plugincontent;

    /**
     * Constructor for the pluginsInstaller.
     *
     * Initializes the installer with the given recipe, sets up the addon installer
     * instance, and defines known subplugin type mappings.
     *
     * @param array $recipe Associative array of plugin types and their GitHub ZIP URLs.
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
     * Execute the plugin installation process.
     *
     * Iterates over all plugin types (excluding 'subplugins') in the recipe.
     * For each plugin URL, checks compatibility, downloads the ZIP archive,
     * extracts and installs the plugin manually, and triggers the Moodle
     * upgrade process for all successfully installed plugins.
     *
     * @param string $extractpath The base extraction path of the installer package.
     * @param \tool_wbinstaller\wbCheck|null $parent The parent installer instance providing optional plugin selections.
     * @return int Returns 1 on completion.
     */
    public function execute($extractpath, $parent = null) {
        global $PAGE, $DB;
        $this->parent = $parent;
        $PAGE->set_context(context_system::instance());
        $installer = tool_installaddon_installer::instance();
        $installables = [];

        if (isset($this->recipe)) {
            foreach ($this->recipe as $type => $plugins) {
                if ($type != "subplugins") {
                    foreach ($plugins as $gitzipurl) {
                        // Skip optional plugins that were not selected by the user.
                        if (
                            $type != 'optional' ||
                            in_array($gitzipurl, $this->parent->optionalplugins)
                        ) {
                            $install = $this->check_plugin_compability($gitzipurl, $type, true);
                            if ($install != 2) {
                                $installable = $this->download_install_plugins_testing(
                                    $gitzipurl,
                                    $type,
                                    $installer,
                                    $install
                                );
                                if (isset($installable->component)) {
                                    $installables[] = $installable->component;
                                }
                                $this->manual_install_plugins($installable, $gitzipurl);
                            }
                        }
                    }
                }
            }
            // Trigger Moodle upgrade after all plugins are installed.
            if (!empty($installables)) {
                $this->trigger_upgrade_after_plugin_install($installables);
            }
        }
        return 1;
    }

    /**
     * Download a plugin ZIP archive and detect its component name.
     *
     * Downloads the plugin ZIP file from the given GitHub URL, saves it to the
     * Moodle data directory, and uses the addon installer to detect the plugin's
     * component name. Also parses the version.php content for version information.
     *
     * @param string $gitzipurl The GitHub ZIP download URL.
     * @param string $type The plugin type category (e.g., 'required', 'optional').
     * @param mixed $installer The Moodle addon installer instance.
     * @param string $install The component name to use as the ZIP filename.
     * @return object|void Object with component, zipfilepath, url, type, and version; or void on failure.
     */
    public function download_install_plugins_testing($gitzipurl, $type, $installer, $install) {
        global $CFG;
        $zipfile = $CFG->dataroot . '/wbinstaller/' . $install . '.zip';

        // Ensure the wbinstaller directory exists.
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
            $this->feedback[$type][$gitzipurl]['error'][] =
                get_string('filedownloadfailed', 'tool_wbinstaller', $gitzipurl);
            $this->set_status(2);
        }
    }

    /**
     * Pre-check all plugins in the recipe for compatibility.
     *
     * Iterates over all plugin types (excluding 'subplugins') and checks
     * each plugin URL for version compatibility with the current Moodle installation.
     *
     * @param string $extractpath The base extraction path of the installer package (unused).
     * @param \tool_wbinstaller\wbCheck $parent The parent installer instance.
     * @return void
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
     * Check version compatibility of a single plugin against the installed version.
     *
     * Fetches the plugin's version.php from GitHub, parses its component name and version,
     * and compares it against the currently installed version. Reports appropriate feedback
     * (not installed, newer, same, older) and checks target directory writability.
     *
     * @param string $gitzipurl The GitHub ZIP download URL for the plugin.
     * @param string $type The plugin type category (e.g., 'required', 'optional').
     * @param bool $execute If true, returns a status code for the execute workflow instead of only reporting.
     * @return int|string Returns the component name if installable, or 2 if skipped (during execute); 1 on check-only.
     */
    public function check_plugin_compability($gitzipurl, $type, $execute = false) {
        global $CFG;
        $this->plugincontent = $this->get_github_file_content($gitzipurl);
        $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'tool_wbinstaller_settings']);

        if ($this->plugincontent) {
            $plugin = $this->parse_version_file($this->plugincontent);
            if (isset($plugin['component'])) {
                $installedversion = $this->is_component_installed($plugin['component']);

                // Build comparison object for language string placeholders.
                $a = new stdClass();
                $a->name = $plugin['component'] ?? '';
                $a->installedversion = (int)$installedversion ?? '';
                $a->componentversion = (int)$plugin['version'] ?? '';
                $targetdir = $this->get_target_dir($plugin['component'], $type);

                // Check if the target directory is writable.
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
                    // Plugin is not yet installed — proceed with installation.
                    if ($execute) {
                        return $plugin['component'];
                    } else {
                        $this->feedback[$type][$plugin['component']]['success'][] =
                            get_string('pluginnotinstalled', 'tool_wbinstaller', $plugin['component']);
                    }
                } else if ($a->installedversion > $a->componentversion) {
                    // Installed version is newer than the recipe version.
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                      get_string('pluginduplicate', 'tool_wbinstaller', $a);
                    if ($execute) {
                        return $plugin['component'];
                    }
                } else if ($a->installedversion == $a->componentversion) {
                    // Same version is already installed — skip.
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                      get_string('pluginsame', 'tool_wbinstaller', $a);
                    if ($execute) {
                        return 2;
                    }
                } else if ($a->installedversion < $a->componentversion) {
                    // Installed version is older — skip (avoid downgrade conflicts).
                    $this->feedback[$type][$plugin['component']]['warning'][] =
                      get_string('pluginolder', 'tool_wbinstaller', $a);
                    if ($execute) {
                        return 2;
                    }
                }
            } else {
                // Component name could not be extracted from version.php.
                $this->feedback[$type][$plugin['component']]['error'][] =
                  get_string('pluginfailedinformation', 'tool_wbinstaller', $settingsurl->out());
                $this->set_status(2);
                if ($execute) {
                    return 2;
                }
            }
        } else {
            // Failed to fetch version.php from GitHub.
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
     * Fetch the version.php file content from a GitHub repository via the GitHub API.
     *
     * Parses the GitHub ZIP URL to extract the owner, repository, and branch/tag reference,
     * then queries the GitHub API for the version.php file content. Uses an optional
     * API token from the plugin settings for authentication.
     *
     * @param string $url The GitHub ZIP download URL.
     * @return string|null The decoded version.php content, or null if retrieval failed.
     */
    public function get_github_file_content($url) {
        // Parse URL components to build the GitHub API request.
        $urlparts = explode('/', parse_url($url, PHP_URL_PATH));
        $owner = $urlparts[1];
        $repo = $urlparts[2];
        $branchtag = null;

        // Determine if the URL points to a tag or a branch.
        if ($urlparts[5] == 'tags') {
            $branchtag = "?ref=refs/tags/" . str_replace('.zip', '', $urlparts[6]);
        } else if ($urlparts[6] != null) {
            $branchtag = "?ref=" . str_replace('.zip', '', $urlparts[6]);
        }

        $apiurl = "https://api.github.com/repos/" . $owner . "/" . $repo . "/contents/version.php" . $branchtag;

        // Retrieve optional GitHub API token from plugin configuration.
        $token = null;
        $apitoken = get_config('tool_wbinstaller', 'apitoken');
        if ($apitoken) {
            $token = 'token ' . $apitoken;
        }

        // Execute the API request via cURL.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PHP-cURL-Request',
            'Authorization: ' . $token,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        // Decode the API response and extract the base64-encoded file content.
        $data = json_decode($response, true);
        if (isset($data['content'])) {
            return base64_decode($data['content']);
        }
        return null;
    }

    /**
     * Parse a version.php file content to extract the component name and version number.
     *
     * Uses regular expressions to find the $plugin->component and $plugin->version
     * assignments within the version.php content string.
     *
     * @param string $content The raw content of the version.php file.
     * @return array Associative array with optional keys 'component' and 'version'.
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
     * Check whether a Moodle plugin component is currently installed.
     *
     * Queries the config_plugins database table for the version entry of the
     * given component name.
     *
     * @param string $componentname The full Moodle component name (e.g., 'mod_adaptivequiz').
     * @return string|false The installed version number, or false if not installed.
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
     * Determine the target installation directory for a plugin component.
     *
     * Uses the Moodle plugin manager to resolve the plugin type root directory.
     * Falls back to subplugin paths defined in the recipe if the plugin type
     * is not recognized by the standard plugin manager.
     *
     * @param string $componentname The full Moodle component name.
     * @param string $type The plugin type category from the recipe.
     * @return string The absolute target directory path.
     */
    public function get_target_dir($componentname, $type) {
        global $CFG;
        [$plugintype, $pluginname] = core_component::normalize_component($componentname);
        $pluginman = core_plugin_manager::instance();
        $targetdir = $pluginman->get_plugintype_root($plugintype);

        // Fall back to subplugin path from recipe if plugin type is unknown.
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
     * Trigger Moodle's built-in upgrade_install_plugins process.
     *
     * Wraps the Moodle core upgrade_install_plugins() function in output buffering
     * to suppress direct output. Catches and reports any exceptions that occur
     * during the process.
     *
     * @param array $installable Array of installable plugin component objects.
     * @return void
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
     * Manually install a plugin by extracting its ZIP archive to the target directory.
     *
     * Extracts the downloaded ZIP file into a temporary directory, identifies the
     * extracted plugin folder, moves it to the correct Moodle plugin directory,
     * and initializes a git repository connected to the source GitHub URL.
     *
     * @param stdClass|null $installable Object containing component, zipfilepath, url, type, and version.
     * @param string $giturl The original GitHub ZIP URL for git remote configuration.
     * @return void
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

            // Create the target directory if it does not exist.
            if (!is_dir($targetdir)) {
                $result = mkdir($targetdir, 0777, true);
                if (!$result) {
                    if (is_dir($targetdir)) {
                        $this->feedback[$installable->type][$component]['error'][] =
                          get_string('jsonfailalreadyexist', 'tool_wbinstaller', $targetdir);
                    } else {
                        $this->feedback[$installable->type][$component]['error'][] =
                          get_string('jsonfailinsufficientpermission', 'tool_wbinstaller', $targetdir);
                    }
                }
            }

            // Extract the ZIP file and move the plugin to its final location.
            $zip = new \ZipArchive();
            if ($zip->open($zipfile) === true) {
                $tempdirplugin = str_replace('.zip', '', $zipfile);
                if (!is_dir($tempdirplugin)) {
                    $result = mkdir($tempdirplugin, 0777, true);
                    if (!$result) {
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

                // Find the top-level extracted directory name.
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
                    // Move the extracted plugin directory to the final target location.
                    $finaldir = $targetdir . '/' . $pluginname;
                    rename($tempdirplugin . '/' . $extracteddirname, $finaldir);
                    rmdir($tempdirplugin);

                    // Initialize git and connect to the source repository.
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
     * Initialize a git repository in the plugin directory and connect it to the GitHub remote.
     *
     * Runs git init, marks the directory as safe, and adds the GitHub repository
     * as the 'origin' remote. Only operates on existing directories.
     *
     * @param string $directorypath The absolute path to the installed plugin directory.
     * @param string $gitzipurl The GitHub ZIP URL used to derive the git repository URL.
     * @return void
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
     * Set the git remote 'origin' to the derived GitHub repository URL.
     *
     * Converts the GitHub ZIP download URL to a .git repository URL and
     * adds it as the 'origin' remote for the given directory.
     *
     * @param string $directorypath The absolute path to the git-initialized directory.
     * @param string $gitzipurl The GitHub ZIP download URL.
     * @return void
     */
    private static function set_remote_repository($directorypath, $gitzipurl) {
        $gitrepositoryurl = self::get_git_url_from_zip_url($gitzipurl);
        $cmd = sprintf(
            'cd %s && git remote add origin %s 2>&1',
            escapeshellarg($directorypath),
            escapeshellarg($gitrepositoryurl)
        );
        exec($cmd, $output, $retval);
        return;
    }

    /**
     * Mark the directory as a safe git directory to avoid ownership warnings.
     *
     * Executes 'git config --add safe.directory' for the given path to prevent
     * git from rejecting operations due to ownership mismatches.
     *
     * @param string $directorypath The absolute path to the git directory.
     * @return void
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
     * Initialize a new git repository in the given directory.
     *
     * Executes 'git init' to create a fresh git repository.
     *
     * @param string $directorypath The absolute path where the git repository should be initialized.
     * @return void
     */
    private static function initialize_git_drectory($directorypath) {
        $cmd = sprintf('cd %s && git init 2>&1', escapeshellarg($directorypath));
        $output = [];
        $retval = null;
        exec($cmd, $output, $retval);
        return;
    }

    /**
     * Convert a GitHub ZIP download URL to a .git repository URL.
     *
     * Strips the '/archive/refs/(heads|tags)/...' suffix from the URL path
     * and appends '.git' to produce a valid git clone URL.
     *
     * @param string $zipurl The GitHub ZIP download URL.
     * @return string The derived git repository URL (e.g., 'https://github.com/owner/repo.git').
     */
    private static function get_git_url_from_zip_url($zipurl) {
        $parsedurl = parse_url($zipurl);
        $path = $parsedurl['path'];
        $gitpath = preg_replace('#/archive/refs/(heads|tags)/.*$#', '.git', $path);
        return $parsedurl['scheme'] . '://' . $parsedurl['host'] . $gitpath;
    }

    /**
     * Trigger the Moodle CLI upgrade process after plugin installation.
     *
     * Executes the Moodle admin/cli/upgrade.php script non-interactively via
     * the PHP CLI binary. Verifies the exec() function is available and the
     * PHP CLI path is configured. Reports success, warnings, or errors based
     * on the exit code and output of the upgrade process.
     *
     * @param array $installables Array of component names that were installed.
     * @return void
     */
    private function trigger_upgrade_after_plugin_install($installables) {
        global $CFG;
        $output = null;
        $retval = null;

        // Verify that exec() is available on this system.
        if (!function_exists('exec')) {
            $this->set_status(3);
            $this->feedback['needed']['phpcli']['error'][] = get_string(
                'execdisabled',
                'tool_wbinstaller'
            );
            return;
        }

        // Verify that the PHP CLI path is configured.
        $phptocli = get_config('core', 'pathtophp');
        if (empty($phptocli)) {
            $this->set_status(3);
            return;
        }

        // Execute the Moodle CLI upgrade script.
        $cmd = $phptocli . ' ' . escapeshellarg($CFG->dirroot . '/admin/cli/upgrade.php') . ' --non-interactive 2>&1';
        exec($cmd, $output, $retval);

        if ($retval === 0) {
            if ($output) {
                $this->feedback['needed']['phpcli']['warning'][] =
                      get_string('installerwarningextractcode', 'tool_wbinstaller', implode("\n", $output));
            }
            // Verify each plugin was successfully registered after upgrade.
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
