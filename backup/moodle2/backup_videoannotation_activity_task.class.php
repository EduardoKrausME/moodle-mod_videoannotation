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
 * Backup task for Video Annotation.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoannotation/backup/moodle2/backup_videoannotation_stepslib.php');

/**
 * Defines the backup task for Video Annotation activities.
 */
class backup_videoannotation_activity_task extends backup_activity_task {
    /**
     * Method define_my_settings.
     *
     * @return void Return value.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Method define_my_steps.
     *
     * @return void Return value.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_videoannotation_activity_structure_step(
            'videoannotation_structure',
            'videoannotation.xml'
        ));
    }

    /**
     * Encodes activity links in backed-up text.
     *
     * @param string $content Content to encode.
     * @return string Encoded content.
     */
    public static function encode_content_links($content): string {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        return preg_replace(
            "/({$base}\\/mod\\/videoannotation\\/index.php\\?id=)([0-9]+)/",
            '$@VIDEOANNOTATIONINDEX*$2@$',
            $content
        );
    }
}
