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
 * Custom completion implementation.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation\completion;

use core_completion\activity_custom_completion;
use mod_videoannotation\annotation_manager;
use mod_videoannotation\progress_manager;

/**
 * Evaluates watched percentage and required annotation prompts.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Calculates one custom completion rule state.
     *
     * @param string $rule Rule name.
     * @return int Completion state.
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $activity = $DB->get_record('videoannotation', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if ($rule === 'completionpercent') {
            $progress = $DB->get_record('videoannotation_progress', [
                'videoannotationid' => $activity->id,
                'userid' => $this->userid,
            ]);
            return (new progress_manager())->percent_complete($activity, $progress)
                ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        if ($rule === 'completionprompts') {
            if (empty($activity->completionprompts)) {
                return COMPLETION_COMPLETE;
            }
            return (new annotation_manager())->required_prompts_complete($activity->id, $this->userid)
                ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        return COMPLETION_INCOMPLETE;
    }

    /**
     * Returns custom completion rule identifiers supported by this activity.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionpercent', 'completionprompts'];
    }

    /**
     * Returns human-readable custom rule descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record('videoannotation', ['id' => $this->cm->instance], '*', MUST_EXIST);
        return [
            'completionpercent' => get_string('completiondetail:percent', 'videoannotation', $activity->completionpercent),
            'completionprompts' => get_string('completiondetail:prompts', 'videoannotation'),
        ];
    }

    /**
     * Defines rule display order.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionpercent', 'completionprompts'];
    }
}
