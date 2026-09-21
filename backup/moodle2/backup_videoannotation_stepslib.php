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
 * Backup structure for Video Annotation.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Builds the complete backup tree for activity configuration and optional learner data.
 */
class backup_videoannotation_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines database records and files included in the backup.
     *
     * @return backup_nested_element Root backup element.
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('videoannotation', ['id'], [
            'name', 'intro', 'introformat', 'videosource', 'videourl', 'sourceconfig',
            'resumeplayback', 'allowseek', 'maxplaybackrate', 'disabledownload',
            'disablepip', 'disablecontextmenu', 'annotationvisibility',
            'completionpercent', 'completionprompts', 'timecreated', 'timemodified',
        ]);
        $categories = new backup_nested_element('categories');
        $category = new backup_nested_element('category', ['id'], [
            'name', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $prompts = new backup_nested_element('prompts');
        $prompt = new backup_nested_element('prompt', ['id'], [
            'question', 'categoryid', 'mode', 'required', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $notes = new backup_nested_element('notes');
        $note = new backup_nested_element('note', ['id'], [
            'userid', 'promptid', 'categoryid', 'starttime', 'endtime', 'content', 'timecreated', 'timemodified',
        ]);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'duration', 'lastposition', 'uniquewatched', 'totalwatchtime', 'percent',
            'watchedsegments', 'sequence', 'sessionkey', 'timecreated', 'timemodified',
        ]);

        $activity->add_child($categories);
        $categories->add_child($category);
        $activity->add_child($prompts);
        $prompts->add_child($prompt);
        $activity->add_child($notes);
        $notes->add_child($note);
        $activity->add_child($progresses);
        $progresses->add_child($progress);

        $activity->set_source_table('videoannotation', ['id' => backup::VAR_ACTIVITYID]);
        $category->set_source_table('videoannotation_categories', ['videoannotationid' => backup::VAR_PARENTID]);
        $prompt->set_source_table('videoannotation_prompts', ['videoannotationid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $note->set_source_table('videoannotation_notes', ['videoannotationid' => backup::VAR_PARENTID]);
            $progress->set_source_table('videoannotation_progress', ['videoannotationid' => backup::VAR_PARENTID]);
        }

        $prompt->annotate_ids('videoannotation_category', 'categoryid');
        $note->annotate_ids('user', 'userid');
        $note->annotate_ids('videoannotation_prompt', 'promptid');
        $note->annotate_ids('videoannotation_category', 'categoryid');
        $progress->annotate_ids('user', 'userid');
        $activity->annotate_files('mod_videoannotation', 'video', null);

        return $this->prepare_activity_structure($activity);
    }
}
