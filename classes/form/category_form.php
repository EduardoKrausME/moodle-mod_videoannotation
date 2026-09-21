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
 * Category management form.
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
 * Edits one annotation category.
 */
class category_form extends moodleform {
    /**
     * Defines form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'categoryid', 0);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addElement('hidden', 'formkind', 'category');
        $mform->setType('formkind', PARAM_ALPHA);
        $mform->addElement('text', 'name', get_string('categoryname', 'videoannotation'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('addcategory', 'videoannotation'));
    }
}
