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

$string['apitoken'] = 'GitHub API-Token';
$string['apitokendesc'] = 'Geben Sie Ihren GitHub-Token ein, um detailliertere Informationen über Ihre Plugins zu erhalten.';
$string['classnotfound'] = 'Klasse für {$a} wurde nicht gefunden.';
$string['componentdetectfailed'] = 'Komponentenerkennung fehlgeschlagen.';
$string['confignotfound'] = 'Die Konfigurationseinstellung {$a} wurde nicht gefunden.';
$string['configsettingfound'] = 'Die Konfigurationseinstellung {$a} wurde gefunden.';
$string['configvalueset'] = 'Die Konfigurationseinstellung {$a} wurde erfolgreich gesetzt.';
$string['courseidmismatchlocaldata'] = 'Die Kurs-ID stimmt nicht überein.';
$string['courseidmismatchlocaldatalink'] = 'Die Kurs-ID im Link wurde nicht gefunden: {$a}.';
$string['coursescategoryfound'] = 'Die Kurskategorie {$a} für die zu installierenden Kurse wurde gefunden.';
$string['coursescategorynotfound'] = 'Es konnte keine passende Kurskategorie für die zu installierenden Kurse gefunden werden.';
$string['coursesduplicateshortname'] = 'Übersprungen: Kurs mit Kurzname {$a} existiert bereits.';
$string['coursesfailextract'] = 'Kopieren der extrahierten Dateien in das Moodle-Backup-Verzeichnis fehlgeschlagen.';
$string['coursesfailprecheck'] = 'Vorprüfung für die Kurswiederherstellung fehlgeschlagen: {$a}.';
$string['coursesnoshortname'] = 'Kurzname des Kurses konnte nicht abgerufen werden: {$a}.';
$string['coursessuccess'] = 'Kurs {$a->courseshortname} erfolgreich auf der Kategorieebene {$a->category} installiert.';
$string['coursetypenotfound'] = 'STRING coursetypenotfound';
$string['csvinvalid'] = 'CSV-Datei {$a} konnte nicht korrekt hochgeladen werden.';
$string['csvinvalidheaders'] = 'Kopfzeilen der CSV-Datei {$a} nicht ausreichend.';
$string['csvmissingfields'] = 'Unvollständige Daten in Zeile {$a->line} in CSV-Datei {$a->file} übersprungen.';
$string['csvnotreadable'] = 'CSV-Datei {$a} kann nicht gelesen werden.';
$string['customcategoryduplicate'] = 'Benutzerdefinierter Kategoriename existiert bereits!';
$string['customfieldduplicate'] = 'Benutzerdefiniertes Feld {$a} Kurzname existiert bereits!';
$string['customfielderror'] = 'Hochladen des Feldsets fehlgeschlagen: {$a}.';
$string['customfieldfailupload'] = 'Kategorie konnte nicht hochgeladen werden!';
$string['customfieldnewfield'] = 'Neues benutzerdefiniertes Feld gefunden: {$a}.';
$string['customfieldsuccesss'] = 'Benutzerdefiniertes Feld {$a} erfolgreich installiert.';
$string['dbtablenotfound'] = 'Die Datenbanktabelle {$a} konnte nicht gefunden werden. Wenn das Plugin, das diese Tabelle enthält, in diesem Rezept enthalten ist, werden die Daten hochgeladen.';
$string['error'] = 'Installation abgebrochen.';
$string['error_description'] = 'Die Installation war nicht erfolgreich.';
$string['errorextractingmbz'] = 'Fehler beim Extrahieren der MBZ-Kursdatei: {$a}.';
$string['execdisabled'] = 'Die exec-Funktion ist auf diesem Server deaktiviert.';
$string['exporttitle'] = 'Wählen Sie die Kurse, die Sie exportieren möchten.';
$string['filedownloadfailed'] = 'Herunterladen der ZIP-Datei fehlgeschlagen mit URL: {$a}.';
$string['installerdecodebase'] = 'Base64-Inhalt konnte nicht dekodiert werden oder der Inhalt ist leer.';
$string['installerfailextract'] = 'Extrahieren von {$a} fehlgeschlagen.';
$string['installerfailextractcode'] = 'CLI-Befehl wurde nicht erfolgreich ausgeführt und gab Fehlercode zurück: {$a}.';
$string['installerfailfinddir'] = 'Extrahiertes Verzeichnis für {$a} konnte nicht gefunden werden.';
$string['installerfailopen'] = 'ZIP-Datei konnte nicht geöffnet werden.';
$string['installerfilenotfound'] = 'Die Datei wurde nicht gefunden: {$a}.';
$string['installerfilenotreadable'] = 'Die Datei ist nicht lesbar: {$a}.';
$string['installersuccessinstalled'] = '{$a} erfolgreich installiert.';
$string['installervalidbase'] = 'Die Base64-Zeichenfolge ist nicht gültig.';
$string['installerwarningextractcode'] = 'CLI-Befehl wurde erfolgreich ausgeführt mit folgendem Output: {$a}.';
$string['installerwritezip'] = 'ZIP-Datei konnte nicht ins Plugin-Verzeichnis geschrieben werden.';
$string['installfailed'] = 'Installation fehlgeschlagen.';
$string['installfromzip'] = 'Von ZIP-Datei installieren';
$string['installplugins'] = 'Plugins installieren';
$string['invalidmodurl'] = 'Die URL konnte nicht übersetzt werden: {$a}.';
$string['itemparamsfilefound'] = 'Itemparameter-Datei gefunden.';
$string['itemparamsinstallerfilefound'] = 'Itemparameter-Installer gefunden: {$a}.';
$string['itemparamsinstallernotfound'] = 'Installer {$a} wurde nicht gefunden. Wenn der Installer in diesem Rezept enthalten ist, werden die Daten normal installiert.';
$string['itemparamsinstallernotfoundexecute'] = 'Installer {$a} wurde nicht gefunden und die Installation wurde nicht ausgeführt.';
$string['itemparamsinstallersuccess'] = 'Der angegebene Installer {$a} wurde gefunden und verwendet.';
$string['jsonfailalreadyexist'] = 'Fehler: Verzeichnis {$a} existiert bereits.';
$string['jsonfaildecoding'] = 'Fehler beim Dekodieren von JSON: {$a}.';
$string['jsonfailinsufficientpermission'] = 'Fehler: Unzureichende Berechtigungen für {$a}.';
$string['jsoninvalid'] = 'Die JSON-Datei {$a} konnte nicht korrekt hochgeladen werden.';
$string['learningpathalreadyexistis'] = 'Lernpfad mit gleichem Namen existiert bereits.';
$string['localdatauploadduplicate'] = 'CSV-Datei {$a} wurde bereits hochgeladen und nicht erneut hochgeladen.';
$string['localdatauploadmissingcourse'] = 'CSV-Datei {$a} konnte nicht hochgeladen werden, da die referenzierten Kurse nicht gefunden wurden.';
$string['localdatauploadsuccess'] = 'CSV-Datei {$a} erfolgreich hochgeladen.';
$string['missingcomponents'] = 'Die folgenden Komponenten-IDs werden für den Lernpfad benötigt, sind aber nicht im Rezept enthalten: {$a}.';
$string['missingcourses'] = 'Die folgenden Kurs-IDs werden für den Lernpfad benötigt, sind aber nicht im Rezept enthalten: {$a}.';
$string['newcoursefound'] = 'Neuer Kurs gefunden: {$a}.';
$string['newlocaldatafilefound'] = 'Neue lokale Daten gefunden: {$a}.';
$string['noadaptivequizfound'] = 'Kein passendes adaptives Quiz gefunden.';
$string['nomoddatafilefound'] = 'Kein Kurs gefunden, in dem der Lernpfad enthalten ist: {$a}.';
$string['oldermoodlebackupversion'] = 'Das Kurs-Backup ist älter als die aktuelle Moodle-Version {$a}.';
$string['plugincomponentdetectfailed'] = 'Komponente ist unbekannt.';
$string['pluginduplicate'] = 'Komponente {$a->name} ist bereits installiert mit Version {$a->installedversion}. Plugin wird auf Version {$a->componentversion} aktualisiert.';
$string['pluginfailedinformation'] = 'Abrufen der Komponenteninformationen fehlgeschlagen. Möglicherweise liegt dies an einem nicht gesetzten GitHub-Token. <a href="{$a}">Klicken Sie hier, um es einzurichten</a>.';
$string['plugininstalled'] = 'Komponente {$a} wurde installiert.';
$string['pluginname'] = 'Wunderbyte Installer';
$string['pluginnotinstalled'] = 'Komponente {$a} wurde nicht installiert.';
$string['pluginolder'] = 'Komponente {$a->name} ist bereits installiert mit einer neueren Version {$a->installedversion}. Ihre Version {$a->componentversion} wird nicht installiert.';
$string['pluginsame'] = 'Komponente {$a->name} ist bereits installiert mit Version {$a->installedversion}.';
$string['questionfilefound'] = 'Fragedatei gefunden.';
$string['questionsuccesinstall'] = 'Fragen erfolgreich hochgeladen.';
$string['restoreerror'] = 'Fehler bei der Ausführung der Kursimporte: {$a}.';
$string['scalemismatchlocaldata'] = 'Die Kategorien-Skalen stimmen nicht überein.';
$string['success'] = 'Erfolgreich abgeschlossen.';
$string['success_description'] = 'Die Installation wurde ohne Fehler abgeschlossen.';
$string['targetdirnotwritable'] = 'Keine Schreibberechtigung für das Verzeichnis {$a}.';
$string['targetdirsubplugin'] = 'Das Verzeichnis {$a} ist ein Subplugin. Wenn das Hauptplugin in diesem Rezept enthalten ist, wird das Subplugin installiert.';
$string['targetdirwritablecommand'] = 'Um die Berechtigung des Verzeichnisses zu ändern, führen Sie einen Befehl wie folgt aus: "sudo chmod o+w {$a}"';
$string['translatorerror'] = 'Die Übersetzung von {$a->changingcolumn} aus der Tabelle {$a->table} konnte nicht ausgeführt werden.';
$string['translatorsuccess'] = 'Die Übersetzung von {$a->changingcolumn} aus der Tabelle {$a->table} war erfolgreich.';
$string['upgradeplugincompleted'] = 'Plugin {$a} wurde erfolgreich installiert und konfiguriert.';
$string['uploadbuttontext'] = 'Klicken Sie hier, um das Rezept auszuwählen.';
$string['vuecategories'] = 'Kategorie: ';
$string['vuechooserecipe'] = 'Ziehen Sie die Rezeptdatei hierher oder klicken Sie unten, um die Datei hochzuladen.';
$string['vueconfigheading'] = 'Konfigurationseinstellungen im ZIP:';
$string['vuecoursesheading'] = 'Kurse im ZIP:';
$string['vuecustomfieldsheading'] = 'Benutzerdefinierte Felder im ZIP:';
$string['vueerror'] = 'Fehler: ';
$string['vueerrorheading'] = 'Fehlerdetails';
$string['vueexport'] = 'Exportieren';
$string['vueexportselect'] = 'Ausgewählte exportieren';
$string['vuefinishedrecipe'] = 'Das Rezept wurde vollständig installiert.';
$string['vueinstall'] = 'Installieren';
$string['vueinstallbtn'] = 'Rezept installieren';
$string['vuelearningpathsheading'] = 'Lernpfade im ZIP:';
$string['vuelocaldataheading'] = 'Lokale Daten im ZIP:';
$string['vuemandatoryplugin'] = 'Erforderliche Plugins im ZIP:';
$string['vuemanualupdate'] = 'Alle Plugins wurden installiert. Bevor Sie fortfahren, schließen Sie den manuellen Installationsprozess gemäß den Anweisungen des untenstehenden Buttons ab.';
$string['vuemanualupdatebtn'] = 'Plugin-Update auslösen';
$string['vuenextstep'] = 'Alle Plugins wurden installiert. Bevor Sie fortfahren, können Sie die Plugin-Konfiguration bei Bedarf anpassen. Klicken Sie auf Weiter oder ziehen Sie das Rezept erneut, um den Installationsprozess fortzusetzen.';
$string['vuenextstepbtn'] = 'Weiter';
$string['vueshowless'] = 'Weniger anzeigen';
$string['vueshowmore'] = 'Mehr anzeigen';
$string['vuesimulationsheading'] = 'Itemparameter im ZIP:';
$string['vuestepcounterof'] = ' von ';
$string['vuestepcountersetp'] = 'Schritt ';
$string['vuesuccess'] = 'Erfolg: ';
$string['vuewaitingtext'] = 'Bitte warten Sie, während die Installation läuft...';
$string['vuewarining'] = 'Warnung: ';
$string['warning'] = 'Mit Fehlern abgeschlossen.';
$string['warning_description'] = 'Während der Installation sind einige Fehler aufgetreten. Weitere Informationen im Installations-Feedback.';
$string['wbinstaller:canexport'] = 'WB Installer: Benutzer/in darf exportieren';
$string['wbinstaller:caninstall'] = 'WB Installer: Benutzer/in darf installieren';
$string['wbinstallerroledescription'] = 'Beschreibung der Rolle wbinstaller';
