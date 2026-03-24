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
 * Renderer for Custom Dashboard block.
 *
 * @package    block_customdashboard
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_customdashboard\output;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use completion_info;
use grade_grade;
use grade_item;

/**
 * Renderer class for Custom Dashboard block.
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the parent dashboard content.
     *
     * @param array $children Array of child users
     * @param int $selectedchildid Selected child ID
     * @return string HTML content
     */
    public function render_parent_dashboard($children, $selectedchildid) {
        global $DB, $PAGE;

        // Prepare children for selector.
        $childrenoptions = [];
        foreach ($children as $child) {
            $childrenoptions[] = [
                'id' => $child->id,
                'fullname' => fullname($child),
                'email' => $child->email,
                'selected' => ($child->id == $selectedchildid),
            ];
        }

        // Get courses for selected child.
        $courses = $this->get_child_courses($selectedchildid);

        $data = [
            'children' => $childrenoptions,
            'haschildren' => !empty($childrenoptions),
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'isparent' => true,
            'zoomclasses' => $this->get_zoom_classes($selectedchildid, 'student'),
            'teachers' => $this->get_student_teachers($selectedchildid),
            'buttonlabel' => 'join',
            'canclickzoom' => false,
        ];

        // Initialize JavaScript module.
        $PAGE->requires->js_call_amd('block_customdashboard/selector', 'init');
        $PAGE->requires->js_call_amd('block_customdashboard/modals', 'init');

        return $this->render_from_template('block_customdashboard/dashboard', $data);
    }

    /**
     * Render student dashboard.
     *
     * @param int $userid Student user ID
     * @return string HTML content
     */
    // public function render_student_dashboard($userid) {
    //     global $PAGE;
    //     // Get all courses the student is enrolled in
    //     $courses = enrol_get_users_courses($userid, true, ['id', 'fullname']);

    // // Prepare courses data for Mustache
    //     $courseitems = [];
    //     foreach ($courses as $course) {
    //         $courseitems[] = [
    //             'id' => $course->id,
    //             'fullname' => format_string($course->fullname),
    //         ];
    //     }

    //     $data = [
    //         'isstudent' => true,
    //         'zoomclasses' => $this->get_zoom_classes($userid, 'student'),
    //         'teachers' => $this->get_student_teachers($userid),
    //         'buttonlabel' => 'join',
    //         'canclickzoom' => true,
    //         'courses' => $courseitems,
    //         'hascourses' => !empty($courseitems),
    //     ];

    //     // Initialize JavaScript module.
    //     $PAGE->requires->js_call_amd('block_customdashboard/selector', 'init');

    //     return $this->render_from_template('block_customdashboard/dashboard', $data);
    // }

    public function render_student_dashboard($userid) {
        global $PAGE, $DB;
    
        // Step 1: Get all courses the student is enrolled in
        $courses = enrol_get_users_courses($userid, true, ['id', 'fullname']);
        //fetch announcmeents
        $announcementsdata = [];
        $courseids = array_keys($courses);
        // Step 2: Fetch announcement forums and discussions for these courses
        
        $sql = "
                SELECT 
                    f.id AS forumid,
                    f.name AS forumname,
                    fd.id AS discussionid,
                    fd.name AS discussionname,
                    c.id AS courseid,
                    c.fullname AS coursename
                FROM {forum} f
                JOIN {forum_discussions} fd ON fd.forum = f.id
                JOIN {course} c ON c.id = f.course
                WHERE f.type = 'news'
                  AND c.id IN (" . implode(',', array_map('intval', $courseids)) . ")
                  AND fd.name IS NOT NULL 
                  AND fd.name <> ''
                ORDER BY fd.timemodified DESC
            ";
            if (!empty($courseids)) {
                $records = $DB->get_records_sql($sql);
                
                foreach ($records as $rec) {
                    $announcementsdata[] = [
                        'course' => format_string($rec->coursename),
                        'forum' => format_string($rec->forumname),
                        'discussion' => format_string($rec->discussionname),
                        'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $rec->discussionid]))->out(false)
                    ];
                }
            } 
            

        //fetch course activities

        $courseactivities = [];
    
        foreach ($courses as $i => $course) { // <-- note $i for first course
            // Step 2: Get all course modules for this course
            $cms = $DB->get_records_sql("
                SELECT cm.id AS cmid, m.name AS modname, cm.instance
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                WHERE cm.course = :courseid AND cm.deletioninprogress = 0 AND m.name <> 'subsection'
                ORDER BY cm.id ASC
            ", ['courseid' => $course->id]);
    
            $activities = [];
    
            // Step 3: Loop through course modules to get their names and URLs
            foreach ($cms as $cm) {
                // Get the actual activity record to get its name
                $module = $DB->get_record($cm->modname, ['id' => $cm->instance], 'id, name');
    
                // Skip if module does not exist
                if (!$module) {
                    continue;
                }
    
                $activities[] = [
                    'id' => $cm->cmid,
                    'name' => format_string($module->name),
                    'modname' => $cm->modname,
                    'url' => new \moodle_url("/mod/{$cm->modname}/view.php", ['id' => $cm->cmid]),
                    'icon' => '', // optional later
                ];
            }
    
            $courseactivities[] = [
                'id' => $course->id,
                'fullname' => format_string($course->fullname),
                'activities' => $activities,
                'hasactivities' => !empty($activities),
                'first' => ($i === 0), // <-- mark the first course for Mustache
            ];
        }
    
        // Step 4: Prepare data for the Mustache template
        $data = [
            'isstudent' => true,
            'zoomclasses' => $this->get_zoom_classes($userid, 'student'),
            // 'teachers' => $this->get_student_teachers($userid),
            'buttonlabel' => 'join',
            'canclickzoom' => true,
            'courses' => $courseactivities,
            'hascourses' => !empty($courseactivities),
            'announcements' => $announcementsdata,
            'homework' => new moodle_url('/blocks/customdashboard/homework.php', []),
        ];
        
        // Step 5: Initialize JavaScript module
        $PAGE->requires->js_call_amd('block_customdashboard/selector', 'init');
    
        return $this->render_from_template('block_customdashboard/customui', $data);
    }

    public function get_course_activities_for_user($courseid, $userid) {
        global $DB;
    
        // Get course modules for the course.
        $modinfo = get_fast_modinfo($courseid, $userid);
        $cms = $modinfo->get_cms();
    
        $activities = [];
    
        foreach ($cms as $cm) {
            // Skip modules the user cannot see
            if (!$cm->uservisible) {
                continue;
            }
    
            // Get the activity record to get the name
            $module = $DB->get_record($cm->modname, ['id' => $cm->instance], 'id, name');
    
            $activities[] = [
                'id' => $cm->id,
                'name' => $module ? format_string($module->name) : $cm->modname,
                'modname' => $cm->modname,
                'url' => $cm->url ? $cm->url->out(false) : (string)new \moodle_url("/mod/{$cm->modname}/view.php", ['id' => $cm->id]),
            ];
        }
    
        return $activities;
    }

    /**
     * Render teacher dashboard.
     *
     * @param int $userid Teacher user ID
     * @return string HTML content
     */
    public function render_teacher_dashboard($userid) {
        global $PAGE;

        $data = [
            'isteacher' => true,
            'zoomclasses' => $this->get_zoom_classes($userid, 'teacher'),
            'buttonlabel' => 'startjoin',
            'canclickzoom' => true,
        ];

        // Initialize JavaScript module.
        $PAGE->requires->js_call_amd('block_customdashboard/selector', 'init');

        return $this->render_from_template('block_customdashboard/dashboard', $data);
    }

    /**
     * Get courses with progress, grades, and activity completion for a child.
     *
     * @param int $childid Child user ID
     * @return array Array of course data
     */
    private function get_child_courses($childid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $courses = enrol_get_users_courses($childid, true, ['id', 'fullname', 'shortname', 'visible', 'enablecompletion']);

        $coursedata = [];

        foreach ($courses as $course) {
            if (!$course->visible) {
                continue;
            }

            $coursecontext = \context_course::instance($course->id);
            $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);

            // Get course category.
            $coursecategory = '';
            if ($course->category) {
                $category = $DB->get_record('course_categories', ['id' => $course->category]);
                if ($category) {
                    $coursecategory = format_string($category->name);
                }
            }

            // Get course image.
            $courseimage = $this->get_course_image($course);

            // Get course progress.
            $progress = $this->get_course_progress($course, $childid);

            // Get course grade.
            $gradeinfo = $this->get_course_grade($course, $childid);

            // Get activity completion.
            $activitycompletion = $this->get_activity_completion($course, $childid);

            // Get activity list.
            $activities = $this->get_activity_list($course, $childid);

            // Get grades list.
            $gradeslist = $this->get_grades_list($course, $childid);

            // Get course instructors (teachers).
            $instructors = $this->get_course_instructors($course->id);

            $coursedata[] = [
                'id' => $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'viewurl' => $courseurl->out(false),
                'url' => $courseurl->out(false),
                'courseimage' => $courseimage,
                'coursecategory' => $coursecategory,
                'hasprogress' => $progress['percentage'] !== null,
                'progress' => $progress['percentage'],
                'progresstext' => $progress['text'],
                'progressclass' => $progress['class'],
                'grade' => $gradeinfo['grade'],
                'gradetext' => $gradeinfo['text'],
                'gradeclass' => $gradeinfo['class'],
                'grademax' => $gradeinfo['grademax'],
                'hasgrade' => $gradeinfo['hasgrade'],
                'percentage' => $gradeinfo['percentage'],
                'activitycompleted' => $activitycompletion['completed'],
                'activitytotal' => $activitycompletion['total'],
                'activitypercentage' => $activitycompletion['percentage'],
                'activityclass' => $activitycompletion['class'],
                'activities' => $activities,
                'hasactivities' => !empty($activities),
                'gradeslist' => $gradeslist,
                'hasgradeslist' => !empty($gradeslist),
                'finalgrade' => $gradeinfo['grade'],
                'finalgradetext' => $gradeinfo['text'],
                'uniqid' => uniqid(),
                'instructors' => $instructors,
                'hasinstructor' => !empty($instructors),
            ];
        }

        return $coursedata;
    }

    /**
     * Get course image URL.
     *
     * @param object $course Course object
     * @return string Course image URL
     */
    private function get_course_image($course) {
        global $CFG, $OUTPUT;

        require_once($CFG->libdir . '/filelib.php');

        $coursecontext = \context_course::instance($course->id);
        
        // Try to get course overview files.
        $fs = get_file_storage();
        $files = $fs->get_area_files($coursecontext->id, 'course', 'overviewfiles', false, 'filename', false);
        
        if (count($files)) {
            $file = reset($files);
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            );
            return $url->out();
        }

        // Return default course image.
        return $OUTPUT->get_generated_image_for_id($course->id);
    }

    /**
     * Get course progress percentage.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Progress data
     */
    private function get_course_progress($course, $userid) {
        $completion = new completion_info($course);

        if (!$completion->is_enabled()) {
            return [
                'percentage' => 0,
                'text' => get_string('notstarted', 'block_customdashboard'),
                'class' => 'bg-secondary',
            ];
        }

        $percentage = (int) \core_completion\progress::get_course_progress_percentage($course, $userid);

        if ($percentage === null) {
            $percentage = 0;
        }

        $text = get_string('notstarted', 'block_customdashboard');
        $class = 'bg-secondary';

        if ($percentage > 0 && $percentage < 100) {
            $text = get_string('inprogress', 'block_customdashboard');
            $class = 'bg-warning';
        } else if ($percentage == 100) {
            $text = get_string('completed', 'block_customdashboard');
            $class = 'bg-success';
        }

        return [
            'percentage' => $percentage,
            'text' => $text,
            'class' => $class,
        ];
    }

    /**
     * Get course grade.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Grade data
     */
    private function get_course_grade($course, $userid) {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        $gradeitem = grade_item::fetch_course_item($course->id);

        if (!$gradeitem) {
            return [
                'grade' => '-',
                'text' => '-',
                'class' => 'bg-secondary',
                'grademax' => 0,
                'percentage' => 0,
                'hasgrade' => false,
            ];
        }

        $grade = new grade_grade(['itemid' => $gradeitem->id, 'userid' => $userid]);
        $grade->grade_item = $gradeitem;

        $finalgrade = $grade->finalgrade;

        if ($finalgrade === null) {
            return [
                'grade' => '-',
                'text' => '-',
                'class' => 'bg-secondary',
                'grademax' => 0,
                'percentage' => 0,
                'hasgrade' => false,
            ];
        }

        $gradetext = grade_format_gradevalue($finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_REAL);

        // Calculate percentage based only on graded activities (excluding N/A).
        $percentage = $this->calculate_grade_percentage($course, $userid);

        $class = 'bg-danger';
        if ($percentage >= 70) {
            $class = 'bg-success';
        } else if ($percentage >= 50) {
            $class = 'bg-warning';
        }

        return [
            'grade' => round($gradetext, 2),
            'text' => round($percentage, 1) . '%',
            'class' => $class,
            'grademax' => round($gradeitem->grademax, 2),
            'percentage' => round($percentage, 1),
            'hasgrade' => true,
        ];
    }

    /**
     * Calculate grade percentage excluding N/A activities.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return float Grade percentage
     */
    private function calculate_grade_percentage($course, $userid) {
        $modinfo = get_fast_modinfo($course, $userid);
        $totalpoints = 0;
        $earnedpoints = 0;

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $gradeitem = grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => $cm->modname,
                'iteminstance' => $cm->instance,
                'courseid' => $course->id,
            ]);

            if ($gradeitem && $gradeitem->gradetype == GRADE_TYPE_VALUE) {
                $grade = new grade_grade(['itemid' => $gradeitem->id, 'userid' => $userid]);
                $grade->grade_item = $gradeitem;

                // Only include graded activities (not N/A).
                if ($grade->finalgrade !== null && $grade->finalgrade !== false) {
                    $totalpoints += $gradeitem->grademax;
                    $earnedpoints += $grade->finalgrade;
                }
            }
        }

        if ($totalpoints > 0) {
            return ($earnedpoints / $totalpoints) * 100;
        }

        return 0;
    }

    /**
     * Get activity completion statistics.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Activity completion data
     */
    private function get_activity_completion($course, $userid) {
        $completion = new completion_info($course);

        if (!$completion->is_enabled()) {
            return [
                'completed' => 0,
                'total' => 0,
                'percentage' => 0,
                'class' => 'bg-secondary',
            ];
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $completed = 0;
        $total = 0;

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $total++;
                $completiondata = $completion->get_data($cm, false, $userid);
                if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                    $completed++;
                }
            }
        }

        $percentage = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        $class = 'bg-secondary';
        if ($percentage > 0 && $percentage < 100) {
            $class = 'bg-warning';
        } else if ($percentage == 100) {
            $class = 'bg-success';
        }

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
            'class' => $class,
        ];
    }

    /**
     * Get activity list with completion status.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Activity list
     */
    private function get_activity_list($course, $userid) {
        global $OUTPUT;
        
        $completion = new completion_info($course);
        $activities = [];

        if (!$completion->is_enabled()) {
            return $activities;
        }

        $modinfo = get_fast_modinfo($course, $userid);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $completiondata = $completion->get_data($cm, false, $userid);
                $iscompleted = ($completiondata->completionstate == COMPLETION_COMPLETE ||
                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS);

                // Get module icon.
                $iconurl = $cm->get_icon_url()->out(false);

                $activities[] = [
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                    'type' => get_string('modulename', $cm->modname),
                    'modname' => $cm->modname,
                    'iconurl' => $iconurl,
                    'completed' => $iscompleted,
                    'completedtext' => $iscompleted ? 
                        get_string('completed', 'block_customdashboard') : 
                        get_string('notcompleted', 'block_customdashboard'),
                    'completedclass' => $iscompleted ? 'badge-success' : 'badge-secondary',
                ];
            }
        }

        return $activities;
    }

    /**
     * Get grades list for all activities.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Grades list
     */
    private function get_grades_list($course, $userid) {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        $gradeslist = [];
        $modinfo = get_fast_modinfo($course, $userid);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $gradeitem = grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => $cm->modname,
                'iteminstance' => $cm->instance,
                'courseid' => $course->id,
            ]);

            if ($gradeitem) {
                $grade = new grade_grade(['itemid' => $gradeitem->id, 'userid' => $userid]);
                $grade->grade_item = $gradeitem;

                $gradevalue = $grade->finalgrade;
                $gradetext = get_string('na', 'block_customdashboard');
                $isscale = false;
                $scaleitems = [];
                $achievedscale = '';
                $grademax = 0;

                if ($gradevalue !== null && $gradevalue !== false) {
                    // Check if it's a scale or numeric grade.
                    if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
                        $isscale = true;
                        // Get scale items.
                        $scale = $gradeitem->load_scale();
                        if ($scale) {
                            $scaleitems = explode(',', $scale->scale);
                            // Get achieved scale item (grade value is 1-based index).
                            $scaleindex = intval($gradevalue) - 1;
                            if (isset($scaleitems[$scaleindex])) {
                                $achievedscale = trim($scaleitems[$scaleindex]);
                                $gradetext = $achievedscale;
                            }
                            // Clean scale items.
                            $scaleitems = array_map('trim', $scaleitems);
                        }
                    } else {
                        // Numeric grade.
                        $gradetext = grade_format_gradevalue($gradevalue, $gradeitem, true, GRADE_DISPLAY_TYPE_REAL);
                        $grademax = $gradeitem->grademax;
                    }
                }

                $gradeslist[] = [
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                    'grade' => $gradetext,
                    'hasgrade' => ($gradevalue !== null && $gradevalue !== false),
                    'isscale' => $isscale,
                    'scaleitems' => $scaleitems,
                    'achievedscale' => $achievedscale,
                    'grademax' => $grademax,
                    'gradevalue' => $gradevalue,
                ];
            }
        }

        return $gradeslist;
    }

    /**
     * Get course instructors (teachers).
     *
     * @param int $courseid Course ID
     * @return array Instructors list
     */
    private function get_course_instructors($courseid) {
        global $DB, $OUTPUT;

        $coursecontext = \context_course::instance($courseid);
        
        // Get teacher and editing teacher roles.
        $teacherroles = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE archetype IN ('editingteacher', 'teacher')"
        );

        if (empty($teacherroles)) {
            return [];
        }

        $roleids = array_keys($teacherroles);
        list($insql, $params) = $DB->get_in_or_equal($roleids);
        $params[] = $coursecontext->id;

        $instructors = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt, u.firstnamephonetic,
                    u.lastnamephonetic, u.middlename, u.alternatename
             FROM {user} u
             JOIN {role_assignments} ra ON ra.userid = u.id
             WHERE ra.roleid $insql AND ra.contextid = ?
             ORDER BY u.lastname, u.firstname
             LIMIT 1",
            $params
        );

        $instructordata = [];
        foreach ($instructors as $instructor) {
            $instructordata[] = [
                'id' => $instructor->id,
                'fullname' => fullname($instructor),
            ];
        }

        return $instructordata;
    }

    /**
     * Get zoom classes for a user.
     *
     * @param int $userid User ID
     * @param string $role User role (student or teacher)
     * @return array Zoom classes data
     */
    private function get_zoom_classes($userid, $role) {
        global $DB, $CFG;

        // Check if zoom module exists.
        if (!$DB->record_exists('modules', ['name' => 'zoom', 'visible' => 1])) {
            return ['items' => [], 'hasitems' => false];
        }

        $now = time();
        $todaystart = strtotime('today', $now);

        // Get enrolled courses based on role.
        $courses = enrol_get_users_courses($userid, true);
        
        if (empty($courses)) {
            return ['items' => [], 'hasitems' => false];
        }

        $courseids = array_keys($courses);

        // If teacher, filter courses where user has teacher/manager role.
        if ($role === 'teacher') {
            $filteredcourses = [];
            foreach ($courses as $course) {
                $coursecontext = \context_course::instance($course->id);
                if (has_capability('mod/zoom:addinstance', $coursecontext, $userid)) {
                    $filteredcourses[] = $course->id;
                }
            }
            $courseids = $filteredcourses;
        }

        if (empty($courseids)) {
            return ['items' => [], 'hasitems' => false];
        }

        list($insql, $params) = $DB->get_in_or_equal($courseids);
        
        // Get all zoom calendar events from today onwards.
        // Zoom creates calendar events for each occurrence (including recurring meetings).
        $params[] = $todaystart;
        
        $sql = "SELECT e.id, e.name, e.timestart, e.instance as zoomid, e.courseid, c.fullname as coursename
                FROM {event} e
                JOIN {course} c ON e.courseid = c.id
                WHERE e.modulename = 'zoom'
                AND e.courseid $insql
                AND e.timestart >= ?
                AND e.visible = 1
                ORDER BY e.timestart ASC";

        $events = $DB->get_records_sql($sql, $params);

        $items = [];
        foreach ($events as $event) {
            // Get the zoom instance to fetch duration and join_url.
            $zoom = $DB->get_record('zoom', ['id' => $event->zoomid], 'id, name, duration, join_url');
            
            if ($zoom) {
                // Get course module ID for the zoom activity.
                $cm = get_coursemodule_from_instance('zoom', $zoom->id, $event->courseid);
                $activityurl = '';
                if ($cm) {
                    $activityurl = new \moodle_url('/mod/zoom/view.php', ['id' => $cm->id]);
                    $activityurl = $activityurl->out(false);
                }
                
                $items[] = [
                    'id' => $zoom->id,
                    'name' => format_string($event->name),
                    'coursename' => format_string($event->coursename),
                    'starttime' => userdate($event->timestart, get_string('strftimedatetime', 'langconfig')),
                    'starttimestamp' => $event->timestart,
                    'duration' => $zoom->duration / 60, // Convert minutes to hours
                    'joinurl' => $zoom->join_url,
                    'activityurl' => $activityurl,
                ];
            }
        }

        return [
            'items' => $items,
            'hasitems' => !empty($items),
        ];
    }

    /**
     * Get teachers for courses where student is enrolled.
     *
     * @param int $studentid Student user ID
     * @return array Teachers data
     */
    private function get_student_teachers($studentid) {
        global $DB, $OUTPUT;

        $courses = enrol_get_users_courses($studentid, true);
        
        if (empty($courses)) {
            return ['items' => [], 'hasitems' => false];
        }

        $teachersdata = [];
        $uniqueteachers = [];

        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id);
            
            // Get teacher and editing teacher roles.
            $teacherroles = $DB->get_records_sql(
                "SELECT id FROM {role} WHERE archetype IN ('manager', 'editingteacher', 'teacher')"
            );

            if (empty($teacherroles)) {
                continue;
            }

            $roleids = array_keys($teacherroles);
            list($insql, $params) = $DB->get_in_or_equal($roleids);
            $params[] = $coursecontext->id;

            $teachers = $DB->get_records_sql(
                "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.phone2, u.picture, u.imagealt,
                        u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                 FROM {user} u
                 JOIN {role_assignments} ra ON ra.userid = u.id
                 WHERE ra.roleid $insql AND ra.contextid = ?
                 ORDER BY u.lastname, u.firstname",
                $params
            );

            foreach ($teachers as $teacher) {
                if (!isset($uniqueteachers[$teacher->id])) {
                    $uniqueteachers[$teacher->id] = [
                        'id' => $teacher->id,
                        'fullname' => fullname($teacher),
                        'email' => $teacher->email,
                        'phone' => !empty($teacher->phone2) ? $teacher->phone2 : '',
                        'hasphone' => !empty($teacher->phone2),
                        'picture' => $OUTPUT->user_picture($teacher, ['size' => 50, 'link' => false]),
                        'courses' => [],
                    ];
                }
                $uniqueteachers[$teacher->id]['courses'][] = format_string($course->fullname);
            }
        }

        foreach ($uniqueteachers as $teacher) {
            $teacher['courseslist'] = implode(', ', $teacher['courses']);
            $teachersdata[] = $teacher;
        }

        return [
            'items' => $teachersdata,
            'hasitems' => !empty($teachersdata),
        ];
    }
}
