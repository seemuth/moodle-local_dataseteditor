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
 * @copyright 2013 Daniel Seemuth
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
     * @param int $min_rows Minimum number of wildcard rows to show
     * @param url $form_dest URL to which this form submits
     * @return string html code
     */
    public function render_wildcard_form($wildcards, $uservals,
        $min_rows, $form_dest
    ) {
        global $CFG;

        $form_attributes = array(
            'action' => $form_dest->out(false),
            'method' => 'POST'
        );
        $form_contents = '';

        $table = new html_table();
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
         *      $val_override[id] = stdClass
         *      $new_vals[] = stdClass
         */

        $val_override = array();
        $new_vals = array();
        foreach ($uservals as $uv) {
            if ($uv->id > 0) {
                $val_override[$uv->id] = $uv;
            } else {
                $new_vals[] = $uv;
            }
        }

        $override_fields = array('name', 'del', 'orig');

        /* Set defaults for each wildcard, then override fields as needed.
         */
        foreach ($wildcards as $wc) {
            $wc->orig = $wc->name;
            $wc->del = 0;

            if (isset($val_override[$wc->id])) {
                foreach ($override_fields as $field) {
                    $wc->$field = $val_override[$wc->id]->$field;
                }
            }
        }

        /* Make sure we have the minimum number of wildcard fields. */
        $need = $min_rows - count($wildcards);
        for ($i = 0; $i < $need; $i++) {
            $wc = new stdClass();
            $wc->id = 0;
            $wc->name = '';
            $wc->orig = '';
            $wc->del = 0;
            $wc->values = array();

            if (isset($new_vals[$i])) {
                foreach ($override_fields as $field) {
                    $wc->$field = $new_vals[$i]->$field;
                }
            }

            $wildcards[] = $wc;
        }

        /* Add fields to edit each wildcard. */
        $i = 0;
        foreach ($wildcards as $wc) {
            $suffix = '_' . $i;

            $data_id = html_writer::empty_tag('input', array(
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
            $data_name .= $data_id;
            $data_name .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_orig' . $suffix,
                'value' => $wc->orig,
            ));

            $data_values = implode(', ', $wc->values);

            $data_del = html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_del' . $suffix,
                'value' => '',
            ));
            $del_checkbox_attr = array(
                'type' => 'checkbox',
                'name' => 'wc_del' . $suffix,
                'value' => 'yes',
            );
            if ($wc->del) {
                $del_checkbox_attr['checked'] = 'checked';
            }
            $data_del .= html_writer::empty_tag('input', $del_checkbox_attr);

            $data_row = array($data_name, $data_values, $data_del);
            if ($CFG->localdataseteditordebug) {
                array_unshift($data_row, $wc->id);
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

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

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


    /**
     * Renders dataset edit form
     *
     * @param array $wildcards[id] = stdClass(->id ->name ->values)
     * @param array $items[itemnum] = array(defnum => stdClass(->id ->val))
     * @param array $uservals[itemnum] = array(defnum => stdClass(->val))
     * @param array $deleteitems[itemnum] = (don't care)
     * @param int $min_rows Minimum number of item rows to show
     * @param url $form_dest URL to which this form submits
     * @return string html code
     */
    public function render_dataset_form($wildcards, $items,
        $uservals, $deleteitems, $min_rows, $form_dest
    ) {
        global $CFG;

        $form_attributes = array(
            'action' => $form_dest->out(false),
            'method' => 'POST'
        );
        $form_contents = '';

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
        $need = $min_rows - count($itemkeys);

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

            $any_data = false;  /* True if any data is currently defined */

            $data_row = array();

            foreach ($wildcards as $wc) {
                $suffix = '_i' . $itemkey . '_w' . $wc->id;

                if (isset($item[$wc->id])) {
                    $any_data = true;

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

                $data_id = html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'data_id'. $suffix,
                    'value' => $id,
                ));

                if ($CFG->localdataseteditordebug) {
                    $data_id .= $id . ' ';
                }

                $data_val = html_writer::empty_tag('input', array(
                    'type' => 'text',
                    'name' => 'data_val' . $suffix,
                    'value' => $val,
                ));
                $data_val .= html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'data_orig' . $suffix,
                    'value' => $orig,
                ));

                $data_row[] = $data_id . $data_val;
            }

            /* Add row label, including annotation for new data. */
            $rowlabel = $itemkey;
            if (! $any_data) {
                $rowlabel .= ' ' . get_string('paren_newdata',
                    'local_dataseteditor');
            }
            array_unshift($data_row, $rowlabel);

            $data_del = html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'data_del_i' . $itemkey,
                'value' => '',
            ));
            $del_checkbox_attr = array(
                'type' => 'checkbox',
                'name' => 'data_del_i' . $itemkey,
                'value' => 'yes',
            );
            if (isset($deleteitems[$itemkey])) {
                $del_checkbox_attr['checked'] = 'checked';
            }
            $data_del .= html_writer::empty_tag('input', $del_checkbox_attr);
            $data_row[] = $data_del;

            $table->data[] = $data_row;
        }

        $form_contents .= html_writer::tag(
            'div',
            html_writer::table($table),
            array('class' => 'no-overflow')
        );

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'itemkeys',
            'value' => implode(',', array_keys($items)),
        ));

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'wc_keys',
            'value' => implode(',', array_keys($wildcards)),
        ));

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

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


    /**
     * Renders category list page with links to edit wildcards and datasets
     *
     * @param array $context2cats[] = array(stdClass(->context ->categories=
     *      array(stdClass(->id ->name ->numquestions ->wildcards))
     * )
     * @param int $num_valuesets Number of value sets to show per category
     * @param url $wildcard_url URL for editing wildcards
     * @param url $value_url URL for editing values
     * @param url $export_url URL for exporting datasets
     * @param url $import_url URL for importing datasets
     * @return string html code
     */
    public function render_category_tables($context_cats, $num_valuesets,
        $wildcard_url, $value_url, $export_url, $import_url
    ) {

        $contents = '';

        foreach ($context_cats as $context_cat) {
            $context = $context_cat->context;
            $cats = $context_cat->categories;

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
                $wildcard_names = array();
                foreach ($cat->wildcards as $wc) {
                    $wildcard_names[] = '{' . $wc->name . '}';
                }
                $wildcardstr = implode(', ', $wildcard_names);

                $valuesets = array();
                for ($i = 0; $i < $num_valuesets; $i++) {
                    $valueset = array();
                    $any_values = false;
                    foreach ($cat->wildcards as $wc) {
                        if (isset($wc->values[$i])) {
                            $v = $wc->values[$i];
                            $any_values = true;
                        } else {
                            $v = '';
                        }
                        $valueset[] = $v;
                    }

                    if ($any_values) {
                        $valuesets[] = '(' . implode(',', $valueset) . ')';
                    }
                }

                $more_values = false;
                foreach ($cat->wildcards as $wc) {
                    if (
                        isset($wc->values[$i]) ||
                        ($wc->num_more_values > 0)
                    ) {
                        $more_values = true;
                        break;
                    }
                }

                if ($more_values) {
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

                $w_url = new moodle_url($wildcard_url);
                $w_url->param('categoryid', $cat->id);
                $d_url = new moodle_url($value_url);
                $d_url->param('categoryid', $cat->id);
                $e_url = new moodle_url($export_url);
                $e_url->param('categoryid', $cat->id);
                $i_url = new moodle_url($import_url);
                $i_url->param('categoryid', $cat->id);

                $row = array();
                $row[] = $cat->name;
                $row[] = html_writer::link($w_url, $wildcardstr);
                $row[] = html_writer::link($d_url, $valuestr);
                $row[] = html_writer::link($e_url,
                    get_string('exportdataset', 'local_dataseteditor'));
                $row[] = html_writer::link($i_url,
                    get_string('importdataset', 'local_dataseteditor'));

                $table->data[] = $row;
            }

            $context_contents = $context->get_context_name(true);
            $context_contents .= html_writer::empty_tag('br');
            $context_contents .= html_writer::table($table);

            $contents .= html_writer::tag('p', $context_contents);
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
        $wildcard_names = array();
        foreach ($wildcards as $wc) {
            $wildcard_names[] = str_replace("\t", '', $wc->name);
        }
        $contents .= implode("\t", $wildcard_names) . "\n";

        /* Include each dataset item. */
        ksort($items);
        foreach ($items as $itemkey => $item) {
            $data_row = array();

            foreach ($wildcards as $wc) {
                if (isset($item[$wc->id])) {
                    $val = $item[$wc->id]->val;
                } else {
                    $val = '';
                }

                $data_row[] = $val;
            }

            $contents .= implode("\t", $data_row) . "\n";
        }

        return $contents;
    }


    /**
     * Renders dataset import file upload form
     *
     * @param url $form_dest URL to which this form submits
     * @return string html code
     */
    public function render_dataset_upload_form($form_dest) {
        $form_attributes = array(
            'action' => $form_dest->out(false),
            'method' => 'POST',
            'enctype' => 'multipart/form-data',
        );
        $form_contents = '';

        $form_contents .= get_string('import_from_spreadsheet',
            'local_dataseteditor');
        $form_contents .= html_writer::empty_tag('br');

        $form_contents .= html_writer::tag('label',
            get_string('lbl_filename', 'local_dataseteditor'),
            array('for' => 'file')
        );
        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'file',
            'name' => 'file',
            'id' => 'file',
        ));

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

        $form_contents .= html_writer::empty_tag('br');
        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'import',
            'value' => get_string('import', 'local_dataseteditor'),
        ));
        $form_contents = html_writer::tag('p', $form_contents);

        return html_writer::tag('form', $form_contents, $form_attributes);
    }


    /**
     * Renders imported dataset for user confirmation
     *
     * @param array $wildcards[id] = name
     * @param array $items[itemnum] = array(defnum => val)
     * @param url $form_dest URL to which this form submits
     * @param array $changelist List of changes to confirm
     * @return string html code
     */
    public function render_dataset_import_confirm($wildcards, $items,
        $form_dest, $changelist
    ) {

        $form_attributes = array(
            'action' => $form_dest->out(false),
            'method' => 'POST'
        );
        $form_contents = '';

        $table = new html_table();
        $table->head = array(
            get_string('itemnum', 'local_dataseteditor'),
        );
        $table->data = array();

        $unsorted_wildcards = array();
        foreach ($wildcards as $name) {
            $unsorted_wildcards[] = $name;
        }

        asort($wildcards);

        foreach ($wildcards as $wc_id => $wc_name) {
            $table->head[] = '{' . $wc_name . '}';
        }

        /* Add fields for each dataset item. */
        ksort($items);
        foreach ($items as $itemkey => $item) {

            $data_row = array();

            foreach ($wildcards as $wc_id => $wc_name) {
                $suffix = '_i' . $itemkey . '_w' . $wc_id;

                if (isset($item[$wc_id])) {
                    $val = $item[$wc_id];

                    $data_val = $val;

                } else {
                    $data_val = get_string('no_data', 'local_dataseteditor');
                    $val = 'NULL';
                }

                $data_val .= html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'val' . $suffix,
                    'value' => $val,
                ));

                $data_row[] = $data_val;
            }

            /* Add row label. */
            $rowlabel = $itemkey + 1;
            array_unshift($data_row, $rowlabel);

            $table->data[] = $data_row;
        }

        $form_contents .= html_writer::table($table);

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'itemcount',
            'value' => count($items),
        ));

        $wildcardnum = 0;
        foreach ($unsorted_wildcards as $name) {
            $suffix = '_w' . $wildcardnum;

            $form_contents .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'wc_name' . $suffix,
                'value' => $name,
            ));

            $wildcardnum++;
        }

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'wildcardcount',
            'value' => $wildcardnum,
        ));

        $form_contents .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ));

        if (! empty($changelist)) {
            $form_contents .= get_string('changes_to_commit',
                'local_dataseteditor');
            $ul_contents = '';
            foreach ($changelist as $change) {
                $ul_contents .= html_writer::tag('li', $change);
            }
            $form_contents .= html_writer::tag('ul', $ul_contents);
        }

        $button_contents = '';
        $button_contents .= get_string('save_overwrite_p',
            'local_dataseteditor');
        $button_contents .= html_writer::empty_tag('br');
        $button_contents .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'name' => 'submit_overwrite',
            'value' => get_string('save',
                'local_dataseteditor'),
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
