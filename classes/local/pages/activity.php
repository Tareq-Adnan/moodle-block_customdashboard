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

namespace block_customdashboard\local\pages;

use block_customdashboard\constants;
use block_customdashboard\output\renderer;
use completion_info;
use context_system;
use moodle_url;

/**
 * Class activities
 *
 * @package    block_customdashboard
 * @copyright  2026 Brain Station 23 <sales@brainstation-23.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity {

    private $type;
    public function __construct($type) {
        $this->type = $type;
    }

    public function render() {
        global $OUTPUT, $PAGE, $USER;

        $url = new moodle_url('/blocks/customdashboard/activities.php', []);
        $PAGE->set_url($url);
        $PAGE->set_context(context_system::instance());

        $PAGE->set_title(constants::HEADINGS[$this->type]);
        $PAGE->set_pagelayout('mydashboard');
        $PAGE->set_heading(constants::HEADINGS[$this->type]);

        echo $OUTPUT->header();
        $data = $this->fetch_requested_data($this->type);
        // echo "<pre>";
        // var_dump($data);die;
        echo $OUTPUT->render_from_template(constants::TEMPLATES[$this->type], ['activities' => $data]);
        $baseurl = new moodle_url('/blocks/customdashboard/activities.php', ['type' => $this->type]);

        echo $OUTPUT->paging_bar($data['total'], $data['page'], $data['perpage'], $baseurl);
        echo $OUTPUT->footer();
    }

    public function fetch_requested_data($type) {
        global $USER;
        $page = optional_param('page', 0, PARAM_INT);
        $perpage = 9;

        switch($type) {
            case constants::CUDB_MODULE_TYPE_NOTICE : 
                return $this->get_announcements($USER->id, $page, $perpage);
            case constants::CUDB_MODULE_TYPE_COMMUNICATION:
                return renderer::get_student_teachers($USER->id);
            case constants::CUDB_MODULE_TYPE_LIVECLASS:
                return renderer::get_zoom_classes($USER->id, 'student');
            case constants::CUDB_MODULE_TYPE_HOMEWORK:
            case constants::CUDB_MODULE_TYPE_ASSESSMENT:
            case constants::CUDB_MODULE_TYPE_ACTIVITY:
                return $this->get_user_activities($USER->id);
            default:
                return [];
        }
    }

    public function get_user_activities(int $userid): array {
        global $DB;

        $courses = enrol_get_users_courses($userid, true, '*');

        $needattention = [];
        $upcoming = [];
        $completed = [];

        $now = time();
        $onehour = 3600;
        $oneday = 86400;

        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo($course, $userid);
            $completion = new completion_info($course);

            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }

                // Get custom type
                $custom = $DB->get_record('block_cudb_module_type', [
                    'courseid' => $course->id,
                    'cmid' => $cm->id,
                    'type' => $this->type
                ]);

                if (!$custom) {
                    continue;
                }

                // Get instance safely
                $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', IGNORE_MISSING);
                if (!$instance) {
                    continue;
                }

                // Detect due date
                $duedate = 0;
                if (!empty($instance->duedate)) {
                    $duedate = $instance->duedate;
                } elseif (!empty($instance->deadline)) {
                    $duedate = $instance->deadline;
                } elseif (!empty($instance->timeclose)) {
                    $duedate = $instance->timeclose;
                }
                
                $enddate = userdate(intval($duedate), '%b %e', 99);
                // Completion
                $iscompleted = false;

                if ($completion->is_enabled($cm)) {
                    $cdata = $completion->get_data($cm, false, $userid);

                    if (!empty($cdata->completionstate)) {
                        $iscompleted = in_array($cdata->completionstate, [
                            COMPLETION_COMPLETE,
                            COMPLETION_COMPLETE_PASS,
                            COMPLETION_COMPLETE_FAIL
                        ]);
                    }
                }

                // URLs
                $activityurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
                $iconurl = $cm->get_icon_url()->out(false);

                $data = [
                    'courseid' => $course->id,
                    'coursetitle' => format_string($course->fullname),
                    'cmid' => $cm->id,
                    'activitytitle' => format_string($cm->name),
                    'iconurl' => $iconurl,
                    'activityurl' => $activityurl->out(false),
                    'duedate' => $enddate,
                    'type' => $custom->type,
                ];

                // Categorization
                if ($iscompleted) {
                    $completed[] = $data;
                    continue;
                }

                if ($duedate > 0) {
                    if ($duedate <= $now || ($duedate - $now) <= $onehour) {
                        $this->get_activity_status($data, $iscompleted, $duedate);
                        $needattention[] = $data;
                    } elseif (($duedate - $now) >= $oneday) {
                        $this->get_activity_status($data, $iscompleted, $duedate);
                        $upcoming[] = $data;
                    } else {
                        $this->get_activity_status($data, $iscompleted, $duedate);
                        $needattention[] = $data;
                    }
                } else {
                    $this->get_activity_status($data, $iscompleted, $duedate);
                    $upcoming[] = $data;
                }
            }
        }

        // Sort by due date
        $sortfn = function($a, $b) {
            return ($a['duedate'] ?? 0) <=> ($b['duedate'] ?? 0);
        };

        usort($needattention, $sortfn);
        usort($upcoming, $sortfn);
        usort($completed, $sortfn);

        return [
            'needattention' => array_values($needattention),
            'upcoming' => array_values($upcoming),
            'completed' => array_values($completed),
            'has_needattention' => !empty($needattention),
            'has_upcoming' => !empty($upcoming),
            'has_completed' => !empty($completed),
        ];
    }

    private function get_activity_status(&$data, $iscompleted, $duedate) {
        $now = time();

        if ($iscompleted) {
            $data['badgeclass'] = 'done';
            $data['badgemsg'] = 'Completed';

        } else if ($duedate > 0) {

            $diff = $duedate - $now;

            if ($duedate <= $now) {
                $data['badgeclass'] = 'overdue';
                $data['badgemsg'] = 'Overdue';

            } elseif ($diff <= 86400) { // within today (24h)
                $data['badgeclass'] = 'today';
                $data['badgemsg'] = 'Today';

            } else {
                $days = ceil($diff / 86400);

                $data['badgeclass'] = 'todo';
                $data['badgemsg'] = $days . ' days left';
            }

        } else {
            // No due date → treat as todo
            $data['badgeclass'] = 'todo';
            $data['badgemsg'] = 'No deadline';
        }
    }

    private function get_announcements($userid, $page = 0, $perpage = 9) {
        global $DB;
        $courses = enrol_get_users_courses($userid, true, ['id', 'fullname']);
        $announcementsdata = [];
        $offset = $page * $perpage;

        if (!empty($courses)) {
            global $DB;

            $courseids = array_keys($courses);

            // Prepare IN clause safely
            list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

            $sql = "SELECT
                        fd.id AS discussionid, -- ✅ unique first column (important)
                        f.id AS forumid,
                        f.name AS forumname,
                        fd.name AS discussionname,
                        c.id AS courseid,
                        c.fullname AS coursename
                    FROM {forum} f
                    JOIN {forum_discussions} fd ON fd.forum = f.id
                    JOIN {course} c ON c.id = f.course
                    WHERE f.type = :forumtype
                    AND c.id $insql
                    AND fd.name IS NOT NULL
                    AND fd.name <> ''
                    ORDER BY fd.timemodified DESC";

            $params['forumtype'] = 'news';

            $records = $DB->get_recordset_sql($sql, $params, $offset, $perpage);

            foreach ($records as $rec) {
                $announcementsdata[] = [
                    'course' => format_string($rec->coursename),
                    'forum' => format_string($rec->forumname),
                    'discussion' => format_string($rec->discussionname),
                    'url' => (new moodle_url('/mod/forum/discuss.php', [
                        'd' => $rec->discussionid
                    ]))->out(false)
                ];
            }

            $countsql = "SELECT COUNT(fd.id)
                        FROM {forum} f
                        JOIN {forum_discussions} fd ON fd.forum = f.id
                        JOIN {course} c ON c.id = f.course
                        WHERE f.type = :forumtype
                        AND c.id $insql
                        AND fd.name IS NOT NULL
                        AND fd.name <> ''";

            $total = $DB->count_records_sql($countsql, $params);

            return [
                'notices' => $announcementsdata,
                'total' => $total,
                'page' => $page,
                'perpage' => $perpage
            ];
        }
        return [];
    }
}
