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
 * @copyright  2013-2015 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/defines.php');
require_once(dirname(__FILE__) . '/../../config.php');
require_once("$CFG->libdir/outputcomponents.php");

if (! isset($CFG->localdataseteditordebug)) {
    $CFG->localdataseteditordebug = false;
}


/**
 * Sort wildcards by name, then id.
 */
function local_dataseteditor_wildcard_cmp($a, $b) {
    $aname = strtolower($a->name);
    $bname = strtolower($b->name);

    if ($aname != $bname) {
        return ($aname < $bname) ? -1 : 1;
    } else if ($a->id != $b->id) {
        return ($a->id < $b->id) ? -1 : 1;
    } else {
        return 0;
    }
}


/**
 * Dataset editor renderer class
 *
 * @copyright  2013-2015 Daniel Seemuth
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_dataseteditor_renderer extends plugin_renderer_base {

    /* This external API defines methods to render dataset editor
     * renderable components.
     */

    /**
     * Renders message of the desired type.
     */
    public function render_message($text, $type='info') {
        $inner = html_writer::tag('span', $text);
        return $this->output->container($inner, array('message', $type));
    }


    /**
     * Renders wildcard edit form
     *
     * @param array $wildcards[] = stdClass(->id ->name ->values)
     * @param array $uservals[] = stdClass(->id ->name ->del ->orig)
     * Override starting values on form
     * @param int $minrows Minimum number of wildcard rows to show
     * @param url $formdest URL to which this form submits
     * @return string html code
     */
    public function render_wildcard_form($wildcards, $uservals,
        $minrows, $formdest
    ) {
        global $CFG;

        $formattributes = array(
            'action' => $formdest->out(false),
            'method' => 'POST'
        );
        $formcontents = '';

        $table = new html_table();
        $table->attributes['class'] = 'flexible generaltable';
        $table->head = array(
            get_string('name', 'local_dataseteditor'),
            get_string('curvals', 'local_dataseteditor'),
            get_string('delete_p', 'local_dataseteditor'),
        );
        $table->data = array();

        if ($CFG->localdataseteditordebug) {
            array_unshift($table->head,
                get_string('id', 'local_dataseteditor')
            );
        }

        uasort($wildcards, 'local_dataseteditor_wildcard_cmp');

        /* Split $newvals into two arrays:
         *      $valoverride[id] = stdClass
         *      $newvals[] = stdClass
         */

        $valoverride = array();
        $newvals = array();
        foreach ($uservals as $uv) {
            if ($uv->id > 0) {
                $valoverride[$uv->id] = $uv;
            } else {
                $newvals[] = $uv;
            }
        }

        $overridefields = array('name', 'del', 'orig');

        /* Set defaults for each wildcard, then override fields as needed.
         */
        foreach ($wildcards as $wc) {
            $wc->orig = $wc->name;
            $wc->del = 0;

            if (isset($valoverride[$wc->id])) {
                foreach ($overridefields as $field) {
                    $wc->$field = $valoverride[$wc->id]->$field;
                }
            }
        }

        /* Make sure we have the minimum number of wildcard fields. */
        $need = $minrows - count($wildcards);
        for ($i = 0; $i < $need; $i++) {
            $wc = new stdClass();
            $wc->id = 0;
            $wc->name = '';
            $wc->orig = '';
            $wc->del = 0;
            $wc->values = array();

            if (isset($newvals[$i])) {
                foreach ($overridefields as $field) {
                    $wc->$field = $newvals[$i]->$field;
                }
            }

            $wildcards[] = $wc;
        }

        /* Add fields to edit each wildcard. */
        $i = 0;
        foreach ($wildcards as $wc) {
            $suffix = '_' . $i;

            $dataid = html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_id'. $suffix,
                'value' => $wc->id,
            ));

            $dataname = '{';
            $dataname .= html_writer::empty_tag('input', array(
                'type' => 'text',
                'name' => 'wc_name' . $suffix,
                'value' => $wc->name,
            ));
            $dataname .= '}';
            $dataname .= $dataid;
            $dataname .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_orig' . $suffix,
                'value' => $wc->orig,
            ));

            $datavalues = implode(', ', $wc->values);

            $datadel = html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_del' . $suffix,
                'value' => '',
            ));
            $delcheckboxattr = array(
                'type' => 'checkbox',
                'name' => 'wc_del' . $suffix,
                'value' => 'yes',
            );
            if ($wc->del) {
                $delcheckboxattr['checked'] = 'checked';
            }
            $datadel .= html_writer::empty_tag('input', $delcheckboxattr);

            $datarow = array($dataname, $datavalues, $datadel);
            if ($CFG->localdataseteditordebug) {
                array_unshift($datarow, $wc->id);
            }
            $table->data[] = $datarow;

            $i++;
        }

        $numwildcardrows = $i;

        $formcontents .= html_writer::tag(
            'div',
            html_writer::table($table),
            array('class' => 'no-overflow')
        );

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'num_wildcard_rows',
            'value' => $numwildcardrows,
        ));

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

        $buttoncontents = '';
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_saveandadd',
            'value' => get_string('saveandadd', 'local_dataseteditor'),
        ));
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'reset',
            'value' => get_string('reset', 'local_dataseteditor'),
        ));
        $buttoncontents .= html_writer::empty_tag('br');
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_save',
            'value' => get_string('save', 'local_dataseteditor'),
        ));
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_cancel',
            'value' => get_string('cancel', 'local_dataseteditor'),
        ));
        $formcontents .= html_writer::tag('p', $buttoncontents);

        return html_writer::tag('form', $formcontents, $formattributes);
    }


    /**
     * Renders dataset edit form
     *
     * @param array $wildcards[id] = stdClass(->id ->name ->values)
     * @param array $items[itemnum] = array(defnum => stdClass(->id ->val))
     * @param array $uservals[itemnum] = array(defnum => stdClass(->val))
     * @param array $deleteitems[itemnum] = (don't care)
     * @param int $minrows Minimum number of item rows to show
     * @param url $formdest URL to which this form submits
     * @return string html code
     */
    public function render_dataset_form($wildcards, $items,
        $uservals, $deleteitems, $minrows, $formdest
    ) {
        global $CFG;

        $formattributes = array(
            'action' => $formdest->out(false),
            'method' => 'POST'
        );
        $formcontents = '';

        $table = new html_table();
        $table->attributes['class'] = 'flexible generaltable';
        $table->head = array(
            get_string('itemnum', 'local_dataseteditor'),
        );
        $table->data = array();

        uasort($wildcards, 'local_dataseteditor_wildcard_cmp');

        foreach ($wildcards as $wc) {
            $table->head[] = '{' . $wc->name . '}';
        }

        $table->head[] = get_string('delete_p', 'local_dataseteditor');

        /* Make sure we have the minimum number of item fields. */
        $itemkeys = array_keys($items);
        $need = $minrows - count($itemkeys);

        if (empty($itemkeys)) {
            $itemkey = 0;
        } else {
            $itemkey = max($itemkeys);
        }

        for ($i = 0; $i < $need; $i++) {
            $item = array();

            $itemkey++;
            $items[$itemkey] = $item;
        }
        unset($itemkeys);

        /* Add fields to edit each dataset item. */
        ksort($items);
        foreach ($items as $itemkey => $item) {

            $anydata = false;  /* True if any data is currently defined */

            $datarow = array();

            foreach ($wildcards as $wc) {
                $suffix = '_i' . $itemkey . '_w' . $wc->id;

                if (isset($item[$wc->id])) {
                    $anydata = true;

                    $id = $item[$wc->id]->id;
                    $val = $item[$wc->id]->val;
                } else {
                    $id = 0;
                    $val = '';
                }
                $orig = $val;

                if (isset($uservals[$itemkey][$wc->id])) {
                    $val = $uservals[$itemkey][$wc->id]->val;
                    $orig = $uservals[$itemkey][$wc->id]->orig;
                }

                $dataid = html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'data_id'. $suffix,
                    'value' => $id,
                ));

                if ($CFG->localdataseteditordebug) {
                    $dataid .= $id . ' ';
                }

                $dataval = html_writer::empty_tag('input', array(
                    'type' => 'text',
                    'name' => 'data_val' . $suffix,
                    'value' => $val,
                ));
                $dataval .= html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'data_orig' . $suffix,
                    'value' => $orig,
                ));

                $datarow[] = $dataid . $dataval;
            }

            /* Add row label, including annotation for new data. */
            $rowlabel = $itemkey;
            if (! $anydata) {
                $rowlabel .= ' ' . get_string('paren_newdata',
                    'local_dataseteditor');
            }
            array_unshift($datarow, $rowlabel);

            $datadel = html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'data_del_i' . $itemkey,
                'value' => '',
            ));
            $delcheckboxattr = array(
                'type' => 'checkbox',
                'name' => 'data_del_i' . $itemkey,
                'value' => 'yes',
            );
            if (isset($deleteitems[$itemkey])) {
                $delcheckboxattr['checked'] = 'checked';
            }
            $datadel .= html_writer::empty_tag('input', $delcheckboxattr);
            $datarow[] = $datadel;

            $table->data[] = $datarow;
        }

        $formcontents .= html_writer::tag(
            'div',
            html_writer::table($table),
            array('class' => 'no-overflow')
        );

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'itemkeys',
            'value' => implode(',', array_keys($items)),
        ));

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'wc_keys',
            'value' => implode(',', array_keys($wildcards)),
        ));

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

        $buttoncontents = '';
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_saveandadd',
            'value' => get_string('saveandadd', 'local_dataseteditor'),
        ));
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'reset',
            'value' => get_string('reset', 'local_dataseteditor'),
        ));
        $buttoncontents .= html_writer::empty_tag('br');
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_save',
            'value' => get_string('save', 'local_dataseteditor'),
        ));
        $buttoncontents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_cancel',
            'value' => get_string('cancel', 'local_dataseteditor'),
        ));
        $formcontents .= html_writer::tag('p', $buttoncontents);

        return html_writer::tag('form', $formcontents, $formattributes);
    }


    /**
     * Renders category choice select form
     *
     * @param url $desturl Destination URL
     * @param array $contexts Contexts containing desired categories
     * @param string $current Currently-selected category
     * @return string html code
     *
     * @see display_category_form in question/editlib.php
     */
    public function render_category_form($desturl, $contexts, $current) {
        global $OUTPUT;

        $goodcontexts = array();

        foreach ($contexts as $c) {
            if (
                ($c->contextlevel == CONTEXT_COURSE) ||
                ($c->contextlevel == CONTEXT_MODULE)
            ) {
                $goodcontexts[] = $c;
            }
        }

        $ret = '<div class="choosecategory">';
        $options = question_category_options($goodcontexts, true, 0, true);

        $catmenu = array();
        foreach ($options as $op) {
            $contextgroup = array();

            foreach ($op as $contextstring => $group) {
                $categories = array();

                foreach ($group as $category => $catname) {
                    $parts = explode(',', $category);
                    $categoryid = $parts[0];
                    $categories[$categoryid] = $catname;
                }

                $contextgroup[$contextstring] = $categories;
            }

            $catmenu[] = $contextgroup;
        }

        $select = new single_select(
            $desturl,
            'topcategory',
            $catmenu,
            $current,
            null,
            'catmenu'
        );
        $select->set_label(get_string('selectacategory', 'question'));

        $ret .= $OUTPUT->render($select);
        $ret .= "</div>\n";

        return $ret;
    }


    /**
     * Renders category list page with links to edit wildcards and datasets
     *
     * @param array $context2cats[] = array(stdClass(->context ->categories=
     *      array(stdClass(->id ->name ->numquestions ->wildcards))
     * )
     * @param int $numvaluesets Number of value sets to show per category
     * @param url $wildcardurl URL for editing wildcards
     * @param url $valueurl URL for editing values
     * @param url $exporturl URL for exporting datasets
     * @param url $importurl URL for importing datasets
     * @return string html code
     */
    public function render_category_tables($contextcats, $numvaluesets,
        $wildcardurl, $valueurl, $exporturl, $importurl
    ) {

        $contents = '';

        foreach ($contextcats as $contextcat) {
            $context = $contextcat->context;
            $cats = $contextcat->categories;

            if (empty($cats)) {
                continue;
            }

            $table = new html_table();
            $table->head = array(
                get_string('name', 'local_dataseteditor'),
                get_string('editwildcards', 'local_dataseteditor'),
                get_string('editdataset', 'local_dataseteditor'),
                get_string('exportdataset', 'local_dataseteditor'),
                get_string('importdataset', 'local_dataseteditor'),
            );
            $table->data = array();

            foreach ($cats as $cat) {
                $wildcardnames = array();
                foreach ($cat->wildcards as $wc) {
                    $wildcardnames[] = '{' . $wc->name . '}';
                }
                $wildcardstr = implode(', ', $wildcardnames);

                $valuesets = array();
                for ($i = 0; $i < $numvaluesets; $i++) {
                    $valueset = array();
                    $anyvalues = false;
                    foreach ($cat->wildcards as $wc) {
                        if (isset($wc->values[$i])) {
                            $v = $wc->values[$i];
                            $anyvalues = true;
                        } else {
                            $v = '';
                        }
                        $valueset[] = $v;
                    }

                    if ($anyvalues) {
                        $valuesets[] = '(' . implode(',', $valueset) . ')';
                    }
                }

                $morevalues = false;
                foreach ($cat->wildcards as $wc) {
                    if (
                        isset($wc->values[$i]) ||
                        ($wc->num_more_values > 0)
                    ) {
                        $morevalues = true;
                        break;
                    }
                }

                if ($morevalues) {
                    $valuesets[] = '...';
                }

                $valuestr = implode(', ', $valuesets);

                if (empty($wildcardstr)) {
                    $wildcardstr = get_string('no_wildcards',
                        'local_dataseteditor');
                }
                if (empty($valuestr)) {
                    $valuestr = get_string('no_data',
                        'local_dataseteditor');
                }

                $wurl = new moodle_url($wildcardurl);
                $wurl->param('categoryid', $cat->id);
                $durl = new moodle_url($valueurl);
                $durl->param('categoryid', $cat->id);
                $eurl = new moodle_url($exporturl);
                $eurl->param('categoryid', $cat->id);
                $iurl = new moodle_url($importurl);
                $iurl->param('categoryid', $cat->id);

                $row = array();
                $row[] = $cat->name;
                $row[] = html_writer::link($wurl, $wildcardstr);
                $row[] = html_writer::link($durl, $valuestr);
                $row[] = html_writer::link($eurl,
                    get_string('exportdataset', 'local_dataseteditor'));
                $row[] = html_writer::link($iurl,
                    get_string('importdataset', 'local_dataseteditor'));

                $table->data[] = $row;
            }

            $contextcontents = $context->get_context_name(true);
            $contextcontents .= html_writer::empty_tag('br');
            $contextcontents .= html_writer::table($table);

            $contents .= html_writer::tag('p', $contextcontents);
        }

        return $contents;
    }


    /**
     * Renders dataset as tab-delimited text
     *
     * @param array $wildcards[id] = stdClass(->id ->name ->values)
     * @param array $items[itemnum] = array(defnum => stdClass(->id ->val))
     * @return string html code
     */
    public function render_dataset_text($wildcards, $items) {
        $contents = '';

        /* Include row of wildcard names. */
        $wildcardnames = array();
        foreach ($wildcards as $wc) {
            $wildcardnames[] = str_replace("\t", '', $wc->name);
        }
        $contents .= implode("\t", $wildcardnames) . "\n";

        /* Include each dataset item. */
        ksort($items);
        foreach ($items as $itemkey => $item) {
            $datarow = array();

            foreach ($wildcards as $wc) {
                if (isset($item[$wc->id])) {
                    $val = $item[$wc->id]->val;
                } else {
                    $val = '';
                }

                $datarow[] = $val;
            }

            $contents .= implode("\t", $datarow) . "\n";
        }

        return $contents;
    }


    /**
     * Renders dataset import file upload form
     *
     * @param url $formdest URL to which this form submits
     * @return string html code
     */
    public function render_dataset_upload_form($formdest) {
        $formattributes = array(
            'action' => $formdest->out(false),
            'method' => 'POST',
            'enctype' => 'multipart/form-data',
        );
        $formcontents = '';

        $formcontents .= get_string('import_from_spreadsheet',
            'local_dataseteditor');
        $formcontents .= html_writer::empty_tag('br');

        $formcontents .= html_writer::tag('label',
            get_string('lbl_filename', 'local_dataseteditor'),
            array('for' => 'file')
        );
        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'file',
            'name' => 'file',
            'id' => 'file',
        ));

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

        $formcontents .= html_writer::empty_tag('br');
        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'import',
            'value' => get_string('import', 'local_dataseteditor'),
        ));
        $formcontents = html_writer::tag('p', $formcontents);

        return html_writer::tag('form', $formcontents, $formattributes);
    }


    /**
     * Renders imported dataset for user confirmation
     *
     * @param array $wildcards[id] = name
     * @param array $items[itemnum] = array(defnum => val)
     * @param url $formdest URL to which this form submits
     * @param array $changelist List of changes to confirm
     * @return string html code
     */
    public function render_dataset_import_confirm($wildcards, $items,
        $formdest, $changelist
    ) {

        $formattributes = array(
            'action' => $formdest->out(false),
            'method' => 'POST'
        );
        $formcontents = '';

        $table = new html_table();
        $table->attributes['class'] = 'flexible generaltable';
        $table->head = array(
            get_string('itemnum', 'local_dataseteditor'),
        );
        $table->data = array();

        $unsortedwildcards = array();
        foreach ($wildcards as $name) {
            $unsortedwildcards[] = $name;
        }

        asort($wildcards);

        foreach ($wildcards as $wcid => $wcname) {
            $table->head[] = '{' . $wcname . '}';
        }

        /* Add fields for each dataset item. */
        ksort($items);
        $havealldata = true;
        foreach ($items as $itemkey => $item) {

            $datarow = array();

            foreach ($wildcards as $wcid => $wcname) {
                $suffix = '_i' . $itemkey . '_w' . $wcid;

                if (isset($item[$wcid])) {
                    $val = $item[$wcid];

                    $dataval = $val;

                } else {
                    $dataval = get_string('no_data', 'local_dataseteditor');
                    $val = 'NULL';
                    $havealldata = false;
                }

                $dataval .= html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'val' . $suffix,
                    'value' => $val,
                ));

                $datarow[] = $dataval;
            }

            /* Add row label. */
            $rowlabel = $itemkey + 1;
            array_unshift($datarow, $rowlabel);

            $table->data[] = $datarow;
        }

        $formcontents .= html_writer::tag(
            'div',
            html_writer::table($table),
            array('class' => 'no-overflow')
        );

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'itemcount',
            'value' => count($items),
        ));

        $wildcardnum = 0;
        foreach ($unsortedwildcards as $name) {
            $suffix = '_w' . $wildcardnum;

            $formcontents .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_name' . $suffix,
                'value' => $name,
            ));

            $wildcardnum++;
        }

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'wildcardcount',
            'value' => $wildcardnum,
        ));

        $formcontents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

        if ($havealldata) {
            if (! empty($changelist)) {
                $formcontents .= get_string('changes_to_commit',
                    'local_dataseteditor');
                $ulcontents = '';
                foreach ($changelist as $change) {
                    $ulcontents .= html_writer::tag('li', $change);
                }
                $formcontents .= html_writer::tag('ul', $ulcontents);
            }

            $buttoncontents = '';
            $buttoncontents .= get_string('save_overwrite_p',
                'local_dataseteditor');
            $buttoncontents .= html_writer::empty_tag('br');
            $buttoncontents .= html_writer::empty_tag('input', array(
                'type' => 'submit',
                'name' => 'submit_overwrite',
                'value' => get_string('save',
                'local_dataseteditor'),
            ));
            $buttoncontents .= html_writer::empty_tag('input', array(
                'type' => 'submit',
                'name' => 'submit_cancel',
                'value' => get_string('cancel', 'local_dataseteditor'),
            ));
            $formcontents .= html_writer::tag('p', $buttoncontents);
        }

        return html_writer::tag('form', $formcontents, $formattributes);
    }

}
