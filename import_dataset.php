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

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');


$categoryid = required_param('categoryid', PARAM_INT);

$syscontext = context_system::instance();
require_login($syscontext);

require_capability('local/dataseteditor:view', $syscontext);

$PAGE->set_url(PLUGINPREFIX.'/import_dataset.php', array('categoryid' => $categoryid));
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
$form_dest = $PAGE->url;


$wildcards = get_wildcards($categoryid, 0); // Don't need any data values

$display_confirmation = false;

if (!empty($_POST)) {
    require_sesskey();

    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if ($file['error']) {
            echo get_string('error_upload', 'local_dataseteditor') .
                ':' . $file['error'];

        } else {
            $new_wildcards = array();
            $new_items = array();

            $filename = $file['tmp_name'];
            $fin = fopen($filename, 'r');
            if (!$fin) {
                echo get_string('error_upload', 'local_dataseteditor');
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

                    if (!$new_wildcards) {
                        foreach ($words as $w) {
                            $w = clean_param($w, PARAM_ALPHANUMEXT);
                            $new_wildcards[] = $w;
                        }

                    } else {
                        $data_row = array();
                        $i = 0;
                        foreach ($words as $w) {
                            $w = clean_param($w, PARAM_RAW);
                            if (strlen($w) > 0) {
                                $data_row[$i] = unformat_float($w);
                            }
                            $i++;
                        }

                        $new_items[$itemkey] = $data_row;
                        $itemkey++;
                    }
                }

                fclose($fin);


                $display_confirmation = true;
            }
        }

    } else {
        /* No uploaded file: must be save or cancel! */

        $submit_overwrite = (
            optional_param('submit_overwrite', 0, PARAM_RAW) ? true : false
        );
        $submit_cancel = (
            optional_param('submit_cancel', 0, PARAM_RAW) ? true : false
        );

        if ($submit_cancel) {
            echo html_writer::tag('p',
                get_string('cancelled', 'local_dataseteditor'));

        } elseif ($submit_overwrite) {

            $success = true;

            $itemcount = required_param('itemcount', PARAM_INT);
            $wildcardcount = required_param('wildcardcount', PARAM_INT);

            $new_wildcards = array();
            for ($wc_num = 0; $wc_num < $wildcardcount; $wc_num++) {
                $field = 'wc_name_w' . $wc_num;
                $name = required_param($field, PARAM_ALPHANUMEXT);
                $new_wildcards[$wc_num] = $name;
            }

            $new_items = array();
            for ($item_num = 0; $item_num < $itemcount; $item_num++) {
                $thisitem = array();

                for ($wc_num = 0; $wc_num < $wildcardcount; $wc_num++) {
                    $field = 'val_i' . $item_num . '_w' . $wc_num;
                    $val = required_param($field, PARAM_RAW);

                    if (strtolower($val) == 'null') {
                        $eo = new stdClass();
                        $eo->name = $new_wildcards[$wc_num];
                        $eo->num = $item_num + 1;

                        echo html_writer::tag('p',
                            get_string('missing_data_X_in_X',
                                'local_dataseteditor', $eo));

                        $success = false;

                    } else {
                        $thisitem[$wc_num] = unformat_float($val);
                    }
                }

                $new_items[$item_num] = $thisitem;
            }

            if ($success) {
                overwrite_wildcard_dataset($categoryid, $new_wildcards,
                    $new_items);

                echo html_writer::tag('p',
                    get_string('saved_all_data', 'local_dataseteditor'));
            } else {
                $display_confirmation = true;
            }
        }
    }
}

if ($display_confirmation) {

    /**
     * Compile list of changes to commit.
     */
    $old_name2num = array();
    foreach ($wildcards as $wc) {
        $old_name2num[$wc->name] = $wc->id;
    }
    $new_name2num = array_flip($new_wildcards);

    $to_delete = array_diff_key($old_name2num, $new_name2num);
    $to_add = array_diff_key($new_name2num, $old_name2num);

    $changelist = array();
    foreach ($to_delete as $name => $num) {
        $changelist[] = get_string('delete_wildcardX',
            'local_dataseteditor', $name);
    }
    foreach ($to_add as $name => $num) {
        $changelist[] = get_string('add_wildcardX',
            'local_dataseteditor', $name);
    }
    $changelist[] = get_string('update_all_data', 'local_dataseteditor');

    echo $renderer->render_dataset_import_confirm(
        $new_wildcards, $new_items, $form_dest, $changelist
    );
}

echo $renderer->render_dataset_upload_form($form_dest);

print_object($new_wildcards);
print_object($new_items);
print_object(data_submitted());
