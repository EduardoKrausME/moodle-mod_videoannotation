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
 * Prompt management form.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Edits one teacher annotation prompt.
 */
class prompt_form extends moodleform {
    /**
     * Defines form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'promptid', 0);
        $mform->setType('promptid', PARAM_INT);
        $mform->addElement('hidden', 'formkind', 'prompt');
        $mform->setType('formkind', PARAM_ALPHA);
        $mform->addElement('textarea', 'question', get_string('promptquestion', 'videoannotation'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('question', PARAM_TEXT);
        $mform->addRule('question', null, 'required', null, 'client');
        $mform->addElement('select', 'categoryid', get_string('annotationcategory', 'videoannotation'),
            $this->_customdata['categories']);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addElement('select', 'mode', get_string('promptmode', 'videoannotation'), [
            'point' => get_string('promptmodepoint', 'videoannotation'),
            'interval' => get_string('promptmodeinterval', 'videoannotation'),
            'either' => get_string('promptmodeeither', 'videoannotation'),
        ]);
        $mform->setDefault('mode', 'either');
        $mform->addElement('advcheckbox', 'required', get_string('promptrequired', 'videoannotation'));
        $this->add_action_buttons(true, get_string('addprompt', 'videoannotation'));
    }
}
