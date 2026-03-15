<?php
namespace tool_wbinstaller\local;

use context_user;
use moodle_exception;
use stored_file;
use ZipArchive;

defined('MOODLE_INTERNAL') || die();

class recipe_file {
    public static function get_draft_file(int $draftitemid): stored_file {
        global $USER;

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'itemid, filepath, filename', false);

        if (empty($files)) {
            throw new moodle_exception('norecipefile', 'tool_wbinstaller');
        }

        return reset($files);
    }

    public static function get_filename(int $draftitemid): string {
        return self::get_draft_file($draftitemid)->get_filename();
    }

    public static function get_base64_contents(int $draftitemid): string {
        $file = self::get_draft_file($draftitemid);
        return base64_encode($file->get_content());
    }

    public static function get_recipe_json(int $draftitemid): array {
        global $CFG;

        $file = self::get_draft_file($draftitemid);
        $tmpdir = make_request_directory() . '/tool_wbinstaller';
        check_dir_exists($tmpdir, true, true);

        $zipfile = $tmpdir . '/' . $file->get_filename();
        file_put_contents($zipfile, $file->get_content());

        $zip = new ZipArchive();
        if ($zip->open($zipfile) !== true) {
            throw new moodle_exception('installerfailopen', 'tool_wbinstaller');
        }

        $json = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || empty($stat['name'])) {
                continue;
            }
            if (basename($stat['name']) === 'recipe.json') {
                $json = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();

        if ($json === null) {
            throw new moodle_exception('norecipefound', 'tool_wbinstaller');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('jsonfaildecoding', 'tool_wbinstaller', '', json_last_error_msg());
        }

        return $decoded;
    }

    public static function get_optional_plugins(int $draftitemid): array {
        $recipe = self::get_recipe_json($draftitemid);
        return array_values($recipe['plugins']['optional'] ?? []);
    }

    public static function build_summary(int $draftitemid): array {
        $recipe = self::get_recipe_json($draftitemid);

        $map = [
            'plugins.required' => 'vuemandatoryplugin',
            'plugins.optional' => 'optionalpluginsheading',
            'config' => 'vueconfigheading',
            'courses' => 'vuecoursesheading',
            'customfields' => 'vuecustomfieldsheading',
            'learningpaths' => 'vuelearningpathsheading',
            'localdata' => 'vuelocaldataheading',
            'itemparams' => 'vuesimulationsheading',
            'questions' => 'questionfilefound',
        ];

        $sections = [];
        foreach ($map as $path => $stringid) {
            $value = self::read_path($recipe, $path);
            if (empty($value)) {
                continue;
            }
            $items = [];
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if (is_scalar($item)) {
                        $items[] = (string)$item;
                    } else if (is_array($item)) {
                        $items[] = is_string($key) ? $key : json_encode($item);
                    }
                }
            } else {
                $items[] = (string)$value;
            }
            $sections[] = [
                'title' => get_string($stringid, 'tool_wbinstaller'),
                'count' => count($items),
                'items' => array_map(static fn($item) => ['text' => $item], $items),
            ];
        }

        return $sections;
    }

    protected static function read_path(array $data, string $path) {
        $value = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
