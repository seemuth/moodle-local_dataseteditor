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

require_once(dirname(__FILE__).'/lib.php');

define('DEFAULT_WILDCARD_OPTIONS', 'uniform:1.0:10.0:1');


/**
 * Returns context ID for the given question category
 *
 * @param int $categoryid Question category ID
 * @return int $contextid
 */
function get_cat_contextid($categoryid) {
    global $DB;

    $table_categories = 'question_categories';

    return $DB->get_field(
        $table_categories,
        'contextid',
        array('id' => $categoryid),
        MUST_EXIST
    );
}


/**
 * Returns category IDs for the given wildcard IDs
 *
 * @param array $wildcardids Wildcard IDs
 * @return array array[$categoryid] = num_wildcards
 */
function get_wildcard_categoryids($wildcardids) {
    global $DB;

    $table_definitions = 'question_dataset_definitions';

    if (empty($wildcardids)) {
        return array();
    }

    $ret = array();

    list($where_ids, $params) = $DB->get_in_or_equal($wildcardids);

    $sql = 'SELECT category, COUNT(id) AS num_wc ' .
        'FROM {' . $table_definitions . '} ' .
        'WHERE id ' . $where_ids . ' ' .
        'GROUP BY category';

    $results = $DB->get_records_sql($sql, $params);

    foreach ($results as $row) {
        $ret[$row->category] = $row->num_wc;
    }

    return $ret;
}


/**
 * Returns category IDs for the given dataset item IDs
 *
 * @param array $itemids Dataset item IDs
 * @return array array[$categoryid] = num_items
 */
function get_dataset_item_categoryids($itemids) {
    global $DB;

    $table_definitions = 'question_dataset_definitions';
    $table_values = 'question_dataset_items';

    if (empty($itemids)) {
        return array();
    }

    $ret = array();

    list($where_ids, $params) = $DB->get_in_or_equal($itemids);

    $sql = 'SELECT d.category, COUNT(v.id) AS num_v ' .
        'FROM {' . $table_definitions . '} d ' .
        'INNER JOIN {' . $table_values . '} v ON v.definition = d.id ' .
        'WHERE v.id ' . $where_ids . ' ' .
        'GROUP BY d.category';

    $results = $DB->get_records_sql($sql, $params);

    foreach ($results as $row) {
        $ret[$row->category] = $row->num_v;
    }

    return $ret;
}


/**
 * Check if all the wildcards to be within the question category.
 *
 * @param array $wildcardids Wildcard IDs
 * @param int $categoryid Question category id
 * @return bool True if all the wildcards are in the question category
 *      (or true if no wildcards were given)
 */
function all_wildcards_in_cat($wildcardids, $categoryid) {
    if (empty($wildcardids)) {
        return true;
    }

    $categories = get_wildcard_categoryids($wildcardids);

    $found_cats = 0;
    foreach ($categories as $id => $num) {
        if ($id != $categoryid) {
            return false;
        }

        $found_cats += $num;
    }

    if ($found_cats != count(array_unique($wildcardids))) {
        return false;
    }

    return true;
}


/**
 * Check if all the dataset items are within the question category.
 *
 * @param array $itemids Dataset item IDs
 * @param int $categoryid Question category id
 * @return bool True if all the dataset items are in the question category
 *      (or true if no dataset items were given)
 */
function all_dataset_items_in_cat($itemids, $categoryid) {
    if (empty($itemids)) {
        return true;
    }

    $categories = get_dataset_item_categoryids($itemids);

    $found_cats = 0;
    foreach ($categories as $id => $num) {
        if ($id != $categoryid) {
            return false;
        }

        $found_cats += $num;
    }

    if ($found_cats != count(array_unique($itemids))) {
        return false;
    }

    return true;
}


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
        $wc->num_more_values = 0;   // Pass number of values not returned.

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
 * Updates database with changed wildcard names. Also deletes orphan dataset
 * item values.
 *
 * @param array $wildcards[] = stdClass(->id ->name ->del ->orig)
 * @param stdClass $defaults Default values for category, type, options,
 * @param int $categoryid Require all wildcards to be in this category
 * itemcount
 * @return null
 * @throws coding_exception
 */
