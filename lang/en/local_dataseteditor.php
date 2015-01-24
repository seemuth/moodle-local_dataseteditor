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
 * Language strings
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  2013-2015 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['pluginname'] = 'Dataset editor';
$string['setting'] = 'Dataset editor';
$string['view'] = 'View';
$string['name'] = 'Name';
$string['curvals'] = 'Current values';
$string['delete_p'] = 'Delete';
$string['id'] = 'ID';
$string['itemnum'] = 'Item number';
$string['viewcategories'] = 'View categories';
$string['coursecategories'] = 'Course categories';

/* Define page titles. */
$string['index'] = 'Index';
$string['editwildcards'] = 'Edit wildcards';
$string['editdataset'] = 'Edit dataset';
$string['exportdataset'] = 'Export dataset';
$string['importdataset'] = 'Import dataset';

/* Define form strings. */
$string['wildcardX'] = 'Wildcard {$a}';
$string['save'] = 'Save changes';
$string['saveandadd'] = 'Save changes and add rows';
$string['cancel'] = 'Cancel changes';
$string['cancelled'] = 'Cancelled changes';
$string['reset'] = 'Reset form';
$string['paren_newdata'] = '(new data)';
$string['saved_wildcards'] = 'Saved wildcards';
$string['saved_dataset_items'] = 'Saved dataset items';
$string['saved_all_data'] = 'Saved all data';
$string['no_wildcards'] = 'No wildcards';
$string['no_data'] = 'No data';
$string['empty_wildcard'] = 'Empty wildcard';
$string['dup_wildcard_X'] = 'Duplicate wildcard: {$a}';
$string['lbl_filename'] = 'Filename:';
$string['import'] = 'Import';
$string['import_from_spreadsheet'] = 'Import from tab-delimited spreadsheet.';
$string['error_upload_X'] = 'Error uploading file: {$a}';
$string['no_file'] = 'No file chosen';
$string['changes_to_commit'] = 'Changes to commit:';
$string['save_overwrite_p'] = 'Save and overwrite existing data? Warning: this will replace all existing data in this category!';
$string['missing_data_X_in_X'] = 'Missing data {$a->name} in #{$a->num}';
$string['missing_data_in_X'] = 'Missing data in #{$a}';
$string['add_wildcardX'] = 'Add wildcard {$a}';
$string['delete_wildcardX'] = 'Delete wildcard {$a}';
$string['update_all_data'] = 'Update all data';
$string['cannot_save_dataset_asis'] = 'Cannot save dataset as-is. No changes were saved.';

/* Define permission strings. */
$string['dataseteditor:view'] = 'View datasets';
$string['dataseteditor:edit'] = 'Edit datasets';
$string['dataseteditor:export'] = 'Export datasets';
$string['dataseteditor:import'] = 'Import datasets';

/* Define thrown error strings. */
$string['unexpectedcontext'] = 'Unexpected context';
$string['catnotexist'] = 'The desired category no longer exists';
