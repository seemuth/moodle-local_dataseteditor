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
 * Show editable categories for the current context.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

define('NUM_VALUESETS', 1);


$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$urlargs = array();

if ($cmid > 0) {
    $modulecontext = context_module::instance($cmid);

    $urlargs['cmid'] = $cmid;

    $coursecontext = $modulecontext->get_course_context();
    $courseid = $coursecontext->instanceid;

} else {
    $coursecontext = context_course::instance($courseid);
}

require_login($courseid);

$urlargs['courseid'] = $courseid;

$PAGE->set_url(PLUGINPREFIX.'/categories.php', $urlargs);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title(
    $SITE->fullname .
    ': ' .
    get_string('pluginname', 'local_dataseteditor') .
    ': ' .
    get_string('viewcategories', 'local_dataseteditor')
);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewcategories', 'local_dataseteditor'));

$renderer = $PAGE->theme->get_renderer($PAGE, 'local_dataseteditor');


$wildcard_url = new moodle_url(
    PLUGINPREFIX.'/wildcards.php',
    $urlargs
);
$dataset_url = new moodle_url(
    PLUGINPREFIX.'/dataset.php',
    $urlargs
);


/**
 * Retrieve all related contexts for which the user has permissions.
 */
if (isset($modulecontext)) {
    $thiscontext = $modulecontext;
} else {
    $thiscontext = $coursecontext;
}
$qec = new question_edit_contexts($thiscontext);
$contexts = $qec->having_one_edit_tab_cap(EDIT_CAPABILITY);

$context_cats = array();
foreach ($contexts as $context) {
    $cats = array();

    $results = get_categories_for_contexts($context->instanceid);
    foreach ($results as $row) {
        $o = new stdClass();
        $o->id = $row->id;
        $o->name = $row->name;
        $o->numquestions = $row->questioncount;

        $o->wildcards = get_wildcards($row->id, NUM_VALUESETS);

        $cats[] = $o;
    }

    $o = new stdClass();
    $o->context = $context;
    $o->categories = $cats;

    $context_cats[] = $o;
}

echo $renderer->render_category_tables(
    $context_cats, NUM_VALUESETS, $wildcard_url, $dataset_url
);

echo $OUTPUT->footer();
