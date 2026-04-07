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

namespace block_customdashboard;
use context_course;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Class externallib
 *
 * @package    block_customdashboard
 * @copyright  2026 Brain Station 23 <sales@brainstation-23.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class externallib extends external_api {

    public static function save_schedule_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'content' => new external_value(PARAM_RAW, 'Schedule data in JSON format'),
            ]
        );
    }

    public static function save_schedule($courseid, $content) {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::save_schedule_parameters(), ['courseid' => $courseid, 'content' => $content]);

        // Check capability.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        if (!has_capability('block/customdashboard:save_schedule', $context)) {
            throw new \moodle_exception('nopermission', 'error');
        }

        // Save the schedule data to the database.
        $record = $DB->get_record('block_cudb_class_schedule', ['courseid' => $params['courseid']]);
        if ($record) {
            $record->content = $params['content'];
            $record->timemodified = time();
            $DB->update_record('block_cudb_class_schedule', $record);
        } else {
            $record = new \stdClass();
            $record->courseid = $params['courseid'];
            $record->content = $params['content'];
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('block_cudb_class_schedule', $record);
        }

        return ['status' => true, 'message' => 'Schedule saved successfully'];
    }

    public static function save_schedule_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }

    public static function get_schedule_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
            ]
        );
    }

    public static function get_schedule($courseid) {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::get_schedule_parameters(), ['courseid' => $courseid]);

        // Check capability.
        $context = context_system::instance();
        self::validate_context($context);
        if (!has_capability('block/customdashboard:myaddinstance', $context)) {
            throw new \moodle_exception('nopermission', 'error');
        }

        // Get the schedule data from the database.
        $record = $DB->get_record('block_cudb_class_schedule', ['courseid' => $params['courseid']]);

        return ['status' => true, 'content' => $record->content ?? ''];
    }

    public static function get_schedule_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Success status'),
            'content' => new external_value(PARAM_TEXT, 'Schedule content in JSON format'),
        ]);
    }
}
