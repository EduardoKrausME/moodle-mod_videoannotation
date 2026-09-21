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
 * Restore structure for Video Annotation.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores activity configuration, teacher definitions, learner data, mappings, and files.
 */
class restore_videoannotation_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the records included in the restore tree.
     *
     * @return array Restore paths.
     */
    protected function define_structure(): array {
        $paths = [
            new restore_path_element('videoannotation', '/activity/videoannotation'),
            new restore_path_element('videoannotation_category', '/activity/videoannotation/categories/category'),
            new restore_path_element('videoannotation_prompt', '/activity/videoannotation/prompts/prompt'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('videoannotation_note', '/activity/videoannotation/notes/note');
            $paths[] = new restore_path_element('videoannotation_progress', '/activity/videoannotation/progresses/progress');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * process_videoannotation
     *
     * @param array $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videoannotation(array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->id = $DB->insert_record('videoannotation', $data);
        $this->apply_activity_instance($data->id);
        $this->set_mapping('videoannotation', $oldid, $data->id, true);
    }

    /**
     * process_videoannotation_category
     *
     * @param array $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videoannotation_category(array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videoannotationid = $this->get_new_parentid('videoannotation');
        $data->id = $DB->insert_record('videoannotation_categories', $data);
        $this->set_mapping('videoannotation_category', $oldid, $data->id);
    }

    /**
     * process_videoannotation_prompt
     *
     * @param array $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videoannotation_prompt(array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videoannotationid = $this->get_new_parentid('videoannotation');
        $data->categoryid = $data->categoryid ? $this->get_mappingid('videoannotation_category', $data->categoryid, 0) : null;
        $data->id = $DB->insert_record('videoannotation_prompts', $data);
        $this->set_mapping('videoannotation_prompt', $oldid, $data->id);
    }

    /** @param array $data Backup data. @return void */
    protected function process_videoannotation_note(array $data): void {
        global $DB;
        $data = (object)$data;
        $data->videoannotationid = $this->get_new_parentid('videoannotation');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        if (!$data->userid) {
            return;
        }
        $data->promptid = $data->promptid ? $this->get_mappingid('videoannotation_prompt', $data->promptid, 0) : null;
        $data->categoryid = $data->categoryid ? $this->get_mappingid('videoannotation_category', $data->categoryid, 0) : null;
        $DB->insert_record('videoannotation_notes', $data);
    }

    /**
     * process_videoannotation_progress
     *
     * @param array $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videoannotation_progress(array $data): void {
        global $DB;
        $data = (object)$data;
        $data->videoannotationid = $this->get_new_parentid('videoannotation');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        if (!$data->userid) {
            return;
        }
        $DB->insert_record('videoannotation_progress', $data);
    }

    /**
     * Method after_execute.
     *
     * @return void Return value.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_videoannotation', 'video', null);
    }
}
