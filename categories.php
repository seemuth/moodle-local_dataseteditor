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
 * Show editable categories for the current context.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/defines.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

define('LOCAL_DATASETEDITOR_NUM_VALUESETS', 1);


$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$topcategory = optional_param('topcategory', 0, PARAM_INT);

$urlargs = array();


$defaultcat = intval(local_dataseteditor_get_category_preference($courseid));


/* Clean up user preference, in case a stale preference was the *cause*
 * of errors.
 *
 * Need to save original $courseid used for user preference.
 */
$orig_courseid = $courseid;
function local_dataseteditor_error_cleanup() {
    if ($defaultcat > 0) {
        local_dataseteditor_unset_category_preference($orig_courseid);
    }
}


if (($defaultcat > 0) && ($topcategory <= 0)) {
    $topcategory = $defaultcat;
}

$tocontext = null;
if ($topcategory) {
    $cat_contextid = local_dataseteditor_get_cat_contextid($topcategory);

    $tocontext = context::instance_by_id($cat_contextid);

    if ($tocontext->contextlevel == CONTEXT_COURSE) {
        $courseid = $tocontext->instanceid;
        $cmid = 0;

    } else if ($tocontext->contextlevel == CONTEXT_MODULE) {
        $courseid = 0;
        $cmid = $tocontext->instanceid;

    } else {
        local_dataseteditor_error_cleanup();

        print_error(
            'unexpextedcontext',
            'local_dataseteditor',
            '',
            null,
            (
                'tocontext: ' .
                $tocontext->id .
                ',' .
                $tocontext->instanceid .
                '; modulecontext: ' .
                $modulecontext->id .
                ',' .
                $modulecontext->instanceid
            )
        );
    }
}

if ($cmid > 0) {
    $modulecontext = context_module::instance($cmid);

    if ($tocontext !== null) {
        if ($modulecontext !== $tocontext) {
            local_dataseteditor_error_cleanup();

            print_error(
                'unexpectedcontext',
                'local_dataseteditor',
                '',
                null,
                (
                    'tocontext: ' .
                    $tocontext->id .
                    ',' .
                    $tocontext->instanceid .
                    '; modulecontext: ' .
                    $modulecontext->id .
                    ',' .
                    $modulecontext->instanceid
                )
            );
        }
    }

    $urlargs['cmid'] = $cmid;

    $coursecontext = $modulecontext->get_course_context();
    $courseid = $coursecontext->instanceid;

} else {
    $coursecontext = context_course::instance($courseid);

    if ($tocontext !== null) {
        if ($coursecontext !== $tocontext) {
            local_dataseteditor_error_cleanup();

            print_error(
                'unexpectedcontext',
                'local_dataseteditor',
                '',
                null,
                (
                    'tocontext: ' .
                    $tocontext->id .
                    ',' .
                    $tocontext->instanceid .
                    '; coursecontext: ' .
                    $coursecontext->id .
                    ',' .
                    $coursecontext->instanceid
                )
            );
        }
    }
}

if ($cmid > 0) {
    require_login($courseid, true,
        local_dataseteditor_get_cm($courseid, $cmid));
} else {
    require_login($courseid);
}

if ($defaultcat != $topcategory) {
    local_dataseteditor_set_category_preference($courseid, $topcategory);
}

$urlargs['courseid'] = $courseid;

$PAGE->set_url(LOCAL_DATASETEDITOR_PLUGINPREFIX.'/categories.php', $urlargs);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title(
    $SITE->fullname .
    ': ' .
    get_string('pluginname', 'local_dataseteditor') .
    ': ' .
    get_string('viewcategories', 'local_dataseteditor')
);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewcategories', 'local_dataseteditor'));

$renderer = $PAGE->theme->get_renderer($PAGE, 'local_dataseteditor');


$wildcard_url = new moodle_url(
    LOCAL_DATASETEDITOR_PLUGINPREFIX.'/wildcards.php',
    $urlargs
);
$dataset_url = new moodle_url(
    LOCAL_DATASETEDITOR_PLUGINPREFIX.'/dataset.php',
    $urlargs
);
$export_url = new moodle_url(
    LOCAL_DATASETEDITOR_PLUGINPREFIX.'/export_dataset.php',
    $urlargs
);
$import_url = new moodle_url(
    LOCAL_DATASETEDITOR_PLUGINPREFIX.'/import_dataset.php',
    $urlargs
);


/* Retrieve all related contexts for which the user has permissions. */
if (isset($modulecontext)) {
    $thiscontext = $modulecontext;
} else {
    $thiscontext = $coursecontext;
}

$all_contexts = $thiscontext->get_parent_context_ids(true);

$contexts = array();
$contextids = array();
foreach ($all_contexts as $cid) {
    $c = context::instance_by_id($cid);
    if (has_capability(LOCAL_DATASETEDITOR_VIEW_CAPABILITY, $c)) {
        $contexts[$cid] = $c;
        $contextids[] = $cid;
    }
}


$contextid2cats = array();

if (empty($contextids)) {
    $results = array();

} else if ($topcategory > 0) {
    $catids = question_categorylist($topcategory);

    list($where_ids, $params) = $DB->get_in_or_equal($catids);
    $results = $DB->get_records_sql(
        'SELECT cat.* FROM {question_categories} AS cat
        WHERE cat.id ' . $where_ids .
        '
        ORDER BY cat.sortorder, cat.name, cat.id',
        $params
    );

} else {
    list($where_ids, $params) = $DB->get_in_or_equal($contextids);
    $results = $DB->get_records_sql(
        'SELECT cat.* FROM {question_categories} AS cat
        WHERE cat.contextid ' . $where_ids .
        '
        ORDER BY cat.sortorder, cat.name, cat.id',
        $params
    );
}

foreach ($results as $row) {
    $o = new stdClass();
    $o->id = $row->id;
    $o->name = $row->name;

    $o->wildcards = local_dataseteditor_get_wildcards($row->id,
        LOCAL_DATASETEDITOR_NUM_VALUESETS);

    $contextid2cats[$row->contextid][] = $o;
}

$context_cats = array();
foreach ($contexts as $cid => $context) {
    if (! isset($contextid2cats[$cid])) {
        continue;
    }

    $o = new stdClass();
    $o->context = $context;
    $o->categories = $contextid2cats[$cid];

    $context_cats[] = $o;
}

$categorychoiceurl = new moodle_url($PAGE->url);
$categorychoiceurl->remove_params('topcategory');
echo $renderer->render_category_form(
    $categorychoiceurl,
    $contexts,
    $topcategory
);

echo "<br />\n";

echo $renderer->render_category_tables(
    $context_cats, LOCAL_DATASETEDITOR_NUM_VALUESETS,
    $wildcard_url, $dataset_url, $export_url, $import_url
);

echo $OUTPUT->footer();