function save_wildcards($wildcards, $defaults, $categoryid) {
    $table_definitions = 'question_dataset_definitions';
    $table_values = 'question_dataset_items';

    $fields = array('category', 'name', 'type', 'options', 'itemcount');

    global $DB;

    /* Check all wildcard IDs against this category ID! */
    $ids = array();
    foreach ($wildcards as $wc) {
        if ($wc->id > 0) {
            $ids[] = $wc->id;
        }
    }

    if (! all_wildcards_in_cat($ids, $categoryid)) {
        throw new coding_exception(
            'Not all wildcards in category ' . $categoryid
        );
    }

    foreach ($wildcards as $wc) {
        if (empty($wc->name)) {
            /* Empty name. Treat as deleted. */
            $wc->del = 1;
        }

        if ($wc->id > 0) {
            if ($wc->del) {
                /* Delete this wildcard and its dataset item values. */
                $DB->delete_records($table_definitions, array(
                    'id' => $wc->id,
                ));
                $DB->delete_records($table_values, array(
                    'definition' => $wc->id,
                ));

            } else {
                /* Existing wildcard! Update only if changed. */
                if ($wc->name != $wc->orig) {
                    $DB->set_field($table_definitions, 'name', $wc->name,
                        array('id' => $wc->id));
                }

            }

        } else {
            /* New wildcard!
             * Insert into database if not marked for deletion.
             */
            if ($wc->del) {
                continue;
            }

            $new_wc = new stdClass();

            foreach ($fields as $field) {
                if (isset($wc->$field)) {
                    $new_wc->$field = $wc->$field;
                } else if (isset($defaults->$field)) {
                    $new_wc->$field = $defaults->$field;
                } else {
                    throw new coding_exception('Undefined field: ' . $field);
                }
            }

            $DB->insert_record($table_definitions, $new_wc);
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

/**
 * Updates database with changed dataset items.
 *
 * @param array
 *      $items[itemnum] => array(defnum => stdClass{->id ->val ->orig})
 * @param array $deleteitems[itemnum] = i (use keys, ignore values)
 *      Delete items with these itemnums
 * @param int $categoryid Require all dateset items to be in this category
 * @return null
 * @throws coding_exception
 */
function save_dataset_items($items, $deleteitems, $categoryid) {
    $table_values = 'question_dataset_items';

    global $DB;

    /* Check all wildcard IDs against this category ID! */
    $ids = array();
    foreach ($items as $def2val) {
        foreach ($def2val as $defnum => $item) {
            if ($item->id > 0) {
                $ids[] = $item->id;
            }
        }
    }

    if (! all_dataset_items_in_cat($ids, $categoryid)) {
        throw new coding_exception(
            'Not all dataset items in category ' . $categoryid
        );
    }

    ksort($items);

    /* Keep track of how many item numbers deleted so far.
     * Modify subsequent item numbers by subtracting this number.
     * This ensures that item numbers always start at 1 and are consecutive.
     */
    $num_deleted = 0;

    foreach ($items as $itemnum => $def2val) {
        if (isset($deleteitems[$itemnum])) {
            /* Delete dataset items with this item number and matching
             * the definition IDs.
             */

            foreach ($def2val as $defnum => $item) {
                $DB->delete_records($table_values, array(
                    'definition' => $defnum,
                    'itemnumber' => $itemnum,
                ));
            }

            $num_deleted++;
            continue;
        }

        /* Not marked for deletion! Update $itemnum as needed. */
        $itemnum -= $num_deleted;

        foreach ($def2val as $defnum => $item) {
            if (empty($item->val)) {
                /* Empty value. Ignore. */
                continue;
            }

            if ($item->id > 0) {
                /* Existing value! Update only if changed. */
                if ($item->val != $item->orig) {
                    $DB->set_field($table_values, 'value', $item->val,
                        array('id' => $item->id));
                }

                /* Update item number if any items have been deleted. */
                if ($num_deleted) {
                    $DB->set_field($table_values, 'itemnumber', $itemnum,
                        array('id' => $item->id));
                }

            } else {
                /* New value! Insert into database. */

                $new_item = new stdClass();
                $new_item->definition = $defnum;
                $new_item->itemnumber = $itemnum;
                $new_item->value = $item->val;

                $DB->insert_record($table_values, $new_item);
            }
        }
    }
}


/**
 * Overwrites wildcards and dataset with imported data
 *
 * @param int $categoryid Category whose data to overwrite
 * @param array $wildcards[id] = name
 * @param array $items[] = array(defnum => val)
 * @return null
 * @throws coding_exception
 */
function overwrite_wildcard_dataset(
    $categoryid, $wildcards, $items,
    $requirefulldataset = true
) {

    $table_definitions = 'question_dataset_definitions';
    $table_values = 'question_dataset_items';

    global $DB;

    $cur_wildcards = get_wildcards($categoryid, 0);
    $cur_name2id = array();
    foreach ($cur_wildcards as $wc) {
        $cur_name2id[$wc->name] = $wc->id;
    }

    if (count($cur_wildcards) != count($cur_name2id)) {
        throw new coding_exception('Duplicate wildcard names');
    }

    /* Compile list of deleted and new wildcards.  */
    $new_name2id = array();
    foreach ($wildcards as $name) {
        if (array_key_exists($name, $cur_name2id)) {
            $new_name2id[$name] = $cur_name2id[$name];
        } else {
            $new_name2id[$name] = null;
        }
    }

    /* If required, make sure each dataset item has all values. */
    if ($requirefulldataset) {
        foreach ($items as $values) {
            foreach ($wildcards as $i => $name) {
                if (! isset($values[$i])) {
                    throw new coding_exception(
                        'No value defined for ' . $i . ', ' . $name
                    );
                }
            }
        }
    }

    /* Delete old, unused wildcards. */
    $delete_ids = array();
    foreach ($cur_name2id as $name => $id) {
        if (! array_key_exists($name, $new_name2id)) {
            $delete_ids[] = $id;
        }
    }

    if (! empty($delete_ids)) {
        list($where_ids, $params) = $DB->get_in_or_equal($delete_ids);
        $DB->delete_records_select($table_definitions, 'id ' . $where_ids,
            $params);
    }

    /* Add new wildcards. */
    foreach ($new_name2id as $name => $id) {
        if ($id === null) {
            $o = new stdClass();
            $o->category = $categoryid;
            $o->name = $name;
            $o->type = 1;
            $o->options = DEFAULT_WILDCARD_OPTIONS;
            $o->itemcount = 0;  /* Will be updated. */

            $id = $DB->insert_record($table_definitions, $o);
            $new_name2id[$name] = $id;
        }
    }

    /* Delete old values. */
    if (! empty($cur_wildcards)) {
        list($where_ids, $params) = $DB->get_in_or_equal(
            array_keys($cur_wildcards));
        $DB->delete_records_select($table_values, 'definition ' . $where_ids,
            $params);
    }

    /* Insert new values. */
    $itemnum = 0;
    foreach ($wildcards as $i => $name) {
        $wc_id = $new_name2id[$name];

        $itemnum = 0;
        foreach ($items as $values) {
            $itemnum++;

            $o = new stdClass();
            $o->definition = $wc_id;
            $o->itemnumber = $itemnum;
            $o->value = $values[$i];

            $DB->insert_record($table_values, $o);
        }
    }

    /* Update wildcards' itemcount field. */
    $DB->set_field($table_definitions, 'itemcount', $itemnum, array(
        'category' => $categoryid
    ));
}


/**
 * Require the user to have access to the given question category.
 *
 * @param string $capability Require the user to have this capability
 * @param int $categoryid Question category id
 * @return void terminates with an error if the user does not have the given 
 * capability.
 */
function require_capability_cat($capability, $categoryid) {
    $contextid = get_cat_contextid($categoryid);
    $context = context::instance_by_id($contextid);
    require_capability($capability, $context);
}


/**
 * Return course module from within course $courseid.
 *
 * @param int $courseid ID of course containing module
 * @param int $cmid Course-module ID
 * @return cm_info Information about that course-module
 * @throws moodle_exception If the course-module does not exist
 */
function get_cm($courseid, $cmid) {
    global $DB;

    $course = $DB->get_record('course',
        array('id' => $courseorid),
        '*',
        MUST_EXIST
    );

    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm($cmid);

    return $cm;
}
