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
 * Teacher annotation and aggregate report.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoannotation\annotation_manager;
use mod_videoannotation\source_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videoannotation', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoannotation', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videoannotation:viewreport', $context);

$PAGE->set_url('/mod/videoannotation/report.php', ['id' => $cm->id, 'userid' => $userid]);
$PAGE->set_title(get_string('report', 'videoannotation'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$users = get_enrolled_users($context, 'mod/videoannotation:addannotation', 0,
    "u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename",
    "u.lastname,u.firstname");
$useroptions = [['id' => 0, 'name' => get_string('alllearners', 'videoannotation'), 'selected' => $userid === 0]];
foreach ($users as $user) {
    $useroptions[] = [
        'id' => (int)$user->id,
        'name' => fullname($user),
        'selected' => $userid === (int)$user->id,
    ];
}
if ($userid > 0 && !isset($users[$userid])) {
    $userid = 0;
}

$manager = new annotation_manager();
$notes = $manager->report_notes($activity->id, $USER->id, $context, $userid);
$duration = (float)$DB->get_field_sql(
    'SELECT COALESCE(MAX(duration), 0) FROM {videoannotation_progress} WHERE videoannotationid = ?',
    [$activity->id]
);
$aggregate = $manager->aggregate($activity->id, $duration);

$player = (new source_manager())->get_player_config($activity, $context);
$player['ishtml5'] = in_array($player['source'], ['upload', 'url'], true);
$player['isyoutube'] = $player['source'] === 'youtube';
$player['isvimeo'] = $player['source'] === 'vimeo';
$config = ['player' => $player];

$data = [
    'name' => format_string($activity->name),
    'backurl' => (new moodle_url('/mod/videoannotation/view.php', ['id' => $cm->id]))->out(false),
    'formurl' => (new moodle_url('/mod/videoannotation/report.php'))->out(false),
    'cmid' => $cm->id,
    'useroptions' => $useroptions,
    'player' => $player,
    'configjson' => json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    'notes' => $notes,
    'hasnotes' => (bool)$notes,
    'buckets' => $aggregate['buckets'],
    'hasaggregate' => (bool)$aggregate['buckets'],
    'top' => $aggregate['top'],
    'hastop' => (bool)$aggregate['top'],
    'totalannotations' => get_string('annotationcount', 'videoannotation', $aggregate['total']),
];
$PAGE->requires->js_call_amd('mod_videoannotation/report', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoannotation/report', $data);
echo $OUTPUT->footer();
