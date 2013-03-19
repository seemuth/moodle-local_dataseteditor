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

require_once(dirname(__FILE__) . '/config.php');

//moodleform is defined in formslib.php
require_once("$CFG->libdir/formslib.php");


function local_dataseteditor_extends_settings_navigation($settings, $context) {
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $cmid = optional_param('cmid', 0, PARAM_INT);

    if ($cmid > 0) {
        $modulecontext = context_module::instance($cmid);

    } elseif ($courseid > 0) {
        $coursecontext = context_course::instance($courseid);

    } elseif ($context->contextlevel == CONTEXT_COURSE) {
        $courseid = $context->instanceid;
        $coursecontext = $context;

    } elseif($context->contextlevel == CONTEXT_MODULE) {
        $cmid = $context->instanceid;
        $modulecontext = $context;

    } else {
        return;
    }

    if ($cmid > 0) {
        $coursecontext = $modulecontext->get_course_context(false);

        if ($coursecontext) {
            $courseid = $coursecontext->instanceid;
        }
    }

    $settingnode = $settings->add(
        get_string('setting', 'local_dataseteditor')
    );
    $indexnode = $settingnode->add(
        get_string('index', 'local_dataseteditor'),
        new moodle_url(PLUGINPREFIX.'/index.php')
    );

    if ($courseid) {
        $coursecontext = context_course::instance($courseid);
        if (!has_capability('local/dataseteditor:view', $coursecontext)) {
            $courseid = 0;
            $coursecontext = NULL;
        }
    }

    if ($courseid) {
        global $DB;

        $course = $DB->get_record(
            'course', array('id' => $courseid), 'id,shortname', MUST_EXIST
        );

        $coursenode = $settingnode->add($course->shortname);

        $viewnode = $coursenode->add(
            get_string('viewcategories', 'local_dataseteditor'),
            new moodle_url(
                PLUGINPREFIX.'/categories.php',
                array('courseid' => $courseid)
            )
        );

        if ($cmid && $modulecontext) {
            $modulenode = $coursenode->add(
                $modulecontext->get_context_name(true),
                new moodle_url(
                    PLUGINPREFIX.'/categories.php',
                    array(
                        'courseid' => $courseid,
                        'cmid' => $cmid,
                    )
                )
            );
        }
    }
}


class dataset_wildcard_form extends moodleform {
    function definition() {
        global $CFG;

        $numwildcards = $this->_customdata['numwildcards'];
        if (! $numwildcards) {
            $numwildcards = 5;
        }

        $categoryid = $this->_customdata['categoryid'];
        if (! $categoryid) {
            $categoryid = 0;
        }


        $mform =& $this->_form;

        $mform->addElement(
            'hidden',
            'numwildcards',
            $numwildcards
        );

        $mform->addElement(
            'hidden',
            'categoryid',
            $categoryid
        );

        for ($i = 1; $i <= $numwildcards; $i++) {
            $prefix = 'sub_'.$i.'_';

            $mform->addElement(
                'text',
                $prefix.'name',
                get_string('wildcardX', 'local_dataseteditor', $i)
            );
            $mform->setType($prefix.'name', PARAM_TEXT);
        }

        // Add submit and reset buttons
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement(
            'submit',
            'submitbutton',
            get_string('savechanges')
        );
        $buttonarray[] = &$mform->createElement(
            'reset',
            'resetbutton',
            get_string('revert')
        );
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
}
