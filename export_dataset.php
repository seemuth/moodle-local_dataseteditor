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
 * Export a dataset as tab-delimited spreadsheet
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  2013-2015 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/defines.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');



$categoryid = required_param('categoryid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$urlargs = array(
    'categoryid' => $categoryid
);

if ($cmid > 0) {
    $modulecontext = context_module::instance($cmid);

    $urlargs['cmid'] = $cmid;

    $coursecontext = $modulecontext->get_course_context();
    $courseid = $coursecontext->instanceid;

    $thiscontext = $modulecontext;

} else {
    $coursecontext = context_course::instance($courseid);

    $thiscontext = $coursecontext;
}

$urlargs['courseid'] = $courseid;

if ($cmid > 0) {
    require_login($courseid, true,
        local_dataseteditor_get_cm($courseid, $cmid));
} else {
    require_login($courseid);
}

$mainurl = new moodle_url(
    LOCAL_DATASETEDITOR_PLUGINPREFIX.'/categories.php',
    $urlargs
);

if (! local_dataseteditor_get_cat_contextid($categoryid, false)) {
    /* Invalid category ID. */
    $mainurl->remove_params('categoryid');
    print_error(
        'catnotexist',
        'local_dataseteditor',
        $mainurl
    );
}

require_capability(LOCAL_DATASETEDITOR_EXPORT_CAPABILITY, $thiscontext);
local_dataseteditor_require_capability_cat(
    LOCAL_DATASETEDITOR_EXPORT_CAPABILITY,
    $categoryid
);

$PAGE->set_url(LOCAL_DATASETEDITOR_PLUGINPREFIX.'/export_dataset.php',
    $urlargs);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title(
    $SITE->fullname .
    ': ' .
    get_string('pluginname', 'local_dataseteditor') .
    ': ' .
    get_string('editdataset', 'local_dataseteditor')
);
$PAGE->set_pagelayout('incourse');

$renderer = $PAGE->theme->get_renderer($PAGE, 'local_dataseteditor');


// Don't need any data values.
$wildcards = local_dataseteditor_get_wildcards($categoryid, 0);
$items = local_dataseteditor_get_dataset_items(array_keys($wildcards));

header(
    'Content-Disposition: attachment; filename=dataset-' .
    $categoryid
    . '.tsv'
);
header('Content-Type: application/octet-stream');
header('Content-Description: Dataset Export');

echo $renderer->render_dataset_text($wildcards, $items);
