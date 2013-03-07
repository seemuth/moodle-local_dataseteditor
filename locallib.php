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
 * @return array array[id] => stdclass{->id ->name ->values}
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
        }
    }

    return $wildcards;
}
