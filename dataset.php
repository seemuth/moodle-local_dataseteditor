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
 * Show and edit the data values for a given category.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  2013-2015 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/defines.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

define('LOCAL_DATASETEDITOR_NUM_EXTRA_ROWS', 5);

define('LOCAL_DATASETEDITOR_DELETE_GIVEN', 1);
define('LOCAL_DATASETEDITOR_DELETE_ASSUME', 2);



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

require_capability(LOCAL_DATASETEDITOR_EDIT_CAPABILITY, $thiscontext);
local_dataseteditor_require_capability_cat(
    LOCAL_DATASETEDITOR_EDIT_CAPABILITY,
    $categoryid
);

$PAGE->set_url(LOCAL_DATASETEDITOR_PLUGINPREFIX.'/dataset.php', $urlargs);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title(
    $SITE->fullname .
    ': ' .
    get_string('pluginname', 'local_dataseteditor') .
    ': ' .
    get_string('editdataset', 'local_dataseteditor')
);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editdataset', 'local_dataseteditor'));

$renderer = $PAGE->theme->get_renderer($PAGE, 'local_dataseteditor');

/* Set to true to use data from user-submitted form. */
$showuserdata = false;

if (!empty($_POST)) {
    require_sesskey();

    $attrtypes = array(
        'id' => PARAM_INT,
        'val' => PARAM_RAW,
        'orig' => PARAM_RAW,
    );

    $itemkeys = array_map('intval',
        explode(',', required_param('itemkeys', PARAM_SEQUENCE))
    );
    $wckeys = array_map('intval',
        explode(',', required_param('wc_keys', PARAM_SEQUENCE))
    );

    $numrows = count($itemkeys);


    $newitems = array();
    $deleteitems = array();
    $showuserdata = true;
    $success = true;

    foreach ($itemkeys as $i) {
        $suffix = '_i' . $i;

        $varname = 'data_del' . $suffix;
        $deleterow = required_param($varname, PARAM_BOOL);
        if ($deleterow) {
            $deleteitems[$i] = LOCAL_DATASETEDITOR_DELETE_GIVEN;
        }

        $anydata = false;
        $anyempty = false;

        foreach ($wckeys as $wc) {
            $suffix = '_i' . $i . '_w' . $wc;
            $item = new stdClass();

            foreach ($attrtypes as $n => $t) {
                $varname = 'data_' . $n . $suffix;
                $val = required_param($varname, $t);

                if ($t == PARAM_RAW) {
                    /* Convert to float. */
                    $val = unformat_float($val);
                }

                $item->$n = $val;
            }

            if (trim($item->val) === '') {
                $anyempty = true;
            } else {
                $anydata = true;
            }

            $newitems[$i][$wc] = $item;
        }

        if (
            (! isset($_POST['submit_cancel'])) &&
            $anydata &&
            $anyempty &&
            (! $deleterow)
        ) {
            echo $renderer->notification(
                get_string('missing_data_in_X', 'local_dataseteditor', $i),
                'notifyproblem'
            );

            $success = false;
        }

        if (! $anydata) {
            /* Assume that we should delete this item. */
            $deleteitems[$i] = LOCAL_DATASETEDITOR_DELETE_ASSUME;
        }
    }



    if (isset($_POST['submit_cancel'])) {
        $showuserdata = false;

    } else if (isset($_POST['submit_saveandadd'])) {
        $minrows = $numrows + LOCAL_DATASETEDITOR_NUM_EXTRA_ROWS;

        if ($success) {
            local_dataseteditor_save_dataset_items(
                $newitems,
                $deleteitems,
                $categoryid
            );
            echo $renderer->notification(
                get_string('saved_dataset_items', 'local_dataseteditor'),
                'notifysuccess'
            );
            $showuserdata = false;
        }

    } else if (isset($_POST['submit_save'])) {

        if ($success) {
            local_dataseteditor_save_dataset_items(
                $newitems,
                $deleteitems,
                $categoryid
            );
            echo $renderer->notification(
                get_string('saved_dataset_items', 'local_dataseteditor'),
                'notifysuccess'
            );
            $showuserdata = false;
        }

    } else {
        throw new coding_exception('Invalid submit button');
    }
}


// Don't need any data values.
$wildcards = local_dataseteditor_get_wildcards($categoryid, 0);
$items = local_dataseteditor_get_dataset_items(array_keys($wildcards));

$formdest = $PAGE->url;
$uservals = ($showuserdata) ? $newitems : array();
$deleteitemsform = ($showuserdata) ? $deleteitems : array();

/* Do not check delete boxes for rows to be automatically deleted. */
foreach ($deleteitemsform as $k => $v) {
    if ($v == LOCAL_DATASETEDITOR_DELETE_ASSUME) {
        unset($deleteitemsform[$k]);
    }
}

if (! isset($minrows)) {
    $minrows = count($items) + LOCAL_DATASETEDITOR_NUM_EXTRA_ROWS;
}
echo $renderer->render_dataset_form(
    $wildcards, $items,
    $uservals, $deleteitemsform,
    $minrows, $formdest
);

echo $OUTPUT->footer();
