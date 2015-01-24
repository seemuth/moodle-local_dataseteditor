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
 * Version details.
 *
 * @package    local
 * @subpackage dataseteditor
 * @copyright  2013-2015 Daniel Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$capabilities = array(
    'local/dataseteditor:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'moodle/question:managecategory',
    ),
    'local/dataseteditor:edit' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask' => RISK_DATALOSS,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'moodle/question:managecategory',
    ),
    'local/dataseteditor:export' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'moodle/question:managecategory',
    ),
    'local/dataseteditor:import' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask' => RISK_DATALOSS,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'moodle/question:managecategory',
    ),
);
