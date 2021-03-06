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
 * @copyright  2013-2015 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/lib.php');

define('LOCAL_DATASETEDITOR_DEFAULT_WILDCARD_OPTIONS', 'uniform:0:0:0');
define('LOCAL_DATASETEDITOR_PREF_PREFIX', 'local_dataseteditor_');


/**
 * Returns true if the dataset editor navigation should appear for
 * the given course module name.
 *
 * @param string $modulename Course module name
 * @return boolean if dataset editor navigation should appear
 */
function local_dataseteditor_applicable_module($modulename) {
    $modulename = strtolower($modulename);

    if ($modulename == 'quiz') {
        return true;
    }

    return false;
}


/**
 * Returns context ID for the given question category
 *
 * @param int $categoryid Question category ID
 * @param bool $mustexist If true and the category does not exist, throw error
 * @return int $contextid
 */
function local_dataseteditor_get_cat_contextid(
    $categoryid,
    $mustexist = true
) {
    global $DB;

    $tablecategories = 'question_categories';

    if ($mustexist) {
        $strictness = MUST_EXIST;
    } else {
        $strictness = IGNORE_MISSING;
    }

    return $DB->get_field(
        $tablecategories,
        'contextid',
        array('id' => $categoryid),
        $strictness
    );
}


/**
 * Returns category IDs for the given wildcard IDs
 *
 * @param array $wildcardids Wildcard IDs
 * @return array array[$categoryid] = num_wildcards
 */
