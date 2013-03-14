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
 * Library of internal classes and functions for dataset editor
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  2013 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/lib.php');     // we extend this library here

/**
 * Returns array of id => wildcard
 *
 * @param int $categoryid Category from which to retrieve wildcards
 * @param int $val_limit Limit values to this many results
 * @return array array[id] => stdClass{->id ->name ->values}
 */
function get_wildcards($categoryid, $val_limit=4) {
    global $DB;

    $table_definitions = 'question_dataset_definitions';
    $table_values = 'question_dataset_items';

    $wildcard_results = $DB->get_records(
        $table_definitions,
        array('category' => $categoryid),
        'name',
        'id,name'
    );

    $wildcards = array();

    /* Retrieve wildcard definitions. */
    foreach ($wildcard_results as $row) {
        $wc = new stdClass();
        $wc->id = $row->id;
        $wc->name = $row->name;
        $wc->values = array();
        $wc->num_more_values = 0;   // Number of values not returned

        $wildcards[$wc->id] = $wc;
    }

    /* Retrieve sampling of wildcard values. */
    $value_results = $DB->get_records_list(
        $table_values,
        'definition',
        array_keys($wildcards),
        'itemnumber',
        'id,definition,value'
    );

    foreach ($value_results as $row) {
        if (count($wildcards[$row->definition]->values) < $val_limit) {
            $wildcards[$row->definition]->values[] = $row->value;
        } else {
            $wildcards[$row->definition]->num_more_values++;
        }
    }

    return $wildcards;
}

/**
 * Updates database with changed wildcard names.
 *
 * @param array $wildcards[] = stdClass(->id ->name ->del ->orig)
 * @param stdClass $defaults Default values for category, type, options,
 * itemcount
 * @return null
 */
function save_wildcard_names($wildcards, $defaults) {
    $table = 'question_dataset_definitions';

    $fields = array('category', 'name', 'type', 'options', 'itemcount');

    foreach ($wildcards as $wc) {
        if ($wc->id > 0) {
            /**
             * Existing wildcard! Update only if changed.
             */
            if ($wc->name != $wc->orig) {
                $DB->set_field($table, 'name', $wc->name, array(
                    'id' => $wc->id,
                ));
            }
        } else {
            /**
             * New wildcard! Insert into database.
             */
            $new_wc = new stdClass();

            foreach ($fields as $field) {
                if (isset($wc->$field)) {
                    $new_wc->$field = $wc->field;
                } elseif (isset($defaults->$field)) {
                    $new_wc->$field = $defaults->$field;
                } else {
                    throw new coding_exception('Undefined field: ' . $field);
                }
            }

            $DB->insert_record($table, $new_wc);
        }
    }
}


/**
 * Returns array of itemnum => array(defnum => item)
 *
 * @param array $wildcardids Retrieve values matching these wildcard IDs
 * @return array array[itemnum] => array(defnum => stdClass{->id ->val})
 */
function get_dataset_items($wildcardids) {
    global $DB;

    $table_values = 'question_dataset_items';

    $value_results = $DB->get_records_list(
        $table_values,
        'definition',
        $wildcardids,
        '',
        'id,definition,itemnumber,value'
    );

    $items = array();

    /* Retrieve wildcard definitions. */
    foreach ($value_results as $row) {
        $item = new stdClass();
        $item->id = $row->id;
        $item->val = $row->value;

        if (! isset($items[$row->itemnumber])) {
            $items[$row->itemnumber] = array();
        }

        $items[$row->itemnumber][$row->definition] = $item;
    }

    return $items;
}
