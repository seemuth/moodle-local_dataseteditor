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
 * Show and edit the dataset for a given category.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/../../config.php');

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
echo $OUTPUT->heading(get_string('pluginname', 'local_dataseteditor'));

$definitions = $DB->get_records(
    'question_dataset_definitions',
    array('category' => $categoryid),
    'id',
    'id,name,type'
);

$definition_ids = array();

foreach ($definitions as $row) {
    $definition_ids[] = $row->id;
}

$data_items = $DB->get_records_list(
    'question_dataset_items',
    'definition',
    $definition_ids,
    'definition,itemnumber',
    '*'
);

print_object($definitions);


$wildcardform = new dataset_wildcard_form(
    null,
    array(
        'numwildcards' => count($definitions) + 3,
        'categoryid' => $categoryid,
    )
);

if ($wildcardform->is_cancelled()) {
    // Nothing to do for cancel

} else if ($fromform = $wildcardform->get_data()) {
    echo '<p>Received wildcard data!<br />';
    print_object($fromform);
    echo '</p>';

} else {
    // Set default data
    // TODO

    $wildcardform->display();
}


foreach ($data_items as $row) {
    print_object($row);
}

echo $OUTPUT->footer();
