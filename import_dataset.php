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
 * Import a dataset from a tab-delimited spreadsheet
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  Daniel Seemuth
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

require_capability(LOCAL_DATASETEDITOR_IMPORT_CAPABILITY, $thiscontext);
local_dataseteditor_require_capability_cat(
    LOCAL_DATASETEDITOR_IMPORT_CAPABILITY,
    $categoryid
);

$PAGE->set_url(LOCAL_DATASETEDITOR_PLUGINPREFIX.'/import_dataset.php',
    $urlargs);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title(
    $SITE->fullname .
    ': ' .
    get_string('pluginname', 'local_dataseteditor') .
    ': ' .
    get_string('importdataset', 'local_dataseteditor')
);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importdataset', 'local_dataseteditor'));

$renderer = $PAGE->theme->get_renderer($PAGE, 'local_dataseteditor');
$formdest = $PAGE->url;


// Don't need any data values.
$wildcards = local_dataseteditor_get_wildcards($categoryid, 0);

$displayconfirmation = false;
$havealldata = false;
$newwildcards = null;
$newitems = null;

if (!empty($_POST)) {
    require_sesskey();

    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if ($fileerror = $file['error']) {

            if ($fileerror === UPLOAD_ERR_NO_FILE) {
                $fileerror = get_string('no_file', 'local_dataseteditor');
            }

            echo $renderer->notification(
                get_string('error_upload_X', 'local_dataseteditor',
                    $fileerror),
                'notifyproblem'
            );

        } else {
            $newwildcards = array();
            $newitems = array();

            $filename = $file['tmp_name'];
            $fin = fopen($filename, 'r');
            if (!$fin) {
                echo $renderer->notification(
                    get_string('error_upload', 'local_dataseteditor'),
                    'notifyproblem'
                );

            } else {
                $linenum = 0;
                $itemkey = 0;
                while (!feof($fin)) {
                    $line = rtrim(fgets($fin));
                    $linenum++;

                    /* Skip blank lines */
                    if (!$line) {
                        continue;
                    }

                    $words = explode("\t", $line);

                    if (!$newwildcards) {
                        foreach ($words as $w) {
                            $w = clean_param($w, PARAM_ALPHANUMEXT);
                            $newwildcards[] = $w;
                        }

                    } else {
                        $datarow = array();
                        $i = 0;
                        foreach ($words as $w) {
                            $w = clean_param($w, PARAM_RAW);
                            if (strlen($w) > 0) {
                                $datarow[$i] = unformat_float($w);
                            }
                            $i++;
                        }

                        $newitems[$itemkey] = $datarow;
                        $itemkey++;
                    }
                }

                fclose($fin);


                $displayconfirmation = true;
            }
        }

        /* Ensure all data is defined. */
        if ($newitems) {
            $havealldata = true;
            foreach ($newitems as $itemkey => $item) {
                foreach ($newwildcards as $wcid => $wcname) {
                    if (! isset($item[$wcid])) {
                        $havealldata = false;

                        $eo = new stdClass();
                        $eo->name = $wcname;
                        $eo->num = $itemkey + 1;

                        echo $renderer->notification(
                            get_string(
                                'missing_data_X_in_X',
                                'local_dataseteditor',
                                $eo),
                            'notifyproblem'
                        );
                    }
                }
            }

            if (! $havealldata) {
                echo $renderer->notification(
                    get_string(
                        'cannot_save_dataset_asis',
                        'local_dataseteditor'
                    ),
                    'notifyproblem'
                );
            }
        }

    } else {
        /* No uploaded file: must be save or cancel! */

        $submitoverwrite = (
            optional_param('submit_overwrite', 0, PARAM_RAW) ? true : false
        );
        $submitcancel = (
            optional_param('submit_cancel', 0, PARAM_RAW) ? true : false
        );

        if ($submitcancel) {
            echo $renderer->notification(
                get_string('cancelled', 'local_dataseteditor'),
                'notifyproblem'
            );

        } else if ($submitoverwrite) {

            $success = true;

            $itemcount = required_param('itemcount', PARAM_INT);
            $wildcardcount = required_param('wildcardcount', PARAM_INT);

            $newwildcards = array();
            for ($wcnum = 0; $wcnum < $wildcardcount; $wcnum++) {
                $field = 'wc_name_w' . $wcnum;
                $name = required_param($field, PARAM_ALPHANUMEXT);
                $newwildcards[$wcnum] = $name;
            }

            $newitems = array();
            for ($itemnum = 0; $itemnum < $itemcount; $itemnum++) {
                $thisitem = array();

                for ($wcnum = 0; $wcnum < $wildcardcount; $wcnum++) {
                    $field = 'val_i' . $itemnum . '_w' . $wcnum;
                    $val = required_param($field, PARAM_RAW);

                    if (strtolower($val) == 'null') {
                        $eo = new stdClass();
                        $eo->name = $newwildcards[$wcnum];
                        $eo->num = $itemnum + 1;

                        echo $renderer->notification(
                            get_string(
                                'missing_data_X_in_X',
                                'local_dataseteditor', $eo),
                            'notifyproblem'
                        );

                        $success = false;

                    } else {
                        $thisitem[$wcnum] = unformat_float($val);
                    }
                }

                $newitems[$itemnum] = $thisitem;
            }

            if ($success) {
                local_dataseteditor_overwrite_wildcard_dataset(
                    $categoryid,
                    $newwildcards,
                    $newitems
                );

                echo $renderer->notification(
                    get_string('saved_all_data', 'local_dataseteditor'),
                    'notifysuccess'
                );

            } else {
                $displayconfirmation = true;
            }
        }
    }
}

if ($displayconfirmation) {

    /* Compile list of changes to commit. */
    $oldname2num = array();
    foreach ($wildcards as $wc) {
        $oldname2num[$wc->name] = $wc->id;
    }
    $newname2num = array_flip($newwildcards);

    $todelete = array_diff_key($oldname2num, $newname2num);
    $toadd = array_diff_key($newname2num, $oldname2num);

    $changelist = array();
    foreach ($todelete as $name => $num) {
        $changelist[] = get_string('delete_wildcardX',
            'local_dataseteditor', $name);
    }
    foreach ($toadd as $name => $num) {
        $changelist[] = get_string('add_wildcardX',
            'local_dataseteditor', $name);
    }
    $changelist[] = get_string('update_all_data', 'local_dataseteditor');

    echo $renderer->render_dataset_import_confirm(
        $newwildcards, $newitems, $formdest, $changelist
    );

}

if (! $havealldata) {
    echo $renderer->render_dataset_upload_form($formdest);
}

echo $OUTPUT->footer();
