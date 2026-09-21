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
 * External annotation save API.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_videoannotation\annotation_manager;
use completion_info;

/**
 * Creates or updates one learner annotation.
 */
class save_annotation extends external_api {
    /**
     * Declares API parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module identifier'),
            'annotationid' => new external_value(PARAM_INT, 'Existing annotation identifier, or zero'),
            'promptid' => new external_value(PARAM_INT, 'Prompt identifier, or zero'),
            'categoryid' => new external_value(PARAM_INT, 'Category identifier, or zero'),
            'starttime' => new external_value(PARAM_FLOAT, 'Annotation start in seconds'),
            'endtime' => new external_value(PARAM_FLOAT, 'Annotation end in seconds, or -1 for a point'),
            'content' => new external_value(PARAM_TEXT, 'Annotation text or justification'),
        ]);
    }

    /**
     * Saves one annotation owned by the current user.
     *
     * @param int $cmid Course module identifier.
     * @param int $annotationid Annotation identifier.
     * @param int $promptid Prompt identifier.
     * @param int $categoryid Category identifier.
     * @param float $starttime Start time.
     * @param float $endtime End time or -1.
     * @param string $content Annotation content.
     * @return array
     */
    public static function execute(int $cmid, int $annotationid, int $promptid, int $categoryid,
                                   float $starttime, float $endtime, string $content): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'annotationid' => $annotationid,
            'promptid' => $promptid,
            'categoryid' => $categoryid,
            'starttime' => $starttime,
            'endtime' => $endtime,
            'content' => $content,
        ]);
        $cm = get_coursemodule_from_id('videoannotation', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videoannotation:addannotation', $context);
        $activity = $DB->get_record('videoannotation', ['id' => $cm->instance], '*', MUST_EXIST);

        $text = trim($params['content']);
        if ($text === '') {
            throw new invalid_parameter_exception(get_string('annotationrequired', 'videoannotation'));
        }
        $start = max(0.0, $params['starttime']);
        $end = $params['endtime'] < 0 ? null : max(0.0, $params['endtime']);
        if ($end !== null && $end <= $start + 0.05) {
            throw new invalid_parameter_exception(get_string('annotationinvalidrange', 'videoannotation'));
        }

        $prompt = null;
        if ($params['promptid'] > 0) {
            $prompt = $DB->get_record('videoannotation_prompts', [
                'id' => $params['promptid'],
                'videoannotationid' => $activity->id,
            ], '*', MUST_EXIST);
            if ($prompt->mode === 'point') {
                $end = null;
            } else if ($prompt->mode === 'interval' && $end === null) {
                throw new invalid_parameter_exception(get_string('annotationinvalidrange', 'videoannotation'));
            }
        }

        if ($params['categoryid'] > 0) {
            $DB->get_record('videoannotation_categories', [
                'id' => $params['categoryid'],
                'videoannotationid' => $activity->id,
            ], 'id', MUST_EXIST);
        }

        $now = time();
        if ($params['annotationid'] > 0) {
            $note = $DB->get_record('videoannotation_notes', [
                'id' => $params['annotationid'],
                'videoannotationid' => $activity->id,
            ], '*', MUST_EXIST);
            if ((int)$note->userid !== (int)$USER->id) {
                throw new invalid_parameter_exception(get_string('annotationforbidden', 'videoannotation'));
            }
        } else {
            $note = (object)[
                'videoannotationid' => $activity->id,
                'userid' => $USER->id,
                'timecreated' => $now,
            ];
        }
        $note->promptid = $params['promptid'] > 0 ? $params['promptid'] : null;
        $note->categoryid = $params['categoryid'] > 0 ? $params['categoryid'] : null;
        $note->starttime = $start;
        $note->endtime = $end;
        $note->content = $text;
        $note->timemodified = $now;
        if (empty($note->id)) {
            $note->id = $DB->insert_record('videoannotation_notes', $note);
        } else {
            $DB->update_record('videoannotation_notes', $note);
        }

        $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
        }

        $formatted = (new annotation_manager())->format_notes([$note], $USER->id, $context)[0];
        return [
            'id' => $formatted['id'],
            'timecode' => $formatted['timecode'],
            'content' => $text,
        ];
    }

    /**
     * Declares API return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Annotation identifier'),
            'timecode' => new external_value(PARAM_TEXT, 'Formatted timecode'),
            'content' => new external_value(PARAM_TEXT, 'Annotation content'),
        ]);
    }
}
