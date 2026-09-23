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
 * Lists Video Annotation activities in a course.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$PAGE->set_url('/mod/videoannotation/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'videoannotation'));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course('videoannotation', $course);
$table = new html_table();
$table->head = [get_string('modulename', 'videoannotation'), get_string('description')];
foreach ($instances as $instance) {
    if (!$instance->visible) {
        continue;
    }
    $url = new moodle_url('/mod/videoannotation/view.php', ['id' => $instance->coursemodule]);
    $table->data[] = [
        html_writer::link($url, format_string($instance->name)),
        format_text($instance->intro, $instance->introformat),
    ];
}

echo $OUTPUT->header();
echo html_writer::table($table);
echo $OUTPUT->footer();
