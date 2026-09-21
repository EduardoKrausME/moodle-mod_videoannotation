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
 * Teacher management page for prompts and categories.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoannotation\annotation_manager;
use mod_videoannotation\form\category_form;
use mod_videoannotation\form\prompt_form;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$deletecategory = optional_param('deletecategory', 0, PARAM_INT);
$deleteprompt = optional_param('deleteprompt', 0, PARAM_INT);
$editcategory = optional_param('editcategory', 0, PARAM_INT);
$editprompt = optional_param('editprompt', 0, PARAM_INT);
$formkind = optional_param('formkind', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('videoannotation', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoannotation', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
$canprompts = has_capability('mod/videoannotation:manageprompts', $context);
$cancategories = has_capability('mod/videoannotation:managecategories', $context);
if (!$canprompts && !$cancategories) {
    require_capability('mod/videoannotation:manageprompts', $context);
}

$PAGE->set_url('/mod/videoannotation/manage.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('manage', 'videoannotation'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($deletecategory && $cancategories) {
    require_sesskey();
    $DB->get_record('videoannotation_categories', [
        'id' => $deletecategory,
        'videoannotationid' => $activity->id,
    ], '*', MUST_EXIST);
    $DB->set_field('videoannotation_notes', 'categoryid', null, ['categoryid' => $deletecategory]);
    $DB->set_field('videoannotation_prompts', 'categoryid', null, ['categoryid' => $deletecategory]);
    $DB->delete_records('videoannotation_categories', ['id' => $deletecategory]);
    redirect($PAGE->url);
}
if ($deleteprompt && $canprompts) {
    require_sesskey();
    $DB->get_record('videoannotation_prompts', [
        'id' => $deleteprompt,
        'videoannotationid' => $activity->id,
    ], '*', MUST_EXIST);
    $DB->set_field('videoannotation_notes', 'promptid', null, ['promptid' => $deleteprompt]);
    $DB->delete_records('videoannotation_prompts', ['id' => $deleteprompt]);
    redirect($PAGE->url);
}

$manager = new annotation_manager();
$categories = $manager->categories($activity->id);
$categoryselect = [0 => get_string('nocategory', 'videoannotation')];
foreach ($categories as $category) {
    $categoryselect[$category->id] = format_string($category->name);
}

$categoryform = null;
if ($cancategories) {
    $categoryform = new category_form($PAGE->url, ['cmid' => $cm->id]);
    if ($editcategory) {
        $record = $DB->get_record('videoannotation_categories', [
            'id' => $editcategory,
            'videoannotationid' => $activity->id,
        ], '*', MUST_EXIST);
        $categoryform->set_data((object)['id' => $cm->id, 'categoryid' => $record->id, 'name' => $record->name]);
    }
    if ($formkind === 'category' && ($data = $categoryform->get_data())) {
        $now = time();
        if (!empty($data->categoryid)) {
            $record = $DB->get_record('videoannotation_categories', [
                'id' => $data->categoryid,
                'videoannotationid' => $activity->id,
            ], '*', MUST_EXIST);
            $record->name = $data->name;
            $record->timemodified = $now;
            $DB->update_record('videoannotation_categories', $record);
        } else {
            $maxsort = (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), 0) FROM {videoannotation_categories} WHERE videoannotationid = ?',
                [$activity->id]
            );
            $DB->insert_record('videoannotation_categories', (object)[
                'videoannotationid' => $activity->id,
                'name' => $data->name,
                'sortorder' => $maxsort + 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        redirect($PAGE->url);
    }
}

$promptform = null;
if ($canprompts) {
    $promptform = new prompt_form($PAGE->url, ['cmid' => $cm->id, 'categories' => $categoryselect]);
    if ($editprompt) {
        $record = $DB->get_record('videoannotation_prompts', [
            'id' => $editprompt,
            'videoannotationid' => $activity->id,
        ], '*', MUST_EXIST);
        $promptform->set_data((object)[
            'id' => $cm->id,
            'promptid' => $record->id,
            'question' => $record->question,
            'categoryid' => $record->categoryid,
            'mode' => $record->mode,
            'required' => $record->required,
        ]);
    }
    if ($formkind === 'prompt' && ($data = $promptform->get_data())) {
        $now = time();
        $mode = in_array($data->mode, ['point', 'interval', 'either'], true) ? $data->mode : 'either';
        if (!empty($data->promptid)) {
            $record = $DB->get_record('videoannotation_prompts', [
                'id' => $data->promptid,
                'videoannotationid' => $activity->id,
            ], '*', MUST_EXIST);
            $record->question = $data->question;
            $record->categoryid = !empty($data->categoryid) ? $data->categoryid : null;
            $record->mode = $mode;
            $record->required = !empty($data->required);
            $record->timemodified = $now;
            $DB->update_record('videoannotation_prompts', $record);
        } else {
            $maxsort = (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), 0) FROM {videoannotation_prompts} WHERE videoannotationid = ?',
                [$activity->id]
            );
            $DB->insert_record('videoannotation_prompts', (object)[
                'videoannotationid' => $activity->id,
                'question' => $data->question,
                'categoryid' => !empty($data->categoryid) ? $data->categoryid : null,
                'mode' => $mode,
                'required' => !empty($data->required),
                'sortorder' => $maxsort + 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        redirect($PAGE->url);
    }
}

$promptrecords = $DB->get_records('videoannotation_prompts', ['videoannotationid' => $activity->id], 'sortorder, id');
$promptitems = [];
foreach ($promptrecords as $prompt) {
    $promptitems[] = [
        'id' => (int)$prompt->id,
        'question' => format_text($prompt->question, FORMAT_PLAIN),
        'category' => !empty($prompt->categoryid) && isset($categories[$prompt->categoryid])
            ? format_string($categories[$prompt->categoryid]->name) : '',
        'mode' => get_string('promptmode' . $prompt->mode, 'videoannotation'),
        'required' => !empty($prompt->required),
        'editurl' => (new moodle_url($PAGE->url, ['editprompt' => $prompt->id]))->out(false),
        'deleteurl' => (new moodle_url($PAGE->url, ['deleteprompt' => $prompt->id, 'sesskey' => sesskey()]))->out(false),
    ];
}
$categoryitems = [];
foreach ($categories as $category) {
    $categoryitems[] = [
        'id' => (int)$category->id,
        'name' => format_string($category->name),
        'editurl' => (new moodle_url($PAGE->url, ['editcategory' => $category->id]))->out(false),
        'deleteurl' => (new moodle_url($PAGE->url, ['deletecategory' => $category->id, 'sesskey' => sesskey()]))->out(false),
    ];
}

$data = [
    'name' => format_string($activity->name),
    'backurl' => (new moodle_url('/mod/videoannotation/view.php', ['id' => $cm->id]))->out(false),
    'canprompts' => $canprompts,
    'cancategories' => $cancategories,
    'prompts' => $promptitems,
    'hasprompts' => (bool)$promptitems,
    'categories' => $categoryitems,
    'hascategories' => (bool)$categoryitems,
    'promptform' => $promptform ? $promptform->render() : '',
    'categoryform' => $categoryform ? $categoryform->render() : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoannotation/manage', $data);
echo $OUTPUT->footer();
