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
 * Add page to admin menu.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  2013 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/defines.php');
require_once(dirname(__FILE__) . '/locallib.php');


function local_dataseteditor_extends_navigation($nav) {
    global $PAGE;

    if (
        ($PAGE->context->contextlevel != CONTEXT_MODULE) ||
        ($PAGE->course === null) ||
        ($PAGE->cm === null)
    ) {
           return;
    }

    $courseid = $PAGE->course->id;
    $cmid = $PAGE->cm->id;

    if ($cmid > 0) {
        $modulecontext = context_module::instance($cmid);
        $thiscontext = $modulecontext;

    } else {
        return;
    }

    /* Show dataset editor link only for applicable module types. */
    if (! local_dataseteditor_applicable_module($PAGE->cm->modname)) {
        return;
    }

    $coursecontext = $modulecontext->get_course_context(false);

    if ($coursecontext) {
        $courseid = $coursecontext->instanceid;
    }

    if ($courseid < 1) {
        return;
    }

    if (! has_capability(LOCAL_DATASETEDITOR_VIEW_CAPABILITY, $thiscontext)) {
        return;
    }

    $coursecontext = context_course::instance($courseid);
    if (! has_capability(
        LOCAL_DATASETEDITOR_VIEW_CAPABILITY,
        $coursecontext)
    ) {
        return;
    }

    $urlparams = array('courseid' => $courseid);

    $mainnode = $PAGE->navigation->find($cmid,
        navigation_node::TYPE_ACTIVITY);
    $urlparams['cmid'] = $cmid;

    $categoryid = optional_param('categoryid', 0, PARAM_INT);

    if ($categoryid > 0) {
        $index_type = navigation_node::TYPE_CONTAINER;
    } else {
        $index_type = navigation_node::TYPE_CUSTOM;
    }

    $indexnode = $mainnode->add(
        get_string('pluginname', 'local_dataseteditor'),
        new moodle_url(
            LOCAL_DATASETEDITOR_PLUGINPREFIX.'/categories.php',
            $urlparams
        ),
        $index_type
    );

    if ($categoryid > 0) {
        $urlparams['categoryid'] = $categoryid;

        $indexnode->add(
            get_string('editwildcards', 'local_dataseteditor'),
            new moodle_url(
                LOCAL_DATASETEDITOR_PLUGINPREFIX.'/wildcards.php',
                $urlparams
            )
        );

        $indexnode->add(
            get_string('editdataset', 'local_dataseteditor'),
            new moodle_url(
                LOCAL_DATASETEDITOR_PLUGINPREFIX.'/dataset.php',
                $urlparams
            )
        );

        $indexnode->add(
            get_string('exportdataset', 'local_dataseteditor'),
            new moodle_url(
                LOCAL_DATASETEDITOR_PLUGINPREFIX.'/export_dataset.php',
                $urlparams
            )
        );

        $indexnode->add(
            get_string('importdataset', 'local_dataseteditor'),
            new moodle_url(
                LOCAL_DATASETEDITOR_PLUGINPREFIX.'/import_dataset.php',
                $urlparams
            )
        );
    }
}
