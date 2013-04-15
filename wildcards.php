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

define('NUM_EXTRA_ROWS', 3);
define('DEFAULT_TYPE', 1);
define('DEFAULT_OPTIONS', 'uniform:1.0:10.0:1');
define('DEFAULT_ITEMCOUNT', 0);


$categoryid = required_param('categoryid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$urlargs = array(
    'categoryid' => $categoryid
);

if ($cmid > 0) {
    $modulecontext = context_module::instance($cmid);

    $urlargs['cmid'] = $cmid;

    $coursecontext = $context_module->get_course_context();
    $courseid = $coursecontext->instanceid;

    $thiscontext = $modulecontext;

} else {
    $coursecontext = context_course::instance($courseid);

    $thiscontext = $coursecontext;
}

$urlargs['courseid'] = $courseid;

require_login($courseid);
require_capability(EDIT_CAPABILITY, $thiscontext);

$PAGE->set_url(PLUGINPREFIX.'/wildcards.php', $urlargs);
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

/**
 * Set to true to use wildcards from user-submitted form.
 */
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


    /**
     * Defaults for new wildcards.
     */
    $wildcard_defaults = new stdClass();
    $wildcard_defaults->category = $categoryid;
    $wildcard_defaults->type = DEFAULT_TYPE;
    $wildcard_defaults->options = DEFAULT_OPTIONS;
    $wildcard_defaults->itemcount = DEFAULT_ITEMCOUNT;


    if (isset($_POST['submit_cancel'])) {
        $wildcards_from_user = false;

    } elseif (isset($_POST['submit_saveandadd'])) {
        $min_rows = $num_rows + NUM_EXTRA_ROWS;
        save_wildcards($new_wildcards, $wildcard_defaults);
        echo $renderer->render_message(
            get_string('saved_wildcards', 'local_dataseteditor')
        );
        $wildcards_from_user = false;

    } elseif (isset($_POST['submit_save'])) {
        save_wildcards($new_wildcards, $wildcard_defaults);
        echo $renderer->render_message(
            get_string('saved_wildcards', 'local_dataseteditor')
        );
        $wildcards_from_user = false;

    } else {
        throw new coding_exception('Invalid submit button');
    }
}


$wildcards = get_wildcards($categoryid);
foreach ($wildcards as $k => $wc) {
    if ($wc->num_more_values > 0) {
        $wc->values[count($wc->values)-1] = '...';
    }
}

$form_dest = $PAGE->url;
$uservals = ($wildcards_from_user) ? $new_wildcards : array();
if (! isset($min_rows)) {
    $min_rows = count($wildcards) + NUM_EXTRA_ROWS;
}
echo $renderer->render_wildcard_form($wildcards, $uservals,
    $min_rows, $form_dest);

if (LOCAL_DATASETEDITOR_DEBUG) {
    print_object($_POST);
}

echo $OUTPUT->footer();
