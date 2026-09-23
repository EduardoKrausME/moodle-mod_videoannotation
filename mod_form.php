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
 * Activity configuration form.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoannotation\source_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Defines Video Annotation activity settings.
 */
class mod_videoannotation_mod_form extends moodleform_mod {
    /**
     * Defines form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videoannotationname', 'videoannotation'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('html', '<h3>' . get_string('sourceheader', 'videoannotation') . '</h3>');
        $mform->addElement('select', 'videosource', get_string('videosource', 'videoannotation'), [
            'upload' => get_string('sourceupload', 'videoannotation'),
            'url' => get_string('sourceurl', 'videoannotation'),
            'youtube' => get_string('sourceyoutube', 'videoannotation'),
            'vimeo' => get_string('sourcevimeo', 'videoannotation'),
        ]);
        $mform->setDefault('videosource', 'url');

        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videoannotation'), null, [
            'subdirs' => 0,
            'accepted_types' => ['.mp4', '.webm', '.ogv', '.m4v', '.mov', '.m3u8'],
        ]);
        $mform->hideIf('videofile', 'videosource', 'neq', 'upload');

        $mform->addElement('url', 'videourl', get_string('videourl', 'videoannotation'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('videourl', PARAM_URL);
        $mform->hideIf('videourl', 'videosource', 'neq', 'url');

        $mform->addElement('url', 'youtubeurl', get_string('youtubeurl', 'videoannotation'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('youtubeurl', PARAM_URL);
        $mform->hideIf('youtubeurl', 'videosource', 'neq', 'youtube');

        $mform->addElement('url', 'vimeourl', get_string('vimeourl', 'videoannotation'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('vimeourl', PARAM_URL);
        $mform->hideIf('vimeourl', 'videosource', 'neq', 'vimeo');

        $mform->addElement('html', '<h3>' . get_string('playbackheader', 'videoannotation') . '</h3>');
        $mform->addElement('select', 'resumeplayback', get_string('resumeplayback', 'videoannotation'), [
            1 => get_string('resumeautomatic', 'videoannotation'),
            2 => get_string('resumeask', 'videoannotation'),
            0 => get_string('resumefromstart', 'videoannotation'),
        ]);
        $mform->setDefault('resumeplayback', 1);
        $mform->addElement('selectyesno', 'allowseek', get_string('allowseek', 'videoannotation'));
        $mform->setDefault('allowseek', 1);
        $mform->addElement('select', 'maxplaybackrate', get_string('maxplaybackrate', 'videoannotation'), [
            '1.00' => '1×', '1.25' => '1.25×', '1.50' => '1.5×', '1.75' => '1.75×', '2.00' => '2×',
        ]);
        $mform->setDefault('maxplaybackrate', '2.00');
        $mform->addElement('advcheckbox', 'disabledownload', get_string('disabledownload', 'videoannotation'));
        $mform->addElement('advcheckbox', 'disablepip', get_string('disablepip', 'videoannotation'));
        $mform->addElement('advcheckbox', 'disablecontextmenu', get_string('disablecontextmenu', 'videoannotation'));

        $mform->addElement('html', '<h3>' . get_string('annotationheader', 'videoannotation') . '</h3>');
        $mform->addElement('select', 'annotationvisibility', get_string('annotationvisibility', 'videoannotation'), [
            'private' => get_string('visibilityprivate', 'videoannotation'),
            'teacher' => get_string('visibilityteacher', 'videoannotation'),
            'class' => get_string('visibilityclass', 'videoannotation'),
        ]);
        $mform->setDefault('annotationvisibility', 'teacher');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds custom completion controls.
     *
     * @return array Element names participating in completion rules.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $mform->addElement('html', '<h3>' . get_string('completionheader', 'videoannotation') . '</h3>');
        $mform->addElement('select', 'completionpercent', get_string('completionpercent', 'videoannotation'), [
            10 => '10%', 20 => '20%', 30 => '30%', 40 => '40%', 50 => '50%',
            60 => '60%', 70 => '70%', 80 => '80%', 90 => '90%', 100 => '100%',
        ]);
        $mform->setDefault('completionpercent', 80);
        $mform->addHelpButton('completionpercent', 'completionpercent', 'videoannotation');
        $mform->addElement('advcheckbox', 'completionprompts', get_string('completionprompts', 'videoannotation'));
        return ['completionpercent', 'completionprompts'];
    }

    /**
     * Reports whether at least one custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionpercent']) || !empty($data['completionprompts']);
    }

    /**
     * Prepares protected file drafts and source edit fields.
     *
     * @param array $defaultvalues Existing values.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);
        if (!empty($this->current->id) && !empty($this->context)) {
            (new source_manager())->prepare_form_data($defaultvalues, $this->context);
        }
    }

    /**
     * Validates source-specific values.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $errors += (new source_manager())->validation($data);
        foreach (['videofile'] as $field) {
            $draftid = (int)($data[$field] ?? 0);
            if ($draftid > 0) {
                $draftinfo = file_get_draft_area_info($draftid);
                if ((int)$draftinfo['filecount'] > 1) {
                    $errors[$field] = get_string('errormaxfiles', 'videoannotation');
                }
            }
        }
        return $errors;
    }
}
