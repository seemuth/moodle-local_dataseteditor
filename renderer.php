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
 * Workshop module renderering methods are defined here
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  2013 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once("$CFG->libdir/outputcomponents.php");

/**
 * Dataset editor renderer class
 *
 * @copyright 2013 Daniel Seemuth
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_dataseteditor_renderer extends plugin_renderer_base {

    ////////////////////////////////////////////////////////////////////////////
    // External API - methods to render dataset editor renderable components
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Renders wildcard edit form
     *
     * @param array $wildcards[id] = stdClass(->id ->name ->values)
     * @param int $min_rows Minimum number of wildcard rows to show
     * @param url $form_dest URL to which this form submits
     * @return string html code
     */
    public function render_wildcard_form($wildcards, $min_rows, $form_dest) {
        $form_attributes = array(
            'action' => $form_dest->out(),
            'method' => 'POST'
        );
        $form_contents = '';

        $table = new html_table();
        $table->head = array('Name', 'Current Values', 'Del?');
        $table->data = array();

        if (LOCAL_DATASETEDITOR_DEBUG) {
            array_unshift($table->head, 'ID');
        }

        /**
         * Sort wildcards by name, then id.
         */
        function wildcard_cmp($a, $b) {
            $aname = strtolower($a->name);
            $bname = strtolower($b->name);

            if ($aname != $bname) {
                return ($aname < $bname) ? -1 : 1;
            } elseif ($a->id != $b->id) {
                return ($a->id < $b->id) ? -1 : 1;
            } else {
                return 0;
            }
        }

        uasort($wildcards, 'wildcard_cmp');

        /**
         * Make sure we have the minimum number of wildcard fields.
         */
        $need = $min_rows - count($wildcards);
        for ($i = 0; $i < $need; $i++) {
            $wc = new stdClass();
            $wc->id = 0;
            $wc->name = '';
            $wc->values = array();

            $wildcards[] = $wc;
        }

        /* Add fields to edit each wildcard. */
        $i = 0;
        foreach ($wildcards as $wc) {
            $suffix = "_$i";

            $data_id = $wc->id;
            $data_id .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_id'. $suffix,
                'value' => $wc->id,
            ));

            $data_name = '{';
            $data_name .= html_writer::empty_tag('input', array(
                'type' => 'text',
                'name' => 'wc_name' . $suffix,
                'value' => $wc->name,
            ));
            $data_name .= '}';

            $data_values = implode(', ', $wc->values);

            if ($wc->id > 0) {
                $data_del = html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'wc_del' . $suffix,
                    'value' => '',
                ));
                $data_del .= html_writer::empty_tag('input', array(
                    'type' => 'checkbox',
                    'name' => 'wc_del' . $suffix,
                    'value' => 'yes',
                ));
            } else {
                $data_del = '';
            }

            $data_row = array($data_name, $data_values, $data_del);
            if (LOCAL_DATASETEDITOR_DEBUG) {
                array_unshift($data_row, $data_id);
            }
            $table->data[] = $data_row;

            $i++;
        }

        $num_wildcard_rows = $i;

        $form_contents .= html_writer::table($table);

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'num_wildcard_rows',
            'value' => $num_wildcard_rows,
        ));

        $sesskey_contents = 'KEY' . html_writer::empty_tag('input', array(
            'type' => 'text',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));
        $form_contents .= html_writer::tag('p', $sesskey_contents);

        $button_contents = '';
        $button_contents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_saveandadd',
            'value' => get_string('saveandadd', 'local_dataseteditor'),
        ));
        $button_contents .= html_writer::empty_tag('input', array(
            'type' => 'reset',
            'value' => get_string('reset', 'local_dataseteditor'),
        ));
        $button_contents .= html_writer::empty_tag('br');
        $button_contents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_save',
            'value' => get_string('save', 'local_dataseteditor'),
        ));
        $button_contents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_cancel',
            'value' => get_string('cancel', 'local_dataseteditor'),
        ));
        $form_contents .= html_writer::tag('p', $button_contents);

        return html_writer::tag('form', $form_contents, $form_attributes);
    }

}
