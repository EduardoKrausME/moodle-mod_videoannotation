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
 * Regression tests for video annotation completion.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoannotation/lib.php');

/**
 * Ensures completion checks work even before a learner watches a video.
 */
final class completion_state_test extends \advanced_testcase {
    /**
     * Missing progress must be incomplete; recorded progress must still be evaluated.
     */
    public function test_completion_without_and_with_progress(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activityid = $DB->insert_record('videoannotation', (object)[
            'course' => $course->id,
            'name' => 'Completion test',
            'videosource' => 'url',
            'completionpercent' => 80,
            'completionprompts' => 0,
        ]);
        $cm = (object)['instance' => $activityid];

        // No progress record exists for this user yet.
        $this->assertFalse(\videoannotation_get_completion_state($course, $cm, $user->id, true));

        $progressid = $DB->insert_record('videoannotation_progress', (object)[
            'videoannotationid' => $activityid,
            'userid' => $user->id,
            'percent' => 75,
        ]);
        $this->assertFalse(\videoannotation_get_completion_state($course, $cm, $user->id, true));

        $DB->set_field('videoannotation_progress', 'percent', 85, ['id' => $progressid]);
        $this->assertTrue(\videoannotation_get_completion_state($course, $cm, $user->id, true));
    }
}
