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
 * Video source management.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation;

use context_module;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Normalises source settings and builds browser-safe player configuration.
 */
class source_manager {
    /** @var string[] Supported built-in source types. */
    public const SOURCES = ['upload', 'url', 'youtube', 'vimeo'];

    /**
     * Validates fields owned by the selected source.
     *
     * @param array $data Form data.
     * @return array Validation errors.
     */
    public function validation(array $data): array {
        $source = clean_param((string)($data['videosource'] ?? ''), PARAM_ALPHANUMEXT);
        if (!in_array($source, self::SOURCES, true)) {
            return ['videosource' => get_string('invalidurl', 'videoannotation')];
        }
        if ($source === 'upload') {
            $draftid = (int)($data['videofile'] ?? 0);
            $info = $draftid ? file_get_draft_area_info($draftid) : ['filecount' => 0];
            if (empty($info['filecount'])) {
                return ['videofile' => get_string('required')];
            }
            return [];
        }
        try {
            $this->build_config((object)$data);
        } catch (moodle_exception $exception) {
            $field = $source === 'youtube' ? 'youtubeurl' : ($source === 'vimeo' ? 'vimeourl' : 'videourl');
            return [$field => $exception->getMessage()];
        }
        return [];
    }

    /**
     * Stores a normalised source configuration in the activity record.
     *
     * @param stdClass $data Activity form record.
     * @return void
     */
    public function normalise_record(stdClass $data): void {
        $source = clean_param((string)($data->videosource ?? ''), PARAM_ALPHANUMEXT);
        if (!in_array($source, self::SOURCES, true)) {
            throw new moodle_exception('invalidurl', 'videoannotation');
        }
        $config = $this->build_config($data);
        $data->sourceconfig = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($source === 'url') {
            $data->videourl = $config['url'];
        } else if ($source === 'youtube') {
            $data->videourl = $config['id'];
        } else if ($source === 'vimeo') {
            $data->videourl = $config['id'] . (!empty($config['hash']) ? ':' . $config['hash'] : '');
        } else {
            $data->videourl = '';
        }
    }

    /**
     * Builds normalised configuration from activity form values.
     *
     * @param stdClass $data Activity form record.
     * @return array Source configuration.
     */
    public function build_config(stdClass $data): array {
        $source = clean_param((string)($data->videosource ?? ''), PARAM_ALPHANUMEXT);
        if ($source === 'upload') {
            return ['storage' => 'moodle'];
        }
        if ($source === 'url') {
            $url = trim((string)($data->videourl ?? ''));
            if (!$this->valid_http_url($url)) {
                throw new moodle_exception('invalidurl', 'videoannotation');
            }
            return ['url' => $url];
        }
        if ($source === 'youtube') {
            $id = self::youtube_id(trim((string)($data->youtubeurl ?? '')));
            if ($id === '') {
                throw new moodle_exception('invalidyoutubeurl', 'videoannotation');
            }
            return ['id' => $id];
        }
        if ($source === 'vimeo') {
            $config = self::vimeo_config(trim((string)($data->vimeourl ?? '')));
            if ($config === null) {
                throw new moodle_exception('invalidvimeourl', 'videoannotation');
            }
            return $config;
        }
        throw new moodle_exception('invalidurl', 'videoannotation');
    }

    /**
     * Prepares source fields for the Moodle edit form.
     *
     * @param array $defaultvalues Existing values.
     * @param context_module $context Module context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $source = (string)($defaultvalues['videosource'] ?? 'url');
        $config = $this->decode_config((object)$defaultvalues);
        if ($source === 'upload') {
            $draftid = file_get_submitted_draft_itemid('videofile');
            file_prepare_draft_area($draftid, $context->id, 'mod_videoannotation', 'video', 0, [
                'subdirs' => 0,
                'maxfiles' => 1,
            ]);
            $defaultvalues['videofile'] = $draftid;
        } else if ($source === 'url') {
            $defaultvalues['videourl'] = (string)($config['url'] ?? '');
        } else if ($source === 'youtube') {
            $defaultvalues['youtubeurl'] = !empty($config['id']) ? 'https://www.youtube.com/watch?v=' . $config['id'] : '';
        } else if ($source === 'vimeo') {
            $defaultvalues['vimeourl'] = !empty($config['id'])
                ? 'https://vimeo.com/' . $config['id'] . (!empty($config['hash']) ? '/' . $config['hash'] : '')
                : '';
        }
    }

    /**
     * Saves protected upload files and removes obsolete source files.
     *
     * @param stdClass $data Activity record.
     * @param context_module $context Module context.
     * @param string|null $previoussource Previously selected source.
     * @return void
     */
    public function save_files(stdClass $data, context_module $context, ?string $previoussource = null): void {
        if ($previoussource === 'upload' && $data->videosource !== 'upload') {
            get_file_storage()->delete_area_files($context->id, 'mod_videoannotation', 'video', 0);
        }
        if ($data->videosource === 'upload' && isset($data->videofile)) {
            file_save_draft_area_files((int)$data->videofile, $context->id, 'mod_videoannotation', 'video', 0, [
                'subdirs' => 0,
                'maxfiles' => 1,
                'accepted_types' => ['.mp4', '.webm', '.ogv', '.m4v', '.mov', '.m3u8'],
            ]);
        }
    }

