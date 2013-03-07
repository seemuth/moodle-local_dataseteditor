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
        $form = new html_form();
        $form->url = $form_dest;
        $form->button->text = 'TEST TEXT';

        $contents = '';

        $table = new html_table();
        $table->head = array('Name', 'Current Values', 'Del?');
        $table->data = array();

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

        uasort($wildcards, wildcard_cmp);

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

            $data_id = <<<EOT
$wc->id<input type="hidden" name="wc_id$suffix" value="$wc->id" />
EOT;

            $data_name = <<<EOT
<input type="input" name="wc_name$suffix" value="$wc->name" />
EOT;

            $data_values = implode(', ', $wc->values);

            if ($wc->id > 0) {
                $data_del = <<<EOT
<input type="hidden" name="wc_del$suffix" value="" />
<input type="checkbox" name="wc_del$suffix" value="yes" />
EOT;
            } else {
                $data_del = '';
            }

            $table->data[] = array($data_id, $data_name, $data_values, $data_del);

            $i++;
        }

        $contents .= html_writer::table($table);

        return $this->output->form($form, $contents);
    }

}
