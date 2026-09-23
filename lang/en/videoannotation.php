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
 * English language strings.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addannotation'] = 'Add annotation';
$string['addcategory'] = 'Add category';
$string['addprompt'] = 'Add prompt';
$string['aggregate'] = 'Aggregate annotation timeline';
$string['aggregatehelp'] = 'Taller bars indicate portions of the video that received more annotations. Interval annotations contribute to every bucket they overlap.';
$string['alllearners'] = 'All learners';
$string['allowseek'] = 'Allow seeking to unwatched parts';
$string['annotation'] = 'Annotation';
$string['annotationcategory'] = 'Category';
$string['annotationcount'] = '{$a} annotation(s)';
$string['annotationend'] = 'End';
$string['annotationforbidden'] = 'You cannot modify this annotation.';
$string['annotationheader'] = 'Annotations';
$string['annotationinvalidrange'] = 'The annotation interval is invalid.';
$string['annotationnotfound'] = 'The requested annotation was not found.';
$string['annotationprompt'] = 'Prompt';
$string['annotationrequired'] = 'Write an annotation or justification.';
$string['annotations'] = 'Annotations';
$string['annotationstart'] = 'Start';
$string['annotationtext'] = 'Annotation text / justification';
$string['annotationvisibility'] = 'Annotation visibility';
$string['answerprompt'] = 'Answer prompt';
$string['byuser'] = 'by {$a}';
$string['cancelinterval'] = 'Cancel interval';
$string['categories'] = 'Categories';
$string['category'] = 'Category';
$string['categoryname'] = 'Category name';
$string['classannotations'] = 'Class annotations';
$string['completiondetail:percent'] = 'Watch at least {$a}% of the video';
$string['completiondetail:prompts'] = 'Answer all required annotation prompts';
$string['completionheader'] = 'Activity completion';
$string['completionpercent'] = 'Required watched percentage';
$string['completionpercent_help'] = 'The learner must watch at least this percentage of unique video time.';
$string['completionprompts'] = 'Require all prompts marked as required';
$string['completionrules'] = '';
$string['confirmdeleteannotation'] = 'Delete this annotation?';
$string['confirmdeletecategory'] = 'Delete this category? Existing annotations keep their text but lose the category reference.';
$string['confirmdeleteprompt'] = 'Delete this prompt? Existing annotations keep their text but lose the prompt reference.';
$string['defaultcategoryerror'] = 'Error';
$string['defaultcategoryexample'] = 'Example';
$string['defaultcategoryimportant'] = 'Important';
$string['defaultcategoryquestion'] = 'Question';
$string['defaultcategoryreview'] = 'Review';
$string['delete'] = 'Delete';
$string['deleteannotation'] = 'Delete annotation';
$string['disablecontextmenu'] = 'Disable the context menu on HTML5 video';
$string['disabledownload'] = 'Disable the browser download control when supported';
$string['disablepip'] = 'Disable picture-in-picture when supported';
$string['edit'] = 'Edit';
$string['editannotation'] = 'Edit annotation';
$string['errormaxfiles'] = 'Only one file can be uploaded.';
$string['finishinterval'] = 'Finish interval';
$string['intervalannotation'] = 'Interval';
$string['invalidurl'] = 'Enter a valid URL for the selected video source.';
$string['invalidvimeourl'] = 'Enter a valid Vimeo URL containing a video ID.';
$string['invalidyoutubeurl'] = 'Enter a valid YouTube URL containing a video ID.';
$string['learner'] = 'Learner';
$string['manage'] = 'Manage prompts and categories';
$string['managecategories'] = 'Manage categories';
$string['manageprompts'] = 'Manage prompts';
$string['maxplaybackrate'] = 'Maximum playback speed';
$string['modulename'] = 'Video Annotation';
$string['modulenameplural'] = 'Video Annotations';
$string['mostannotated'] = 'Most annotated segments';
$string['noannotations'] = 'No annotations have been added yet.';
$string['nocategory'] = 'No category';
$string['noprompt'] = 'No prompt';
$string['pendingupdates'] = 'Some video progress updates are waiting to be saved.';
$string['playbackheader'] = 'Playback and tracking';
$string['pluginadministration'] = 'Video Annotation administration';
$string['pluginname'] = 'Video Annotation';
$string['pointannotation'] = 'Point';
$string['privacy:metadata:notes'] = 'Video Annotation stores annotations created by learners.';
$string['privacy:metadata:notes:content'] = 'The annotation text or justification.';
$string['privacy:metadata:notes:endtime'] = 'The optional referenced video end time.';
$string['privacy:metadata:notes:starttime'] = 'The referenced video start time.';
$string['privacy:metadata:notes:timecreated'] = 'The time the annotation was created.';
$string['privacy:metadata:notes:userid'] = 'The user who created the annotation.';
$string['privacy:metadata:progress'] = 'Video Annotation stores viewing progress for resume and completion.';
$string['privacy:metadata:progress:lastposition'] = 'The last video position.';
$string['privacy:metadata:progress:percent'] = 'The percentage of unique video time watched.';
$string['privacy:metadata:progress:timemodified'] = 'The last time progress was updated.';
$string['privacy:metadata:progress:userid'] = 'The user whose viewing progress is stored.';
$string['privacy:metadata:progress:watchedsegments'] = 'The video ranges confirmed as watched.';
$string['progress'] = 'Video progress';
$string['prompt'] = 'Prompt';
$string['promptanswered'] = 'Answered';
$string['promptmode'] = 'Selection type';
$string['promptmodeeither'] = 'Moment or interval';
$string['promptmodeinterval'] = 'Interval';
$string['promptmodepoint'] = 'Exact moment';
$string['promptquestion'] = 'Question / instruction';
$string['promptrequired'] = 'Required for completion';
$string['prompts'] = 'Annotation prompts';
$string['promptunanswered'] = 'Not answered';
$string['report'] = 'Annotation report';
$string['resumeask'] = 'Ask before resuming';
$string['resumeautomatic'] = 'Automatically resume from the last position';
$string['resumefromstart'] = 'Always start from the beginning';
$string['resumeno'] = 'Start over';
$string['resumeplayback'] = 'Resume playback';
$string['resumequestion'] = 'Resume from {$a}?';
$string['resumeyes'] = 'Resume';
$string['saveannotation'] = 'Save annotation';
$string['seekblocked'] = 'Watch the skipped section before moving ahead.';
$string['sourceheader'] = 'Video source';
$string['sourceupload'] = 'Upload to Moodle';
$string['sourceurl'] = 'Direct video or HLS URL';
$string['sourcevimeo'] = 'Vimeo';
$string['sourceyoutube'] = 'YouTube';
$string['startinterval'] = 'Start interval';
$string['time'] = 'Time';
$string['trackingerror'] = 'Video progress could not be saved.';
$string['videoannotation:addannotation'] = 'Add and edit own video annotations';
$string['videoannotation:addinstance'] = 'Add a new Video Annotation activity';
$string['videoannotation:deleteanyannotation'] = 'Delete any learner annotation';
$string['videoannotation:managecategories'] = 'Manage annotation categories';
$string['videoannotation:manageprompts'] = 'Manage annotation prompts';
$string['videoannotation:view'] = 'View Video Annotation activities';
$string['videoannotation:viewreport'] = 'View learner annotations and aggregate reports';
$string['videoannotationname'] = 'Activity name';
$string['videofile'] = 'Video file';
$string['videofilemissing'] = 'The uploaded video file is missing.';
$string['videosource'] = 'Video source';
$string['videourl'] = 'Video URL';
$string['viewreport'] = 'View report';
$string['vimeourl'] = 'Vimeo URL';
$string['visibilityclass'] = 'Shared with class: learners can see each other\'s annotations';
$string['visibilityprivate'] = 'Private: only the learner sees their annotations';
$string['visibilityteacher'] = 'Learner and teachers: annotations are visible to the learner and teachers';
$string['watchedofduration'] = '{$a->uniquewatched} watched of {$a->duration}';
$string['watchedpercent'] = '{$a}% watched';
$string['yourannotations'] = 'Your annotations';
$string['youtubeurl'] = 'YouTube URL';
