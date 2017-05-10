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
 * Group-cohort sync plugin.
 *
 * @package    enrol_groupsync
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Group-cohort sync plugin.
 *
 * @copyright Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_groupsync_plugin extends enrol_plugin {
    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol);

        } else if (empty($instance->name)) {
            $enrol = $this->get_name();
            $cohort = $DB->get_record('cohort', array('id' => $instance->customint1));
            $group = $DB->get_record('groups', array('id' => $instance->customint2));
            if ($cohort and $group) {
                $groupname = format_string($group->name, true, array('context' => context_course::instance($instance->courseid)));
                $cohortname = format_string($cohort->name, true, array('context' => context::instance_by_id($cohort->contextid)));
                return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ' -> ' . $groupname . ')';
            } else {
                return get_string('pluginname', 'enrol_'.$enrol) . ' - ' . get_string('error');
            }
        } else {
            return format_string($instance->name, true, array('context' => context_course::instance($instance->courseid)));
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        if (!$this->can_add_new_instances($courseid)) {
            return null;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/groupsync/edit.php', array('courseid' => $courseid));
    }

    /**
     * Given a courseid this function returns true if the user is able to enrol or configure cohorts.
     * AND there are cohorts that the user can view.
     *
     * @param int $courseid
     * @return bool
     */
    protected function can_add_new_instances($courseid) {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $coursecontext)
                or !has_capability('enrol/groupsync:config', $coursecontext)) {
            return false;
        }
        list($sqlparents, $params) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids());
        $sql = "SELECT id, contextid
                  FROM {cohort}
                 WHERE contextid $sqlparents
              ORDER BY name ASC";
        $cohorts = $DB->get_records_sql($sql, $params);
        foreach ($cohorts as $c) {
            $context = context::instance_by_id($c->contextid);
            if (has_capability('moodle/cohort:view', $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass  $instance
     * @return bool
     */
    public function can_delete_instance($instance) {

        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/groupsync:config', $context);
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {

        if ($instance->enrol !== 'groupsync') {
            throw new coding_exception('invalid enrol instance!');
        }

        return array();
    }

    /**
     * Called for all enabled enrol plugins that returned true from is_cron_required().
     * @return void
     */
    public function cron() {
        global $CFG;

        require_once("$CFG->dirroot/enrol/groupsync/locallib.php");
        enrol_groupsync_sync();
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {

        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/groupsync:config', $context);
    }

    /**
     * Update instance status
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     */
    public function update_status($instance, $newstatus) {
        global $CFG;

        parent::update_status($instance, $newstatus);

        require_once("$CFG->dirroot/enrol/groupsync/locallib.php");
        enrol_groupsync_sync($instance->courseid);
    }

    /**
     * Delete course enrol plugin instance, unenrol all users.
     * @param stdClass $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $CFG, $DB;

        $name = $this->get_name();
        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }

        // NOTE: We must delete groups manually, there are no user enrolments that would clean them up.

        if ($gms = $DB->get_records('groups_members', array('component' => 'enrol_'.$name, 'itemid' => $instance->id))) {
            foreach ($gms as $gm) {
                groups_remove_member($gm->groupid, $gm->userid);
            }
        }

        parent::delete_instance($instance);

        require_once("$CFG->dirroot/enrol/groupsync/locallib.php");
        enrol_groupsync_sync($instance->courseid);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB, $CFG;

        // No restore support, sorry.

        $step->set_mapping('enrol', $oldid, 0);
        return;
    }

    /**
     * Restore user group membership.
     * @param stdClass $instance
     * @param int $groupid
     * @param int $userid
     */
    public function restore_group_member($instance, $groupid, $userid) {

        // No restore support, sorry.

        return;
    }
}

/**
 * Prevent removal of enrol roles.
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
 */
function enrol_groupsync_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}
