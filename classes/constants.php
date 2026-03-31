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

/**
 * Class constants
 *
 * @package    block_customdashboard
 * @copyright  2026 Brain Station 23 <sales@brainstation-23.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    public const CUDB_MODULE_TYPE_REGULAR = 0;
    public const CUDB_MODULE_TYPE_HOMEWORK = 1;
    public const CUDB_MODULE_TYPE_ASSESSMENT = 2;
    public const CUDB_MODULE_TYPE_LIVECLASS = 3;
    public const CUDB_MODULE_TYPE_ACTIVITY = 4;
    public const CUDB_MODULE_TYPE_NOTICE = 5;
    public const CUDB_MODULE_TYPE_COMMUNICATION = 6;
    
    // Headings array
    public const HEADINGS = [
        self::CUDB_MODULE_TYPE_REGULAR       => "Activities",
        self::CUDB_MODULE_TYPE_HOMEWORK      => "Homeworks",
        self::CUDB_MODULE_TYPE_ASSESSMENT    => "Assessments",
        self::CUDB_MODULE_TYPE_LIVECLASS     => "Live Classes",
        self::CUDB_MODULE_TYPE_ACTIVITY      => "Course Activity",
        self::CUDB_MODULE_TYPE_NOTICE        => "Notice Board",
        self::CUDB_MODULE_TYPE_COMMUNICATION => "Communications and Support",
    ];

    public const TEMPLATES = [
        self::CUDB_MODULE_TYPE_NOTICE          => 'block_customdashboard/noticeboard',
        self::CUDB_MODULE_TYPE_REGULAR         => 'block_customdashboard/homework',
        self::CUDB_MODULE_TYPE_HOMEWORK        => 'block_customdashboard/homework',
        self::CUDB_MODULE_TYPE_ASSESSMENT      => 'block_customdashboard/homework',
        self::CUDB_MODULE_TYPE_LIVECLASS       => 'block_customdashboard/zoom-classes-card',
        self::CUDB_MODULE_TYPE_ACTIVITY        => 'block_customdashboard/homework',
        self::CUDB_MODULE_TYPE_COMMUNICATION   => 'block_customdashboard/teachers-card',
    ];
}
