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
use block_customdashboard\constants;
/**
 * Callback implementations for Custom Dashboard
 *
 * @package    block_customdashboard
 * @copyright  2026 Brain Station 23 <sales@brainstation-23.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Callback to add Cursive settings to a course module form.
 *
 * This function adds a Cursive configuration section to supported module forms,
 * allowing users to enable/disable Cursive functionality for that specific module instance.
 *
 * @param moodleform $formwrapper The form wrapper containing the module form
 * @param MoodleQuickForm $mform The actual form object to add elements to
 * @return void
 */
function block_customdashboard_coursemodule_standard_elements($formwrapper, $mform) {
    global $PAGE, $DB;

    $options = [
        constants::CUDB_MODULE_TYPE_REGULAR    => 'Regular',
        constants::CUDB_MODULE_TYPE_HOMEWORK   => 'Homework',
        constants::CUDB_MODULE_TYPE_ASSESSMENT => 'Assessment',
        constants::CUDB_MODULE_TYPE_LIVECLASS  => 'Liveclass',
        constants::CUDB_MODULE_TYPE_ACTIVITY   => 'Activity',
    ];

    $select = $mform->createElement('select', 'activity_type', "Activity Type", $options);
    $mform->setType('activity_type', PARAM_INT);
    $mform->insertElementBefore($select, 'introeditor');

    $courseid  = $formwrapper->get_current()->course;
    $instance  = $formwrapper->get_current()->coursemodule;

    $selected  = $DB->get_record('block_cudb_module_type', ['courseid' => $courseid, 'cmid' => $instance], 'type', IGNORE_MISSING);
    if ($selected) {
        $mform->setdefault('activity_type', $selected->type);
    }
}

/**
 * Handles post-actions for course module editing, specifically for Cursive settings.
 *
 * This function is called after a course module form is submitted. It saves the Cursive
 * state configuration for supported modules.
 *
 * @param stdClass $formdata The form data containing module settings
 * @param stdClass $course The course object
 * @return stdClass The modified form data
 */
function block_customdashboard_coursemodule_edit_post_actions($formdata, $course) {
    global $DB;

    $courseid  = $formdata->course;
    $instance  = $formdata->coursemodule;
    $type      = $formdata->activity_type;
    if ($type) {
        $DB->insert_record('block_cudb_module_type', ['courseid' => $courseid, 'cmid' => $instance, 'type' => $type]);
    }
    return $formdata;
}
