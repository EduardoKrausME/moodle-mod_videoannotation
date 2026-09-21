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
 * External annotation delete API.
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
use completion_info;

/**
 * Deletes an annotation when the current user is allowed to do so.
 */
class delete_annotation extends external_api {
    /**
     * Declares API parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module identifier'),
            'annotationid' => new external_value(PARAM_INT, 'Annotation identifier'),
        ]);
    }

    /**
     * Deletes one annotation.
     *
     * @param int $cmid Course module identifier.
     * @param int $annotationid Annotation identifier.
     * @return array
     */
    public static function execute(int $cmid, int $annotationid): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'annotationid' => $annotationid,
        ]);
        $cm = get_coursemodule_from_id('videoannotation', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videoannotation:addannotation', $context);
        $activity = $DB->get_record('videoannotation', ['id' => $cm->instance], '*', MUST_EXIST);
        $note = $DB->get_record('videoannotation_notes', [
            'id' => $params['annotationid'],
            'videoannotationid' => $activity->id,
        ], '*', MUST_EXIST);
        if ((int)$note->userid !== (int)$USER->id && !has_capability('mod/videoannotation:deleteanyannotation', $context)) {
            throw new invalid_parameter_exception(get_string('annotationforbidden', 'videoannotation'));
        }
        $DB->delete_records('videoannotation_notes', ['id' => $note->id]);

        $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, (int)$note->userid);
        }
        return ['deleted' => true];
    }

    /**
     * Declares API return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Whether deletion succeeded'),
        ]);
    }
}
