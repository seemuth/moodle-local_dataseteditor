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

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

$categoryid = required_param('categoryid', PARAM_INT);

$syscontext = context_system::instance();
require_login($syscontext);

require_capability('local/dataseteditor:view', $syscontext);

$PAGE->set_url(PLUGINPREFIX.'/wildcards.php', array('categoryid' => $categoryid));
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

if (!empty($_POST)) {
    require_sesskey();

    $attr_types = array(
        'id' => PARAM_INT,
        'name' => PARAM_NOTAGS,
        'del' => PARAM_BOOL,
    );

    $num_rows = required_param('num_wildcard_rows', PARAM_INT);

    $new_wildcards = array();

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

    if (isset($_POST['submit_cancel'])) {
        echo '<p>Cancel!</p>';
    } elseif (isset($_POST['submit_saveandadd'])) {
        echo '<p>Save and add!</p>';
    } elseif (isset($_POST['submit_save'])) {
        echo '<p>Save!</p>';
    } else {
        echo '<p>ERROR ERROR ERROR!</p>';
    }

    print_object($new_wildcards);
}


$wildcards = get_wildcards($categoryid);
foreach ($wildcards as $k => $wc) {
    if ($wc->num_more_values > 0) {
        $wc->values[count($wc->values)-1] = '...';
    }
}

$form_dest = $PAGE->url;
echo $renderer->render_wildcard_form($wildcards, count($wildcards)+3, $form_dest);

print_object($_POST);

echo $OUTPUT->footer();
