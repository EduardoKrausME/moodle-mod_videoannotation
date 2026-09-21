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
 * Annotation domain service.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation;

use context_module;
use stdClass;

/**
 * Provides annotation visibility, formatting, prompt completion, and aggregation.
 */
class annotation_manager {
    /**
     * Creates the default category set for a new activity.
     *
     * @param int $activityid Activity identifier.
     * @return void
     */
    public function create_default_categories(int $activityid): void {
        global $DB;
        $keys = [
            'defaultcategoryimportant',
            'defaultcategoryquestion',
            'defaultcategoryexample',
            'defaultcategoryerror',
            'defaultcategoryreview',
        ];
        $now = time();
        foreach ($keys as $sort => $key) {
            $DB->insert_record('videoannotation_categories', (object)[
                'videoannotationid' => $activityid,
                'name' => get_string($key, 'videoannotation'),
                'sortorder' => $sort + 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Returns activity categories ordered for selectors.
     *
     * @param int $activityid Activity identifier.
     * @return array
     */
    public function categories(int $activityid): array {
        global $DB;
        return $DB->get_records('videoannotation_categories', ['videoannotationid' => $activityid], 'sortorder, name');
    }

    /**
     * Returns teacher-defined prompts and learner answered state.
     *
     * @param int $activityid Activity identifier.
     * @param int $userid User identifier.
     * @return array
     */
    public function prompts(int $activityid, int $userid): array {
        global $DB;
        $prompts = $DB->get_records('videoannotation_prompts', ['videoannotationid' => $activityid], 'sortorder, id');
        if (!$prompts) {
            return [];
        }
        $answered = $DB->get_fieldset_select(
            'videoannotation_notes',
            'promptid',
            'videoannotationid = :activityid AND userid = :userid AND promptid IS NOT NULL',
            ['activityid' => $activityid, 'userid' => $userid]
        );
        $answered = array_flip(array_map('intval', $answered));
        $result = [];
        foreach ($prompts as $prompt) {
            $result[] = [
                'id' => (int)$prompt->id,
                'question' => format_text($prompt->question, FORMAT_PLAIN),
                'mode' => $prompt->mode,
                'point' => $prompt->mode === 'point',
                'interval' => $prompt->mode === 'interval',
                'either' => $prompt->mode === 'either',
                'required' => !empty($prompt->required),
                'answered' => isset($answered[(int)$prompt->id]),
                'categoryid' => (int)($prompt->categoryid ?? 0),
            ];
        }
        return $result;
    }

    /**
     * Returns annotations visible to a learner in the activity view.
     *
     * @param stdClass $activity Activity record.
     * @param int $userid Current user identifier.
     * @param context_module $context Module context.
     * @return array
     */
    public function visible_notes(stdClass $activity, int $userid, context_module $context): array {
        global $DB;
        if ($activity->annotationvisibility === 'class') {
            $notes = $DB->get_records('videoannotation_notes', ['videoannotationid' => $activity->id], 'starttime, id');
        } else {
            $notes = $DB->get_records('videoannotation_notes', [
                'videoannotationid' => $activity->id,
                'userid' => $userid,
            ], 'starttime, id');
        }
        return $this->format_notes($notes, $userid, $context);
    }

    /**
     * Returns report annotations, optionally filtered by learner.
     *
     * @param int $activityid Activity identifier.
     * @param int $currentuserid Current viewer identifier.
     * @param context_module $context Module context.
     * @param int $userid Optional learner filter.
     * @return array
     */
    public function report_notes(int $activityid, int $currentuserid, context_module $context, int $userid = 0): array {
        global $DB;
        $conditions = ['videoannotationid' => $activityid];
        if ($userid > 0) {
            $conditions['userid'] = $userid;
        }
        $notes = $DB->get_records('videoannotation_notes', $conditions, 'userid, starttime, id');
        return $this->format_notes($notes, $currentuserid, $context);
    }

    /**
     * Formats annotation records for Mustache and JavaScript.
     *
     * @param array $notes Database notes.
     * @param int $currentuserid Current user identifier.
     * @param context_module $context Module context.
     * @return array
     */
    public function format_notes(array $notes, int $currentuserid, context_module $context): array {
        global $DB;
        if (!$notes) {
            return [];
        }
        $categoryids = array_values(array_unique(array_filter(array_map(
            static fn(stdClass $note): int => (int)($note->categoryid ?? 0),
            $notes
        ))));
        $promptids = array_values(array_unique(array_filter(array_map(
            static fn(stdClass $note): int => (int)($note->promptid ?? 0),
            $notes
        ))));
        $userids = array_values(array_unique(array_map(static fn(stdClass $note): int => (int)$note->userid, $notes)));
        $categories = $categoryids ? $DB->get_records_list('videoannotation_categories', 'id', $categoryids) : [];
        $prompts = $promptids ? $DB->get_records_list('videoannotation_prompts', 'id', $promptids) : [];
        $users = $userids ? $DB->get_records_list('user', 'id', $userids, '', 'id,firstname,lastname') : [];
        $candeleteany = has_capability('mod/videoannotation:deleteanyannotation', $context);
        $result = [];
        foreach ($notes as $note) {
            $start = (float)$note->starttime;
            $hasend = $note->endtime !== null && (float)$note->endtime > $start + 0.05;
            $end = $hasend ? (float)$note->endtime : 0.0;
            $user = $users[$note->userid] ?? null;
            $author = $user ? fullname($user) : '';
            $result[] = [
                'id' => (int)$note->id,
                'userid' => (int)$note->userid,
                'own' => (int)$note->userid === $currentuserid,
                'candelete' => (int)$note->userid === $currentuserid || $candeleteany,
                'canedit' => (int)$note->userid === $currentuserid,
                'starttime' => $start,
                'endtime' => $hasend ? $end : null,
                'timecode' => $hasend
                    ? progress_manager::format_time($start) . '–' . progress_manager::format_time($end)
                    : progress_manager::format_time($start),
                'isinterval' => $hasend,
                'content' => format_text($note->content, FORMAT_PLAIN),
                'contentplain' => s($note->content),
                'contentraw' => $note->content,
                'categoryid' => (int)($note->categoryid ?? 0),
                'category' => isset($categories[$note->categoryid]) ? format_string($categories[$note->categoryid]->name) : '',
                'promptid' => (int)($note->promptid ?? 0),
                'prompt' => isset($prompts[$note->promptid]) ? format_text($prompts[$note->promptid]->question, FORMAT_PLAIN) : '',
                'author' => $author,
                'modified' => userdate($note->timemodified),
            ];
        }
        return $result;
    }

    /**
     * Checks whether all required annotation prompts have an answer.
     *
     * @param int $activityid Activity identifier.
     * @param int $userid User identifier.
     * @return bool
     */
    public function required_prompts_complete(int $activityid, int $userid): bool {
        global $DB;
        $required = $DB->get_fieldset_select(
            'videoannotation_prompts',
            'id',
            'videoannotationid = :activityid AND required = 1',
            ['activityid' => $activityid]
        );
        if (!$required) {
            return true;
        }
        [$insql, $params] = $DB->get_in_or_equal($required, SQL_PARAMS_NAMED, 'prompt');
        $params['activityid'] = $activityid;
        $params['userid'] = $userid;
        $answered = $DB->get_fieldset_select(
            'videoannotation_notes',
            'DISTINCT promptid',
            "videoannotationid = :activityid AND userid = :userid AND promptid {$insql}",
            $params
        );
        return count(array_unique(array_map('intval', $answered))) >= count(array_unique(array_map('intval', $required)));
    }

    /**
     * Builds annotation-density buckets and top segments for the teacher report.
     *
     * @param int $activityid Activity identifier.
     * @param float $duration Best known video duration.
     * @return array
     */
    public function aggregate(int $activityid, float $duration): array {
        global $DB;
        $notes = $DB->get_records('videoannotation_notes', ['videoannotationid' => $activityid], 'starttime, id');
        if (!$notes || $duration <= 0) {
            return ['buckets' => [], 'top' => [], 'total' => count($notes)];
        }
        $bucketcount = min(80, max(20, (int)ceil($duration / 10)));
        $bucketsize = $duration / $bucketcount;
        $counts = array_fill(0, $bucketcount, 0);
        foreach ($notes as $note) {
            $start = max(0.0, min($duration, (float)$note->starttime));
            $end = $note->endtime !== null ? max($start, min($duration, (float)$note->endtime)) : $start;
            $first = min($bucketcount - 1, (int)floor($start / $bucketsize));
            $last = min($bucketcount - 1, (int)floor($end / $bucketsize));
            for ($i = $first; $i <= $last; $i++) {
                $counts[$i]++;
            }
        }
        $max = max($counts) ?: 1;
        $buckets = [];
        foreach ($counts as $index => $count) {
            $start = $index * $bucketsize;
            $end = min($duration, ($index + 1) * $bucketsize);
            $buckets[] = [
                'index' => $index,
                'count' => $count,
                'height' => $count > 0 ? max(5, (int)round(($count / $max) * 100)) : 2,
                'starttime' => round($start, 3),
                'timecode' => progress_manager::format_time($start) . '–' . progress_manager::format_time($end),
            ];
        }
        $top = $buckets;
        usort($top, static function (array $a, array $b): int {
            if ($a['count'] === $b['count']) {
                return $a['index'] <=> $b['index'];
            }
            return $b['count'] <=> $a['count'];
        });
        $top = array_values(array_filter($top, static fn(array $item): bool => $item['count'] > 0));
        $top = array_slice($top, 0, 8);
        return ['buckets' => $buckets, 'top' => $top, 'total' => count($notes)];
    }
}
