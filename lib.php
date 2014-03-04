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


function local_dataseteditor_add_navigation_nodes($topnode, $urlparams) {

    $categoryid = optional_param('categoryid', 0, PARAM_INT);

    if ($categoryid > 0) {
        $indextype = navigation_node::TYPE_CONTAINER;
    } else {
        $indextype = navigation_node::TYPE_CUSTOM;
    }

    $indexnode = $topnode->add(
        get_string('pluginname', 'local_dataseteditor'),
        new moodle_url(
            LOCAL_DATASETEDITOR_PLUGINPREFIX.'/categories.php',
            $urlparams
        ),
        $indextype
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


function local_dataseteditor_extends_settings_navigation($settings, $denode) {
    global $PAGE;

    /* First ensure that we should show navigation nodes. */

    if ($PAGE->course === null) {
        return;
    }

    $courseid = $PAGE->course->id;

    $coursecontext = context_course::instance($courseid);
    if (! has_capability(
        LOCAL_DATASETEDITOR_VIEW_CAPABILITY,
        $coursecontext)
    ) {
        return;
    }

    $urlparams = array('courseid' => $courseid);


    /* Find the Course administration and Quiz administration blocks. */
    $topnodes = array();
    foreach ($settings->children as $node) {
        if (
            ($node->key == 'courseadmin') ||
            ($node->text == get_string('pluginadministration', 'quiz'))
        ) {
            $topnodes[] = $node;
        }
    }


    foreach ($topnodes as $topnode) {
        /* Find question bank node, if it exists. */
        $questionbank = null;
        foreach ($topnode->children as $node) {
            if ($node->text == get_string('questionbank', 'question')) {
                local_dataseteditor_add_navigation_nodes($node, $urlparams);
                break;
            }
        }
    }
}
