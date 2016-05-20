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
 * Events observer class is defined here
 *
 * @package     enrol_groupsync
 * @category    event
 * @copyright   2016 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Events observer listening to user enrolment related events
 *
 * @copyright 2016 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_groupsync_observer {

    /**
     * Adds the user to the group on course enrolment
     *
     * @param user_enrolment_created $event
     * @return bool
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        if (!enrol_is_enabled('groupsync')) {
            return true;
        }

        $sql = "SELECT e.id, e.customint2
                  FROM {enrol} e
                  JOIN {cohort} c ON (c.id = e.customint1)
                  JOIN {cohort_members} cm ON (cm.cohortid = c.id AND cm.userid = :userid)
                  JOIN {groups} g ON (g.id = e.customint2 AND g.courseid = e.courseid)
             LEFT JOIN {groups_members} gm ON (gm.userid = cm.userid AND gm.groupid = g.id)
                 WHERE e.courseid = :courseid AND e.enrol = 'groupsync' AND e.status = :enabled AND gm.id IS NULL
              ORDER BY e.id ASC";

        $instances = $DB->get_records_sql($sql, ['courseid' => $event->courseid, 'enabled' => ENROL_INSTANCE_ENABLED,
            'userid' => $event->relateduserid]);

        foreach ($instances as $instance) {
            groups_add_member($instance->customint2, $event->relateduserid, 'enrol_groupsync', $instance->id);
        }

        return true;
    }

    /**
     * Removes the user from the group on unenrolment
     *
     * @param user_enrolment_deleted $event
     * @return bool
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        if (!enrol_is_enabled('groupsync')) {
            return true;
        }

        $sql = "SELECT gm.*
                  FROM {groups_members} gm
                  JOIN {groups} g ON (g.id = gm.groupid AND g.courseid = :courseid AND gm.component = 'enrol_groupsync')
                 WHERE gm.userid = :userid";

        $rs = $DB->get_recordset_sql($sql, ['courseid' => $event->courseid, 'userid' => $event->relateduserid]);
        foreach ($rs as $gm) {
            groups_remove_member($gm->groupid, $gm->userid);
        }
        $rs->close();

        return true;
    }

    /**
     * Adds the user to the group on becoming cohort member
     *
     * @param cohort_member_added $event
     * @return bool
     */
    public static function cohort_member_added(\core\event\cohort_member_added $event) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        if (!enrol_is_enabled('groupsync')) {
            return true;
        }

        $sql = "SELECT DISTINCT e.id, e.customint2
                  FROM {enrol} e
                  JOIN {enrol} oe ON (oe.courseid = e.courseid and oe.enrol <> 'groupsync')
                  JOIN {user_enrolments} ue ON (ue.enrolid = oe.id AND ue.userid = :userid)
                  JOIN {cohort} c ON (c.id = e.customint1)
                  JOIN {cohort_members} cm ON (cm.cohortid = c.id AND cm.userid = ue.userid)
                  JOIN {groups} g ON (g.id = e.customint2 AND g.courseid = e.courseid)
             LEFT JOIN {groups_members} gm ON (gm.userid = ue.userid AND gm.groupid = g.id)
                 WHERE e.customint1 = :cohortid AND e.enrol = 'groupsync' AND e.status = :enabled AND gm.id IS NULL
              ORDER BY e.id ASC";

        $instances = $DB->get_records_sql($sql, ['cohortid' => $event->objectid, 'userid' => $event->relateduserid,
            'enabled' => ENROL_INSTANCE_ENABLED]);

        foreach ($instances as $instance) {
            groups_add_member($instance->customint2, $event->relateduserid, 'enrol_groupsync', $instance->id);
        }

        return true;
    }

    /**
     * Remove the user from the group on removing them from the cohort
     *
     * This may not be accurate if user is in several cohorts syncing to the same group. The
     * cron will fix it later.
     *
     * @param cohort_member_removed $event
     * @return bool
     */
    public static function cohort_member_removed(\core\event\cohort_member_removed $event) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        if (!enrol_is_enabled('groupsync')) {
            return true;
        }

        $sql = "SELECT DISTINCT e.id, e.customint2
                  FROM {enrol} e
                  JOIN {groups} g ON (g.id = e.customint2 AND g.courseid = e.courseid)
                  JOIN {groups_members} gm ON (gm.userid = :userid AND gm.groupid = g.id AND gm.component = 'enrol_groupsync'
                       AND gm.itemid = e.id)
                 WHERE e.customint1 = :cohortid AND e.enrol = 'groupsync'
              ORDER BY e.id ASC";

        $instances = $DB->get_records_sql($sql, ['cohortid' => $event->objectid, 'userid' => $event->relateduserid]);

        foreach ($instances as $instance) {
            groups_remove_member($instance->customint2, $event->relateduserid);
        }

        return true;
    }

    /**
     * Delete the enrolment instances on the associated cohort removal.
     *
     * @param cohort_deleted $event
     * @return bool
     */
    public static function cohort_deleted(\core\event\cohort_deleted $event) {
        global $DB;

        if (!enrol_is_enabled('groupsync')) {
            return true;
        }

        if (!$instances = $DB->get_records('enrol', ['customint1' => $event->objectid, 'enrol' => 'groupsync'])) {
            return true;
        }

        $plugin = enrol_get_plugin('groupsync');
        foreach ($instances as $instance) {
            $plugin->delete_instance($instance);
        }

        return true;
    }
}