    /**
     * Removes files owned by the activity source.
     *
     * @param context_module $context Module context.
     * @return void
     */
    public function delete_files(context_module $context): void {
        get_file_storage()->delete_area_files($context->id, 'mod_videoannotation', 'video');
    }

    /**
     * Builds configuration consumed by the common browser player adapter.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Module context.
     * @return array Player configuration.
     */
    public function get_player_config(stdClass $activity, context_module $context): array {
        $source = (string)$activity->videosource;
        $config = $this->decode_config($activity);
        $player = [
            'source' => $source,
            'disabledownload' => !empty($activity->disabledownload),
            'disablepip' => !empty($activity->disablepip),
            'disablecontextmenu' => !empty($activity->disablecontextmenu),
            'maxplaybackrate' => (float)$activity->maxplaybackrate,
        ];
        if ($source === 'upload') {
            $url = $this->first_file_url($context);
            if ($url === '') {
                throw new moodle_exception('videofilemissing', 'videoannotation');
            }
            $player['url'] = $url;
            $player['hls'] = (bool)preg_match('/\.m3u8(?:$|\?)/i', $url);
        } else if ($source === 'url') {
            $player['url'] = (string)($config['url'] ?? '');
            $player['hls'] = (bool)preg_match('/\.m3u8(?:$|\?)/i', $player['url']);
        } else if ($source === 'youtube') {
            $player['youtubeid'] = (string)($config['id'] ?? '');
        } else if ($source === 'vimeo') {
            $player['vimeoid'] = (string)($config['id'] ?? '');
            $player['vimeohash'] = (string)($config['hash'] ?? '');
        }
        return $player;
    }

    /**
     * Decodes source configuration with backward-compatible legacy values.
     *
     * @param stdClass $activity Activity record.
     * @return array Source configuration.
     */
    private function decode_config(stdClass $activity): array {
        $raw = trim((string)($activity->sourceconfig ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (($activity->videosource ?? '') === 'url') {
            return ['url' => (string)($activity->videourl ?? '')];
        }
        if (($activity->videosource ?? '') === 'youtube') {
            return ['id' => (string)($activity->videourl ?? '')];
        }
        if (($activity->videosource ?? '') === 'vimeo') {
            $parts = explode(':', (string)($activity->videourl ?? ''), 2);
            return ['id' => $parts[0], 'hash' => $parts[1] ?? ''];
        }
        return ['storage' => 'moodle'];
    }

    /**
     * Returns a protected URL for the uploaded video.
     *
     * @param context_module $context Module context.
     * @return string File URL or empty string.
     */
    private function first_file_url(context_module $context): string {
        $files = get_file_storage()->get_area_files($context->id, 'mod_videoannotation', 'video', 0, 'filename', false);
        if (!$files) {
            return '';
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $context->id,
            'mod_videoannotation',
            'video',
            0,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * Checks for an HTTP or HTTPS URL.
     *
     * @param string $url URL to validate.
     * @return bool
     */
    private function valid_http_url(string $url): bool {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Extracts a YouTube video identifier from common URL forms.
     *
     * @param string $url YouTube URL.
     * @return string Video identifier or empty string.
     */
    public static function youtube_id(string $url): string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $id = '';

        $sources = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = explode('/', $path)[0] ?? '';
        } else if (in_array($host, $sources, true)) {
            if (!empty($query['v'])) {
                $id = (string)$query['v'];
            } else if (preg_match('~^(?:embed|shorts)/([A-Za-z0-9_-]{6,})~', $path, $matches)) {
                $id = $matches[1];
            }
        }
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : '';
    }

    /**
     * Extracts a Vimeo identifier and optional unlisted hash.
     *
     * @param string $url Vimeo URL.
     * @return array|null Vimeo configuration.
     */
    public static function vimeo_config(string $url): ?array {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        if (!preg_match('~^(?:video/)?(\d+)(?:/([A-Za-z0-9]+))?$~', $path, $matches)) {
            return null;
        }
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $hash = $matches[2] ?? '';
        if ($hash === '' && !empty($query['h']) && preg_match('/^[A-Za-z0-9]+$/', (string)$query['h'])) {
            $hash = (string)$query['h'];
        }
        return ['id' => $matches[1], 'hash' => $hash];
    }
}
