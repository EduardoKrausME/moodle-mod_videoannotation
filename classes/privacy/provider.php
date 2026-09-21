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
 * Privacy provider for Video Annotation.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Describes, exports, and deletes learner data stored by Video Annotation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describes the personal data stored by this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videoannotation_notes', [
            'userid' => 'privacy:metadata:notes:userid',
            'starttime' => 'privacy:metadata:notes:starttime',
            'endtime' => 'privacy:metadata:notes:endtime',
            'content' => 'privacy:metadata:notes:content',
            'timecreated' => 'privacy:metadata:notes:timecreated',
        ], 'privacy:metadata:notes');
        $collection->add_database_table('videoannotation_progress', [
            'userid' => 'privacy:metadata:progress:userid',
            'lastposition' => 'privacy:metadata:progress:lastposition',
            'watchedsegments' => 'privacy:metadata:progress:watchedsegments',
            'percent' => 'privacy:metadata:progress:percent',
            'timemodified' => 'privacy:metadata:progress:timemodified',
        ], 'privacy:metadata:progress');
        return $collection;
    }

    /**
     * Returns module contexts containing data for a user.
     *
     * @param int $userid User identifier.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoannotation} va ON va.id = cm.instance
             LEFT JOIN {videoannotation_notes} n ON n.videoannotationid = va.id AND n.userid = :noteuserid
             LEFT JOIN {videoannotation_progress} p ON p.videoannotationid = va.id AND p.userid = :progressuserid
                 WHERE n.id IS NOT NULL OR p.id IS NOT NULL";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videoannotation',
            'noteuserid' => $userid,
            'progressuserid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Exports a user's annotations and viewing progress.
     *
     * @param approved_contextlist $contextlist Approved module contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoannotation', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $notes = $DB->get_records('videoannotation_notes', [
                'videoannotationid' => $cm->instance,
                'userid' => $userid,
            ], 'starttime ASC, id ASC');
            if ($notes) {
                $export = [];
                foreach ($notes as $note) {
                    $export[] = (object)[
                        'starttime' => (float)$note->starttime,
                        'endtime' => $note->endtime === null ? null : (float)$note->endtime,
                        'content' => $note->content,
                        'timecreated' => (int)$note->timecreated,
                    ];
                }
                writer::with_context($context)->export_data(['annotations'], (object)['annotations' => $export]);
            }
            $progress = $DB->get_record('videoannotation_progress', [
                'videoannotationid' => $cm->instance,
                'userid' => $userid,
            ]);
            if ($progress) {
                writer::with_context($context)->export_data(['progress'], (object)[
                    'lastposition' => (float)$progress->lastposition,
                    'uniquewatched' => (float)$progress->uniquewatched,
                    'totalwatchtime' => (float)$progress->totalwatchtime,
                    'percent' => (float)$progress->percent,
                    'watchedsegments' => $progress->watchedsegments,
                    'timemodified' => (int)$progress->timemodified,
                ]);
            }
        }
    }

    /**
     * Deletes all learner data for one module context.
     *
     * @param context $context Context to purge.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videoannotation', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $DB->delete_records('videoannotation_notes', ['videoannotationid' => $cm->instance]);
        $DB->delete_records('videoannotation_progress', ['videoannotationid' => $cm->instance]);
    }

    /**
     * Deletes an approved user's data from approved module contexts.
     *
     * @param approved_contextlist $contextlist Approved module contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoannotation', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('videoannotation_notes', [
                'videoannotationid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->delete_records('videoannotation_progress', [
                'videoannotationid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }
}
