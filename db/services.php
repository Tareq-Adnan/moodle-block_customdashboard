<?php

use block_customdashboard\externallib;
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
 * External functions and service declaration for Custom Dashboard
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    block_customdashboard
 * @category   webservice
 * @copyright  2026 Brain Station 23 <sales@brainstation-23.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
        'block_cudb_save_schedule' => [
        'classname'    => externallib::class,
        'methodname'   => 'save_schedule',
        'description'  => 'Save Course class Schedule',
        'type'         => 'write',
        'capabilities' => 'block/customdashboard:myaddinstance',
        'services'     => ['moodle_mobile_app'],
        'ajax'         => true,
    ],
    'block_cudb_get_schedule' => [
        'classname'    => externallib::class,
        'methodname'   => 'get_schedule',
        'description'  => 'Get Course class Schedule',
        'type'         => 'read',
        'capabilities' => 'block/customdashboard:myaddinstance',
        'services'     => ['moodle_mobile_app'],
        'ajax'         => true,
    ],
];