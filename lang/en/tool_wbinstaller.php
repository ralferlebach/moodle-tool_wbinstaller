<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     tool_wbinstaller
 * @category    string
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apitoken'] = 'GitHub API Token';
$string['apitokendesc'] = 'Insert your GitHub token to receive more detailed information about your plugins.';
$string['classnotfound'] = 'Class for {$a} was not found.';
$string['componentdetectfailed'] = 'Component detection failed.';
$string['confignotfound'] = 'The config setting {$a} was not found.';
$string['configsettingfound'] = 'The config setting {$a} was found.';
$string['configvalueset'] = 'The config setting {$a} was successfully set.';
$string['courseidmismatchlocaldata'] = 'The course ID did not match.';
$string['courseidmismatchlocaldatalink'] = 'The course ID inside the link was not found: {$a}.';
$string['coursescategoryfound'] = 'Found the course category {$a} for the courses to be installed.';
$string['coursescategorynotfound'] = 'Failed to find a suitable course category for the courses to be installed.';
$string['coursesduplicateshortname'] = 'Skipped: Course with short name {$a} already exists.';
$string['coursesfailextract'] = 'Failed to copy extracted files to the Moodle backup directory.';
$string['coursesfailprecheck'] = 'Precheck failed for course restore: {$a}.';
$string['coursesnoshortname'] = 'Could not get the short name of course: {$a}.';
$string['coursessuccess'] = 'Successfully installed the course: {$a->courseshortname} on the category level {$a->category}.';
$string['coursetypenotfound'] = 'STRING coursetypenotfound';
$string['csvinvalid'] = 'The CSV file {$a} could not be uploaded correctly.';
$string['customcategoryduplicate'] = 'Custom category name already exists!';
$string['customfieldduplicate'] = 'Custom field {$a} short name already exists!';
$string['customfielderror'] = 'Failed to upload fieldset: {$a}.';
$string['customfieldfailupload'] = 'Category could not be uploaded!';
$string['customfieldnewfield'] = 'Found the new custom field: {$a}.';
$string['customfieldsuccesss'] = 'Custom field {$a} was installed successfully.';
$string['dbtablenotfound'] = 'The DB table {$a} could not be found. If the plugin containing this table is inside this recipe, the data will be uploaded.';
$string['error'] = 'Installation aborted.';
$string['error_description'] = 'The installation was not successful.';
$string['errorextractingmbz'] = 'Error extracting the course MBZ file: {$a}.';
$string['execdisabled'] = 'The exec function is disabled on this server.';
$string['exporttitle'] = 'Choose courses that you want to export.';
$string['filedownloadfailed'] = 'Failed to download the zip with the URL: {$a}.';
$string['installerdecodebase'] = 'Failed to decode base64 content or the content is empty.';
$string['installerfailextract'] = 'Failed to extract {$a}.';
$string['installerfailextractcode'] = 'CLI command was not executed successfully and returned error code: {$a}.';
$string['installerfailfinddir'] = 'Failed to find extracted directory for {$a}.';
$string['installerfailopen'] = 'Failed to open the ZIP file.';
$string['installerfilenotfound'] = 'The file was not found: {$a}.';
$string['installerfilenotreadable'] = 'The file is not readable: {$a}.';
$string['installersuccessinstalled'] = 'Successfully installed {$a}.';
$string['installervalidbase'] = 'The base64 string is not valid.';
$string['installerwarningextractcode'] = 'CLI command was executed successfully with the following output: {$a}.';
$string['installerwritezip'] = 'Failed to write the ZIP file to the plugin directory.';
$string['installfailed'] = 'Installation failed.';
$string['installfromzip'] = 'Install from zip file';
$string['installplugins'] = 'Install plugins';
$string['invalidmodurl'] = 'The URL could not be translated: {$a}.';
$string['itemparamsfilefound'] = 'Found the item parameter file.';
$string['itemparamsinstallerfilefound'] = 'Found item parameter installer {$a}.';
$string['itemparamsinstallernotfound'] = 'The installer {$a} was not found. If the installer is inside this recipe, the data will be installed normally.';
$string['itemparamsinstallernotfoundexecute'] = 'The installer {$a} was not found and the installation was not executed.';
$string['itemparamsinstallersuccess'] = 'The given installer {$a} was found and used.';
$string['jsonfailalreadyexist'] = 'Error: Directory {$a} already exists.';
$string['jsonfaildecoding'] = 'Error decoding JSON: {$a}.';
$string['jsonfailinsufficientpermission'] = 'Error: Insufficient permission to write {$a}.';
$string['jsoninvalid'] = 'The JSON file {$a} could not be uploaded correctly.';
$string['learningpathalreadyexistis'] = 'Learning path with the same name already exists.';
$string['localdatauploadduplicate'] = 'CSV file {$a} was already uploaded and was not uploaded again.';
$string['localdatauploadmissingcourse'] = 'CSV file {$a} could not be uploaded as referenced courses were not found.';
$string['localdatauploadsuccess'] = 'CSV file {$a} successfully uploaded.';
$string['missingcomponents'] = 'Following component IDs are required for the learning path but are not inside the recipe: {$a}.';
$string['missingcourses'] = 'Following course IDs are required for the learning path but are not inside the recipe: {$a}.';
$string['newcoursefound'] = 'Found the new course: {$a}.';
$string['newlocaldatafilefound'] = 'Found new local data: {$a}.';
$string['noadaptivequizfound'] = 'No matching adaptive quiz was found.';
$string['nomoddatafilefound'] = 'Did not find a course where the learning path is included: {$a}.';
$string['oldermoodlebackupversion'] = 'The course backup is older than the current Moodle version {$a}.';
$string['plugincomponentdetectfailed'] = 'Component is unknown.';
$string['pluginduplicate'] = 'Component {$a->name} is already installed with version {$a->installedversion}. Plugin will be updated to version {$a->componentversion}.';
$string['pluginfailedinformation'] = 'Failed to retrieve component information. Maybe it is due to an unset GitHub token. <a href="{$a}">Click here to set it up</a>.';
$string['plugininstalled'] = 'Component {$a} is installed.';
$string['pluginname'] = 'Wunderbyte Installer';
$string['pluginnotinstalled'] = 'Component {$a} is not installed.';
$string['pluginolder'] = 'Component {$a->name} is already installed with newer version {$a->installedversion}. Your version will not be installed {$a->componentversion}.';
$string['pluginsame'] = 'Component {$a->name} is already installed with the version {$a->installedversion}.';
$string['questionfilefound'] = 'Found the question file.';
$string['questionsuccesinstall'] = 'Successfully uploaded the questions.';
$string['scalemismatchlocaldata'] = 'The category scales did not match.';
$string['success'] = 'Finished successfully.';
$string['success_description'] = 'The installation finished without any errors.';
$string['targetdirnotwritable'] = 'No write permission for the directory {$a}.';
$string['targetdirsubplugin'] = 'The directory {$a} is a subplugin. If the parent plugin is inside this recipe, the subplugin gets installed.';
$string['targetdirwritablecommand'] = 'To change the permission of the directory, execute a command like this: "sudo chmod o+w {$a}"';
$string['translatorerror'] = 'The translation of {$a->changingcolumn} from the table {$a->table} could not be executed.';
$string['translatorsuccess'] = 'The translation of {$a->changingcolumn} from the table {$a->table} was successful.';
$string['uploadbuttontext'] = 'Click here to choose recipe.';
$string['vuecategories'] = 'Category: ';
$string['vuechooserecipe'] = 'Drag and drop Recipe File here or click below to upload the recipe.';
$string['vueconfigheading'] = 'Config settings in the ZIP:';
$string['vuecoursesheading'] = 'Courses in the ZIP:';
$string['vuecustomfieldsheading'] = 'Custom fields in the ZIP:';
$string['vueerror'] = 'Error: ';
$string['vueerrorheading'] = 'Error Details';
$string['vueexport'] = 'Export';
$string['vueexportselect'] = 'Export Selected';
$string['vuefinishedrecipe'] = 'The recipe was installed completely.';
$string['vueinstall'] = 'Install';
$string['vueinstallbtn'] = 'Install Recipe';
$string['vuelearningpathsheading'] = 'Learning paths in the ZIP:';
$string['vuelocaldataheading'] = 'Local data inside the ZIP:';
$string['vuemandatoryplugin'] = 'Mandatory plugins in the ZIP:';
$string['vuemanualupdate'] = 'All plugins have been installed yet. Before you continue finish the manual installation process by following the instruction of the button below.';
$string['vuemanualupdatebtn'] = 'Trigger plugin update';
$string['vuenextstep'] = 'All plugins have been installed. Before you continue, you can adjust the plugins configuration if needed. Click on Next Step or, if you re-enter the page, drag and drop the recipe again to continue the installation process.';
$string['vuenextstepbtn'] = 'Next Step';
$string['vueshowless'] = 'Show less';
$string['vueshowmore'] = 'Show more';
$string['vuesimulationsheading'] = 'Item parameter in the ZIP:';
$string['vuestepcounterof'] = ' of ';
$string['vuestepcountersetp'] = 'Step ';
$string['vuesuccess'] = 'Success: ';
$string['vuewaitingtext'] = 'Please wait while the installation is in progress...';
$string['vuewarining'] = 'Warning: ';
$string['warning'] = 'Finished with errors.';
$string['warning_description'] = 'Some errors were encountered during the installation. More information inside the installation feedback.';
$string['wbinstaller:canexport'] = 'WB Installer: User can export';
$string['wbinstaller:caninstall'] = 'WB Installer: User can install';
$string['wbinstallerroledescription'] = 'Description for wbinstaller role';
