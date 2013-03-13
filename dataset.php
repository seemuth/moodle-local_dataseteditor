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

if (!empty($_POST)) {
    require_sesskey();
}


$wildcards = get_wildcards($categoryid, 0); // Don't need any data values

function fake_val($id, $val) {
    $ret = new stdClass();
    $ret->id = $id;
    $ret->val = $val;
    return $ret;
}
$items = array(
    1 => array(
        3 => fake_val(22, 1.33),
        4 => fake_val(23, 2.5),
        6 => fake_val(24, 10.01),
    ),
    2 => array(
        4 => fake_val(35, 20),
    ),
    3 => array(
        6 => fake_val(45, 21),
    ),
    5 => array(
        3 => fake_val(55, 22),
    ),
);

$form_dest = $PAGE->url;
echo $renderer->render_dataset_form(
    $wildcards, $items,
    count($wildcards)+3, $form_dest
);

print_object($_POST);

echo $OUTPUT->footer();
