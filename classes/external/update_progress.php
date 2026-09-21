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
 * External progress update API.
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
use mod_videoannotation\progress_manager;

/**
 * Accepts raw player tracking samples and returns server-calculated progress.
 */
class update_progress extends external_api {
    /**
     * Declares API parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module identifier'),
            'currentposition' => new external_value(PARAM_FLOAT, 'Current position'),
            'duration' => new external_value(PARAM_FLOAT, 'Media duration'),
            'playbackrate' => new external_value(PARAM_FLOAT, 'Playback rate'),
            'segmentstart' => new external_value(PARAM_FLOAT, 'Watched segment start'),
            'segmentend' => new external_value(PARAM_FLOAT, 'Watched segment end'),
            'sequence' => new external_value(PARAM_INT, 'Monotonic session sequence'),
            'sessionkey' => new external_value(PARAM_ALPHANUMEXT, 'Random playback session key'),
        ]);
    }

    /**
     * Stores a tracking update.
     *
     * @param int $cmid Course module identifier.
     * @param float $currentposition Current player position.
     * @param float $duration Media duration.
     * @param float $playbackrate Playback rate.
     * @param float $segmentstart Segment start.
     * @param float $segmentend Segment end.
     * @param int $sequence Sequence number.
     * @param string $sessionkey Session key.
     * @return array
     */
    public static function execute(int $cmid, float $currentposition, float $duration, float $playbackrate,
                                   float $segmentstart, float $segmentend, int $sequence, string $sessionkey): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'currentposition' => $currentposition,
            'duration' => $duration,
            'playbackrate' => $playbackrate,
            'segmentstart' => $segmentstart,
            'segmentend' => $segmentend,
            'sequence' => $sequence,
            'sessionkey' => $sessionkey,
        ]);
        $cm = get_coursemodule_from_id('videoannotation', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videoannotation:view', $context);
        $activity = $DB->get_record('videoannotation', ['id' => $cm->instance], '*', MUST_EXIST);
        $progress = (new progress_manager())->update($activity, $cm, $USER->id, $params);
        return [
            'lastposition' => (float)$progress->lastposition,
            'uniquewatched' => (float)$progress->uniquewatched,
            'percent' => (float)$progress->percent,
            'segments' => (string)$progress->watchedsegments,
            'reason' => (string)($progress->reason ?? ''),
        ];
    }

    /**
     * Declares API return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'lastposition' => new external_value(PARAM_FLOAT, 'Accepted position'),
            'uniquewatched' => new external_value(PARAM_FLOAT, 'Unique watched seconds'),
            'percent' => new external_value(PARAM_FLOAT, 'Unique watched percentage'),
            'segments' => new external_value(PARAM_RAW, 'Merged watched segments JSON'),
            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Optional correction reason'),
        ]);
    }
}
