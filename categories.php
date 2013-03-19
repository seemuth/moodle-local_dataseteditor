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


$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$urlargs = array();

if ($cmid > 0) {
    require_login($cmid);
    $modulecontext = context_module::instance($cmid);

    $urlargs['cmid'] = $cmid;

    $coursecontext = $modulecontext->get_course_context();
    $courseid = $coursecontext->instanceid;

} else {
    require_login($courseid);
    $coursecontext = context_course::instance($courseid);
}

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
    PLUGINPREFIX.'wildcards.php',
    $urlargs
);
$dataset_url = new moodle_url(
    PLUGINPREFIX.'dataset.php',
    $urlargs
);

function fake_cat($id, $name, $numquestions, $wildcards, $values) {
    $ret_wildcards = array();
    foreach ($wildcards as $wcid => $wcname) {
        $o = new stdClass();
        $o->id = $wcid;
        $o->name = $wcname;
        $ret_wildcards[] = $o;
    }

    $cat = new stdClass();
    $cat->id = $id;
    $cat->name = $name;
    $cat->numquestions = $numquestions;
    $cat->wildcards = $ret_wildcards;
    $cat->values = $values;

    return $cat;
}

$fake_context2cats = array(
    $coursecontext => array(
        fake_cat(4, 'Cat4', 1,
        array(
            3 => 'A',
            6 => 'ans_AB',
            4 => 'B',
        ),
        array(array(3 => 2, 6 => 2, 4 => 6)))
    ),
);

echo $renderer->render_category_tables(
    $fake_context2cats, $wildcard_url, $dataset_url
);

echo $OUTPUT->footer();
