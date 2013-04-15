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

if (!empty($_POST)) {
    require_sesskey();

    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if ($file['error']) {
            echo get_string('error_upload', 'local_dataset_editor') .
                ':' . $file['error'];

        } else {
            $new_wildcards = array();
            $new_data = array();

            $filename = $file['tmp_name'];
            $fin = fopen($filename, 'r');
            if (!$fin) {
                echo get_string('error_upload', 'local_dataset_editor');
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

                        $new_data[$itemkey] = $data_row;
                        $itemkey++;
                    }
                }

                print_object($new_wildcards);
                print_object($new_data);

                fclose($fin);

                echo $renderer->render_dataset_import_confirm(
                    $new_wildcards, $new_data, $form_dest
                );
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

            $itemcount = required_param('itemcount', PARAM_INT);
            $wildcardcount = required_param('wildcardcount', PARAM_INT);

            print_object(data_submitted());

            echo "$itemcount, $wildcardcount";

            echo html_writer::tag('p',
                get_string('saved_all_data', 'local_dataseteditor'));
        }
    }
}


echo $renderer->render_dataset_upload_form($form_dest);
