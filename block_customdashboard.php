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
 * Custom Dashboard block.
 *
 * @package    block_customdashboard
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom Dashboard block class.
 */
class block_customdashboard extends block_base {

    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('title', 'block_customdashboard');
    }

    /**
     * Set the block title based on instance configuration.
     */
    public function specialization() {
        if (isset($this->config->title) && trim($this->config->title) !== '') {
            $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        }
    }

    /**
     * Multiple instances not allowed.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Which page types this block may appear on.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'my' => true,
            'all' => false,
        ];
    }

    /**
     * Instance configuration is allowed.
     *
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Return true if content should be displayed.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Get the block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Check if user is admin - don't show block for admins.
        $systemcontext = \context_system::instance();
        if (is_siteadmin() || has_capability('moodle/site:config', $systemcontext)) {
            return $this->content;
        }

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_customdashboard');

        // Determine user role and render appropriate content.
        $userrole = $this->get_user_role();

        switch ($userrole) {
            case 'parent':
                $this->content->text = $this->render_parent_dashboard($renderer);
                break;
            case 'student':
                $this->content->text = $renderer->render_student_dashboard($USER->id);
                break;
            case 'teacher':
                $this->content->text = $renderer->render_teacher_dashboard($USER->id);
                break;
            default:
                // No content for other roles.
                break;
        }

        return $this->content;
    }

    /**
     * Determine the user's primary role.
     *
     * @return string Role (parent, student, teacher, or other)
     */
    private function get_user_role() {
        global $USER, $DB, $CFG;
        
        require_once($CFG->dirroot . '/local/parentmanager/lib.php');

        // Check if user is a parent.
        if (local_parentmanager_is_parent($USER->id)) {
            return 'parent';
        }

        // Check if user has teacher or manager role in any course.
        $systemcontext = \context_system::instance();
        
        // Get teacher and manager roles.
        $teacherroles = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE archetype IN ('editingteacher', 'teacher', 'manager')"
        );
        
        if (!empty($teacherroles)) {
            $roleids = array_keys($teacherroles);
            list($insql, $params) = $DB->get_in_or_equal($roleids);
            $params[] = $USER->id;
            
            $hasteacherrole = $DB->record_exists_sql(
                "SELECT 1 FROM {role_assignments} ra
                 JOIN {context} ctx ON ra.contextid = ctx.id
                 WHERE ra.roleid $insql AND ra.userid = ? AND ctx.contextlevel = ?",
                array_merge($params, [CONTEXT_COURSE])
            );
            
            if ($hasteacherrole) {
                return 'teacher';
            }
        }

        // Check if user has student role in any course.
        $studentroles = $DB->get_records('role', ['archetype' => 'student']);
        if (!empty($studentroles)) {
            $roleids = array_keys($studentroles);
            list($insql, $params) = $DB->get_in_or_equal($roleids);
            $params[] = $USER->id;
            
            $hasstudentrole = $DB->record_exists_sql(
                "SELECT 1 FROM {role_assignments} ra
                   JOIN {context} ctx ON ra.contextid = ctx.id
                  WHERE ra.roleid $insql AND ra.userid = ? AND ctx.contextlevel = ?",
                array_merge($params, [CONTEXT_COURSE])
            );
            
            if ($hasstudentrole) {
                return 'student';
            }
        }

        return 'other';
    }

    /**
     * Render parent dashboard.
     *
     * @param object $renderer Renderer instance
     * @return string HTML content
     */
    private function render_parent_dashboard($renderer) {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/local/parentmanager/lib.php');

        // Get children for the current user.
        $children = local_parentmanager_get_children($USER->id);

        if (empty($children)) {
            return html_writer::div(
                get_string('nochildrenassigned', 'block_customdashboard'),
                'alert alert-info'
            );
        }

        // Get selected child from session or use first child.
        $selectedchildid = optional_param('selectedchild', 0, PARAM_INT);
        if ($selectedchildid && isset($children[$selectedchildid])) {
            // Store in user preference.
            set_user_preference('block_customdashboard_selectedchild', $selectedchildid);
        } else {
            $selectedchildid = get_user_preferences('block_customdashboard_selectedchild', 0);
            if (!$selectedchildid || !isset($children[$selectedchildid])) {
                // Get first child.
                $selectedchildid = reset($children)->id;
                set_user_preference('block_customdashboard_selectedchild', $selectedchildid);
            }
        }

        return $renderer->render_parent_dashboard($children, $selectedchildid);
    }
}
