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
 * Learner activity view.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoannotation\annotation_manager;
use mod_videoannotation\event\course_module_viewed;
use mod_videoannotation\progress_manager;
use mod_videoannotation\source_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videoannotation', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoannotation', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videoannotation:view', $context);

$PAGE->set_url('/mod/videoannotation/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}
$event = course_module_viewed::create([
    'objectid' => $activity->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videoannotation', $activity);
$event->trigger();

$progress = $DB->get_record('videoannotation_progress', [
    'videoannotationid' => $activity->id,
    'userid' => $USER->id,
]);
if (!$progress) {
    $progress = progress_manager::empty_progress();
}

$sourcemanager = new source_manager();
$player = $sourcemanager->get_player_config($activity, $context);
$player['ishtml5'] = in_array($player['source'], ['upload', 'url'], true);
$player['isyoutube'] = $player['source'] === 'youtube';
$player['isvimeo'] = $player['source'] === 'vimeo';

$annotationmanager = new annotation_manager();
$categories = $annotationmanager->categories($activity->id);
$categoryoptions = [['id' => 0, 'name' => get_string('nocategory', 'videoannotation')]];
foreach ($categories as $category) {
    $categoryoptions[] = ['id' => (int)$category->id, 'name' => format_string($category->name)];
}
$prompts = $annotationmanager->prompts($activity->id, $USER->id);
$notes = $annotationmanager->visible_notes($activity, $USER->id, $context);

$config = [
    'cmid' => $cm->id,
    'lastposition' => (float)$progress->lastposition,
    'resumeplayback' => (int)$activity->resumeplayback,
    'allowseek' => !empty($activity->allowseek),
    'segments' => json_decode((string)$progress->watchedsegments, true) ?: [],
    'player' => $player,
];

$progressmanager = new progress_manager();
$duration = (float)$progress->duration;
$templatedata = [
    'name' => format_string($activity->name),
    'hasintro' => trim((string)$activity->intro) !== '',
    'intro' => format_module_intro('videoannotation', $activity, $cm->id),
    'player' => $player,
    'configjson' => json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    'canannotate' => has_capability('mod/videoannotation:addannotation', $context),
    'categories' => $categoryoptions,
    'prompts' => $prompts,
    'hasprompts' => (bool)$prompts,
    'notes' => $notes,
    'hasnotes' => (bool)$notes,
    'shared' => $activity->annotationvisibility === 'class',
    'notestitle' => $activity->annotationvisibility === 'class'
        ? get_string('classannotations', 'videoannotation')
        : get_string('yourannotations', 'videoannotation'),
    'canviewreport' => has_capability('mod/videoannotation:viewreport', $context),
    'reporturl' => (new moodle_url('/mod/videoannotation/report.php', ['id' => $cm->id]))->out(false),
    'canmanage' => has_capability('mod/videoannotation:manageprompts', $context)
        || has_capability('mod/videoannotation:managecategories', $context),
    'manageurl' => (new moodle_url('/mod/videoannotation/manage.php', ['id' => $cm->id]))->out(false),
    'progress' => [
        'percent' => round((float)$progress->percent, 2),
        'percentrounded' => (int)round((float)$progress->percent),
        'uniquewatched' => progress_manager::format_time((float)$progress->uniquewatched),
        'duration' => progress_manager::format_time($duration),
        'timeline' => $progressmanager->timeline((string)$progress->watchedsegments, $duration),
    ],
];
$templatedata['progress']['watchedofduration'] = get_string('watchedofduration', 'videoannotation', (object)[
    'uniquewatched' => $templatedata['progress']['uniquewatched'],
    'duration' => $templatedata['progress']['duration'],
]);

$PAGE->requires->strings_for_js([
    'resumequestion', 'resumeyes', 'resumeno', 'trackingerror', 'pendingupdates', 'seekblocked',
    'annotationrequired', 'confirmdeleteannotation', 'startinterval', 'finishinterval',
], 'videoannotation');
$PAGE->requires->js_call_amd('mod_videoannotation/main', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoannotation/view', $templatedata);
echo $OUTPUT->footer();
