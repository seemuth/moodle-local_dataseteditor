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
 * @copyright  Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

define('NUM_EXTRA_ROWS', 5);


$categoryid = required_param('categoryid', PARAM_INT);

$syscontext = context_system::instance();
require_login($syscontext);

require_capability('local/dataseteditor:view', $syscontext);

$PAGE->set_url(PLUGINPREFIX.'/dataset.php', array('categoryid' => $categoryid));
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

/**
 * Set to true to use data from user-submitted form.
 */
$show_user_data = false;

if (!empty($_POST)) {
    require_sesskey();

    $attr_types = array(
        'id' => PARAM_INT,
        'val' => PARAM_RAW,
        'orig' => PARAM_RAW,
    );

    $itemkeys = array_map('intval',
        explode(',', required_param('itemkeys', PARAM_SEQUENCE))
    );
    $wc_keys = array_map('intval',
        explode(',', required_param('wc_keys', PARAM_SEQUENCE))
    );

    $num_rows = count($itemkeys);


    $new_items = array();
    $deleteitems = array();
    $show_user_data = true;

    foreach ($itemkeys as $i) {
        $suffix = '_i' . $i;

        $varname = 'data_del' . $suffix;
        $val = required_param($varname, PARAM_BOOL);
        if ($val) {
            $deleteitems[$i] = 1;
        }

        foreach ($wc_keys as $wc) {
            $suffix = '_i' . $i . '_w' . $wc;
            $item = new stdClass();

            foreach ($attr_types as $n => $t) {
                $varname = 'data_' . $n . $suffix;
                $val = required_param($varname, $t);

                if ($t == PARAM_RAW) {
                    /* Convert to float. */
                    $val = unformat_float($val);
                }

                $item->$n = $val;
            }

            $new_items[$i][$wc] = $item;
        }
    }



    if (isset($_POST['submit_cancel'])) {
        $show_user_data = false;

    } elseif (isset($_POST['submit_saveandadd'])) {
        $min_rows = $num_rows + NUM_EXTRA_ROWS;
        save_dataset_items($new_items, $deleteitems);
        echo $renderer->render_message(
            get_string('saved_dataset_items', 'local_dataseteditor')
        );
        $show_user_data = false;

    } elseif (isset($_POST['submit_save'])) {
        save_dataset_items($new_items, $deleteitems);
        echo $renderer->render_message(
            get_string('saved_dataset_items', 'local_dataseteditor')
        );
        $show_user_data = false;

    } else {
        throw new coding_exception('Invalid submit button');
    }
}


$wildcards = get_wildcards($categoryid, 0); // Don't need any data values
$items = get_dataset_items(array_keys($wildcards));

$form_dest = $PAGE->url;
$uservals = ($show_user_data) ? $new_items : array();
$deleteitems_form = ($show_user_data) ? $deleteitems : array();
if (! isset($min_rows)) {
    $min_rows = count($items) + NUM_EXTRA_ROWS;
}
echo $renderer->render_dataset_form(
    $wildcards, $items,
    $uservals, $deleteitems_form,
    $min_rows, $form_dest
);

if (LOCAL_DATASETEDITOR_DEBUG) {
    print_object($_POST);
}

echo $OUTPUT->footer();
