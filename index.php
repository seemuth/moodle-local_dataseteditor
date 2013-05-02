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
 * Run the code checker from the web.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/../../config.php');

require_login();

$PAGE->set_url(PLUGINPREFIX.'/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'local_dataseteditor'));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_dataseteditor'));

$result = $DB->get_records('course', array(), 'sortorder', 'id,fullname,shortname');

$table = new html_table();
$table->head = array('ID', 'Course Name');
$table->data = array();
foreach ($result as $row) {
    $coursecontext = context_course::instance($row->id);
    if (!has_capability(VIEW_CAPABILITY, $coursecontext)) {
        continue;
    }

    $link = html_writer::link(
        new moodle_url(
            PLUGINPREFIX.'/categories.php',
            array('courseid' => $row->id)
        ),
        $row->fullname
    );
    array_push($table->data, array($row->id, $link));
}
echo html_writer::table($table);

echo $OUTPUT->footer();
