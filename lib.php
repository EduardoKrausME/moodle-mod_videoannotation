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
 * Core callbacks for the Video Annotation activity.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoannotation\annotation_manager;
use mod_videoannotation\progress_manager;
use mod_videoannotation\source_manager;

/**
 * Declares Moodle features supported by the activity.
 *
 * @param string $feature Feature constant.
 * @return bool|string|null
 */
function videoannotation_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Creates a Video Annotation instance.
 *
 * @param stdClass $data Form data.
 * @param mod_videoannotation_mod_form|null $mform Form instance.
 * @return int Activity identifier.
 */
function videoannotation_add_instance(stdClass $data, ?mod_videoannotation_mod_form $mform = null): int {
    global $DB;
    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    (new source_manager())->normalise_record($data);
    $id = $DB->insert_record('videoannotation', $data);
    $data->id = $id;
    $context = context_module::instance((int)$data->coursemodule);
    (new source_manager())->save_files($data, $context);
    (new annotation_manager())->create_default_categories($id);
    return $id;
}

/**
 * Updates a Video Annotation instance.
 *
 * @param stdClass $data Form data.
 * @param mod_videoannotation_mod_form|null $mform Form instance.
 * @return bool
 */
function videoannotation_update_instance(stdClass $data, ?mod_videoannotation_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $previoussource = (string)$DB->get_field('videoannotation', 'videosource', ['id' => $data->id], MUST_EXIST);
    (new source_manager())->normalise_record($data);
    $result = $DB->update_record('videoannotation', $data);
    $context = context_module::instance((int)$data->coursemodule);
    (new source_manager())->save_files($data, $context, $previoussource);
    return $result;
}

/**
 * Deletes an activity and related records.
 *
 * @param int $id Activity identifier.
 * @return bool
 */
function videoannotation_delete_instance(int $id): bool {
    global $DB;
    $activity = $DB->get_record('videoannotation', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $cm = get_coursemodule_from_instance('videoannotation', $id, $activity->course, false, IGNORE_MISSING);
    if ($cm) {
        (new source_manager())->delete_files(context_module::instance($cm->id));
    }
    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records('videoannotation_notes', ['videoannotationid' => $id]);
    $DB->delete_records('videoannotation_progress', ['videoannotationid' => $id]);
    $DB->delete_records('videoannotation_prompts', ['videoannotationid' => $id]);
    $DB->delete_records('videoannotation_categories', ['videoannotationid' => $id]);
    $DB->delete_records('videoannotation', ['id' => $id]);
    $transaction->allow_commit();
    return true;
}

/**
 * Serves protected uploaded video files.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context $context Module context.
 * @param string $filearea File area.
 * @param array $args Remaining path arguments.
 * @param bool $forcedownload Forced download flag.
 * @param array $options File serving options.
 * @return bool
 */
function mod_videoannotation_pluginfile($course, $cm, $context, string $filearea, array $args,
                                        bool $forcedownload, array $options = []): bool {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'video') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videoannotation:view', $context);
    $itemid = (int)array_shift($args);
    if ($itemid !== 0) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_videoannotation', 'video', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Returns File API areas exposed by the module.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context $context Context.
 * @return array
 */
function videoannotation_get_file_areas($course, $cm, $context): array {
    return ['video' => get_string('videofile', 'videoannotation')];
}

/**
 * Builds cached course-module information.
 *
 * @param stdClass $cm Course module record.
 * @return cached_cm_info|null
 */
function videoannotation_get_coursemodule_info(stdClass $cm): ?cached_cm_info {
    global $DB;
    $activity = $DB->get_record('videoannotation', ['id' => $cm->instance],
        'id,name,intro,introformat,completionpercent,completionprompts');
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($cm->showdescription) {
        $info->content = format_module_intro('videoannotation', $activity, $cm->id, false);
    }
    if ((int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules'] = [
            'completionpercent' => (int)$activity->completionpercent,
            'completionprompts' => !empty($activity->completionprompts),
        ];
    }
    return $info;
}

/**
 * Describes active custom completion rules on course pages.
 *
 * @param cached_cm_info $cm Cached module information.
 * @return array
 */
function videoannotation_get_completion_active_rule_descriptions(cached_cm_info $cm): array {
    if ((int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }
    $rules = $cm->customdata['customcompletionrules'] ?? [];
    $descriptions = [];
    if (!empty($rules['completionpercent'])) {
        $descriptions[] = get_string('completiondetail:percent', 'videoannotation', $rules['completionpercent']);
    }
    if (!empty($rules['completionprompts'])) {
        $descriptions[] = get_string('completiondetail:prompts', 'videoannotation');
    }
    return $descriptions;
}

/**
 * Legacy completion callback using progress and required prompts.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param int $userid User identifier.
 * @param bool $type Expected state.
 * @return bool
 */
function videoannotation_get_completion_state($course, $cm, int $userid, bool $type): bool {
    global $DB;
    $activity = $DB->get_record('videoannotation', ['id' => $cm->instance], '*', MUST_EXIST);
    $progress = $DB->get_record('videoannotation_progress', [
        'videoannotationid' => $activity->id,
        'userid' => $userid,
    ]);
    $complete = (new progress_manager())->percent_complete($activity, $progress);
    if (!empty($activity->completionprompts)) {
        $complete = $complete && (new annotation_manager())->required_prompts_complete($activity->id, $userid);
    }
    return $complete;
}
