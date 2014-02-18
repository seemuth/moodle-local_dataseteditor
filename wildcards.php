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
 * Show and edit the wildcards for a given category.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/defines.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

define('LOCAL_DATASETEDITOR_NUM_EXTRA_ROWS', 3);
define('LOCAL_DATASETEDITOR_DEFAULT_TYPE', 1);
define('LOCAL_DATASETEDITOR_DEFAULT_OPTIONS', 'uniform:1.0:10.0:1');
define('LOCAL_DATASETEDITOR_DEFAULT_ITEMCOUNT', 0);


$categoryid = required_param('categoryid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$category = optional_param('category', '', PARAM_SEQUENCE);

$urlargs = array(
    'categoryid' => $categoryid
);

if ($category) {
    $urlargs['category'] = $category;
}

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

require_capability(LOCAL_DATASETEDITOR_EDIT_CAPABILITY, $thiscontext);
local_dataseteditor_require_capability_cat(
    LOCAL_DATASETEDITOR_EDIT_CAPABILITY,
    $categoryid
);

$PAGE->set_url(LOCAL_DATASETEDITOR_PLUGINPREFIX.'/wildcards.php', $urlargs);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title(
    $SITE->fullname .
    ': ' .
    get_string('pluginname', 'local_dataseteditor') .
    ': ' .
    get_string('editwildcards', 'local_dataseteditor')
);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editwildcards', 'local_dataseteditor'));

$renderer = $PAGE->theme->get_renderer($PAGE, 'local_dataseteditor');

/* Set to true to use wildcards from user-submitted form. */
$wildcards_from_user = false;

if (!empty($_POST)) {
    require_sesskey();

    $attr_types = array(
        'id' => PARAM_INT,
        'name' => PARAM_ALPHANUMEXT,
        'orig' => PARAM_ALPHANUMEXT,
        'del' => PARAM_BOOL,
    );

    $num_rows = required_param('num_wildcard_rows', PARAM_INT);

    $new_wildcards = array();
    $wildcards_from_user = true;

    for ($i = 0; $i < $num_rows; $i++) {
        $suffix = '_' . $i;
        $wc = new stdClass();

        foreach ($attr_types as $n => $t) {
            $varname = 'wc_' . $n . $suffix;
            $val = required_param($varname, $t);
            $wc->$n = $val;
        }

        $new_wildcards[] = $wc;
    }


    /* Defaults for new wildcards.
     */
    $wildcard_defaults = new stdClass();
    $wildcard_defaults->category = $categoryid;
    $wildcard_defaults->type = LOCAL_DATASETEDITOR_DEFAULT_TYPE;
    $wildcard_defaults->options = LOCAL_DATASETEDITOR_DEFAULT_OPTIONS;
    $wildcard_defaults->itemcount = LOCAL_DATASETEDITOR_DEFAULT_ITEMCOUNT;


    if (isset($_POST['submit_cancel'])) {
        $wildcards_from_user = false;

    } else if (isset($_POST['submit_saveandadd'])) {
        $min_rows = $num_rows + LOCAL_DATASETEDITOR_NUM_EXTRA_ROWS;
        local_dataseteditor_save_wildcards(
            $new_wildcards,
            $wildcard_defaults,
            $categoryid
        );
        echo $renderer->notification(
            get_string('saved_wildcards', 'local_dataseteditor'),
            'notifysuccess'
        );
        $wildcards_from_user = false;

    } else if (isset($_POST['submit_save'])) {
        local_dataseteditor_save_wildcards(
            $new_wildcards,
            $wildcard_defaults,
            $categoryid
        );
        echo $renderer->notification(
            get_string('saved_wildcards', 'local_dataseteditor'),
            'notifysuccess'
        );
        $wildcards_from_user = false;

    } else {
        throw new coding_exception('Invalid submit button');
    }
}


$wildcards = local_dataseteditor_get_wildcards($categoryid);
foreach ($wildcards as $k => $wc) {
    if ($wc->num_more_values > 0) {
        $wc->values[count($wc->values)-1] = '...';
    }
}

$form_dest = $PAGE->url;
$uservals = ($wildcards_from_user) ? $new_wildcards : array();
if (! isset($min_rows)) {
    $min_rows = count($wildcards) + LOCAL_DATASETEDITOR_NUM_EXTRA_ROWS;
}
echo $renderer->render_wildcard_form($wildcards, $uservals,
    $min_rows, $form_dest);

echo $OUTPUT->footer();