function local_dataseteditor_get_wildcard_categoryids($wildcardids) {
    global $DB;

    $tabledefinitions = 'question_dataset_definitions';

    if (empty($wildcardids)) {
        return array();
    }

    $ret = array();

    list($whereids, $params) = $DB->get_in_or_equal($wildcardids);

    $sql = 'SELECT category, COUNT(id) AS num_wc ' .
        'FROM {' . $tabledefinitions . '} ' .
        'WHERE id ' . $whereids . ' ' .
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
function local_dataseteditor_get_dataset_item_categoryids($itemids) {
    global $DB;

    $tabledefinitions = 'question_dataset_definitions';
    $tablevalues = 'question_dataset_items';

    if (empty($itemids)) {
        return array();
    }

    $ret = array();

    list($whereids, $params) = $DB->get_in_or_equal($itemids);

    $sql = 'SELECT d.category, COUNT(v.id) AS num_v ' .
        'FROM {' . $tabledefinitions . '} d ' .
        'INNER JOIN {' . $tablevalues . '} v ON v.definition = d.id ' .
        'WHERE v.id ' . $whereids . ' ' .
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
function local_dataseteditor_all_wildcards_in_cat($wildcardids, $categoryid) {
    if (empty($wildcardids)) {
        return true;
    }

    $categories = local_dataseteditor_get_wildcard_categoryids($wildcardids);

    $foundcats = 0;
    foreach ($categories as $id => $num) {
        if ($id != $categoryid) {
            return false;
        }

        $foundcats += $num;
    }

    if ($foundcats != count(array_unique($wildcardids))) {
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
function local_dataseteditor_all_dataset_items_in_cat($itemids, $categoryid) {
    if (empty($itemids)) {
        return true;
    }

    $categories = local_dataseteditor_get_dataset_item_categoryids($itemids);

    $foundcats = 0;
    foreach ($categories as $id => $num) {
        if ($id != $categoryid) {
            return false;
        }

        $foundcats += $num;
    }

    if ($foundcats != count(array_unique($itemids))) {
        return false;
    }

    return true;
}


/**
 * Returns array of id => wildcard
 *
 * @param int $categoryid Category from which to retrieve wildcards
 * @param int $vallimit Limit values to this many results
 * @return array array[id] => stdClass{->id ->name ->values}
 */
function local_dataseteditor_get_wildcards($categoryid, $vallimit=4) {
    global $DB;

    $tabledefinitions = 'question_dataset_definitions';
    $tablevalues = 'question_dataset_items';

    $wildcardresults = $DB->get_records(
        $tabledefinitions,
        array('category' => $categoryid),
        'name',
        'id,name'
    );

    $wildcards = array();

    /* Retrieve wildcard definitions. */
    foreach ($wildcardresults as $row) {
        $wc = new stdClass();
        $wc->id = $row->id;
        $wc->name = $row->name;
        $wc->values = array();
        $wc->num_more_values = 0;   // Pass number of values not returned.

        $wildcards[$wc->id] = $wc;
    }

    /* Retrieve sampling of wildcard values. */
    $valueresults = $DB->get_records_list(
        $tablevalues,
        'definition',
        array_keys($wildcards),
        'itemnumber',
        'id,definition,value'
    );

    foreach ($valueresults as $row) {
        if (count($wildcards[$row->definition]->values) < $vallimit) {
            $wildcards[$row->definition]->values[] = $row->value;
        } else {
            $wildcards[$row->definition]->num_more_values++;
        }
    }

    return $wildcards;
}

/**
 * Validate wildcard names. Ignore wildcards that are marked for deletion.
 *
 * @param array $wildcards[] = stdClass(->id ->name ->del ->orig)
 * @return false|string error string if problem, or false if no errors
 * @throws coding_exception
 */
function local_dataseteditor_validate_wildcards($wildcards) {
    $seenwildcards = array();

    foreach ($wildcards as $wc) {
        if ($wc->del) {
            continue;
        }

        if (empty($wc->name)) {
            continue;
        }

        if (isset($seenwildcards[$wc->name])) {
            return get_string('dup_wildcard_X', 'local_dataseteditor', $wc->name);
        }

        $seenwildcards[$wc->name] = $wc;
    }

    // No errors.
    return false;
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
function local_dataseteditor_save_wildcards(
    $wildcards, $defaults, $categoryid
) {
    $tabledefinitions = 'question_dataset_definitions';
    $tablevalues = 'question_dataset_items';

    $fields = array('category', 'name', 'type', 'options', 'itemcount');

    global $DB;

    /* Check all wildcard IDs against this category ID! */
    $ids = array();
    foreach ($wildcards as $wc) {
        if ($wc->id > 0) {
            $ids[] = $wc->id;
        }
    }

    if (! local_dataseteditor_all_wildcards_in_cat($ids, $categoryid)) {
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
                $DB->delete_records($tabledefinitions, array(
                    'id' => $wc->id,
                ));
                $DB->delete_records($tablevalues, array(
                    'definition' => $wc->id,
                ));

            } else {
                /* Existing wildcard! Update only if changed. */
                if ($wc->name != $wc->orig) {
                    $DB->set_field($tabledefinitions, 'name', $wc->name,
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

            $newwc = new stdClass();

            foreach ($fields as $field) {
                if (isset($wc->$field)) {
                    $newwc->$field = $wc->$field;
                } else if (isset($defaults->$field)) {
                    $newwc->$field = $defaults->$field;
                } else {
                    throw new coding_exception('Undefined field: ' . $field);
                }
            }

            $DB->insert_record($tabledefinitions, $newwc);
        }
    }
}


/**
 * Returns array of itemnum => array(defnum => item)
 *
 * @param array $wildcardids Retrieve values matching these wildcard IDs
 * @return array array[itemnum] => array(defnum => stdClass{->id ->val})
 */
function local_dataseteditor_get_dataset_items($wildcardids) {
    global $DB;

    $tablevalues = 'question_dataset_items';

    $valueresults = $DB->get_records_list(
        $tablevalues,
        'definition',
        $wildcardids,
        '',
        'id,definition,itemnumber,value'
    );

    $items = array();

    /* Retrieve wildcard definitions. */
    foreach ($valueresults as $row) {
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
function local_dataseteditor_save_dataset_items(
    $items, $deleteitems, $categoryid
) {

    $tabledefinitions = 'question_dataset_definitions';
    $tablevalues = 'question_dataset_items';

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

    if (! local_dataseteditor_all_dataset_items_in_cat($ids, $categoryid)) {
        throw new coding_exception(
            'Not all dataset items in category ' . $categoryid
        );
    }

    ksort($items);

    /* Keep track of how many item numbers deleted so far.
     * Modify subsequent item numbers by subtracting this number.
     * This ensures that item numbers always start at 1 and are consecutive.
     */
    $numdeleted = 0;

    /* Keep track of how many items remain. */
    $numdata = 0;

    foreach ($items as $itemnum => $def2val) {
        if (isset($deleteitems[$itemnum])) {
            /* Delete dataset items with this item number and matching
             * the definition IDs.
             */

            foreach ($def2val as $defnum => $item) {
                $DB->delete_records($tablevalues, array(
                    'definition' => $defnum,
                    'itemnumber' => $itemnum,
                ));
            }

            $numdeleted++;
            continue;
        }

        /* Not marked for deletion! Update $itemnum as needed. */
        $itemnum -= $numdeleted;

        $itemhasdata = false;
        foreach ($def2val as $defnum => $item) {
            if ((! isset($item->val)) || ($item->val === null)) {
                throw new coding_exception(
                    'No value defined for ' . $itemnum . ', ' . $defnum
                );
            }

            $itemhasdata = true;

            if ($item->id > 0) {
                /* Existing value! Update only if changed. */
                if ($item->val != $item->orig) {
                    $DB->set_field($tablevalues, 'value', $item->val,
                        array('id' => $item->id));
                }

                /* Update item number if any items have been deleted. */
                if ($numdeleted) {
                    $DB->set_field($tablevalues, 'itemnumber', $itemnum,
                        array('id' => $item->id));
                }

            } else {
                /* New value! Insert into database. */

                $newitem = new stdClass();
                $newitem->definition = $defnum;
                $newitem->itemnumber = $itemnum;
                $newitem->value = $item->val;

                $DB->insert_record($tablevalues, $newitem);
            }
        }

        if ($itemhasdata) {
            $numdata++;
        }
    }

    /* Update wildcards' itemcount field. */
    $DB->set_field($tabledefinitions, 'itemcount', $numdata, array(
        'category' => $categoryid
    ));
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
function local_dataseteditor_overwrite_wildcard_dataset(
    $categoryid, $wildcards, $items,
    $requirefulldataset = true
) {

    $tabledefinitions = 'question_dataset_definitions';
    $tablevalues = 'question_dataset_items';

    global $DB;

    $curwildcards = local_dataseteditor_get_wildcards($categoryid, 0);
    $curname2id = array();
    foreach ($curwildcards as $wc) {
        $curname2id[$wc->name] = $wc->id;
    }

    if (count($curwildcards) != count($curname2id)) {
        throw new coding_exception('Duplicate wildcard names');
    }

    /* Compile list of deleted and new wildcards.  */
    $newname2id = array();
    foreach ($wildcards as $name) {
        if (array_key_exists($name, $curname2id)) {
            $newname2id[$name] = $curname2id[$name];
        } else {
            $newname2id[$name] = null;
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
    $deleteids = array();
    foreach ($curname2id as $name => $id) {
        if (! array_key_exists($name, $newname2id)) {
            $deleteids[] = $id;
        }
    }

    if (! empty($deleteids)) {
        list($whereids, $params) = $DB->get_in_or_equal($deleteids);
        $DB->delete_records_select($tabledefinitions, 'id ' . $whereids,
            $params);
    }

    /* Add new wildcards. */
    foreach ($newname2id as $name => $id) {
        if ($id === null) {
            $o = new stdClass();
            $o->category = $categoryid;
            $o->name = $name;
            $o->type = 1;
            $o->options = LOCAL_DATASETEDITOR_DEFAULT_WILDCARD_OPTIONS;
            $o->itemcount = 0;  /* Will be updated. */

            $id = $DB->insert_record($tabledefinitions, $o);
            $newname2id[$name] = $id;
        }
    }

    /* Delete old values. */
    if (! empty($curwildcards)) {
        list($whereids, $params) = $DB->get_in_or_equal(
            array_keys($curwildcards));
        $DB->delete_records_select($tablevalues, 'definition ' . $whereids,
            $params);
    }

    /* Insert new values. */
    $itemnum = 0;
    foreach ($wildcards as $i => $name) {
        $wcid = $newname2id[$name];

        $itemnum = 0;
        foreach ($items as $values) {
            $itemnum++;

            $o = new stdClass();
            $o->definition = $wcid;
            $o->itemnumber = $itemnum;
            $o->value = $values[$i];

            $DB->insert_record($tablevalues, $o);
        }
    }

    /* Update wildcards' itemcount field. */
    $DB->set_field($tabledefinitions, 'itemcount', $itemnum, array(
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
function local_dataseteditor_require_capability_cat(
    $capability, $categoryid
) {
    $contextid = local_dataseteditor_get_cat_contextid($categoryid, true);
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
function local_dataseteditor_get_cm($courseid, $cmid) {
    global $DB;

    $course = $DB->get_record('course',
        array('id' => $courseid),
        '*',
        MUST_EXIST
    );

    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm($cmid);

    return $cm;
}


/**
 * Return user's preference for category selection in course $courseid.
 *
 * @param int $courseid ID of the course
 * @return string Preferred category selection
 */
function local_dataseteditor_get_category_preference($courseid) {
    $key = LOCAL_DATASETEDITOR_PREF_PREFIX . 'c_' . strval($courseid);
    return get_user_preferences($key, '');
}


/**
 * Set user's preference for category selection in course $courseid.
 *
 * @param int $courseid ID of the course
 * @param string $category Preferred category selection
 * @return bool Always true or exception
 */
function local_dataseteditor_set_category_preference($courseid, $category) {
    $key = LOCAL_DATASETEDITOR_PREF_PREFIX . 'c_' . strval($courseid);
    set_user_preference($key, strval($category));

    return true;
}


/**
 * Unset user's preference for category selection in course $courseid.
 *
 * @param int $courseid ID of the course
 * @return bool Always true or exception
 */
function local_dataseteditor_unset_category_preference($courseid) {
    $key = LOCAL_DATASETEDITOR_PREF_PREFIX . 'c_' . strval($courseid);
    unset_user_preference($key);

    return true;
}
