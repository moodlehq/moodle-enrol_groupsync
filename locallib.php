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
 * Local stuff for cohort to group plugin.
 *
 * @package    enrol_groupsync
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Event handler for cohort enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class enrol_groupsync_handler {
    /**
     * Event processor - user enrolled.
     * @param stdClass $ue
     * @return bool
     */
    public static function user_enrolled($ue) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/group/lib.php");

        if (!enrol_is_enabled('groupsync')) {
            return true;
        }

        // No need to verify user is really enrolled in this course.

        $sql = "SELECT e.id, e.customint2
                  FROM {enrol} e
                  JOIN {cohort} c ON (c.id = e.customint1)
                  JOIN {cohort_members} cm ON (cm.cohortid = c.id AND cm.userid = :userid)
                  JOIN {groups} g ON (g.id = e.customint2 AND g.courseid = e.courseid)
             LEFT JOIN {groups_members} gm ON (gm.userid = cm.userid AND gm.groupid = g.id)
                 WHERE e.courseid = :courseid AND e.enrol = 'groupsync' AND e.status = :enabled AND gm.id IS NULL
              ORDER BY e.id ASC";
        $instances = $DB->get_records_sql($sql, array('courseid'=>$ue->courseid, 'enabled'=>ENROL_INSTANCE_ENABLED, 'userid'=>$ue->userid));

        foreach ($instances as $instance) {
            groups_add_member($instance->customint2, $ue->userid, 'enrol_groupsync', $instance->id);
        }

        return true;
    }

    /**
     * Event processor - user unenrolled.
     * @param stdClass $ue
     * @return bool
     */
    public static function user_unenrolled($ue) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/group/lib.php");

        if (!$ue->lastenrol) {
            return true;
        }

        // No need to verify here that user was unenrolled from the course.

        $sql = "SELECT gm.*
                  FROM {groups_members} gm
                  JOIN {groups} g ON (g.id = gm.groupid AND g.courseid = :courseid AND gm.component = 'enrol_groupsync')
                 WHERE gm.userid = :userid";
        $rs = $DB->get_recordset_sql($sql, array('courseid'=>$ue->courseid, 'userid'=>$ue->userid));
        foreach ($rs as $gm) {
            groups_remove_member($gm->groupid, $gm->userid);
        }
        $rs->close();

        return true;
    }

    /**
     * Event processor - cohort member added.
     * @param stdClass $ca
     * @return bool
     */
    public static function member_added($ca) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/group/lib.php");

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
        $instances = $DB->get_records_sql($sql, array('cohortid'=>$ca->cohortid, 'userid'=>$ca->userid, 'enabled'=>ENROL_INSTANCE_ENABLED));

        foreach ($instances as $instance) {
            groups_add_member($instance->customint2, $ca->userid, 'enrol_groupsync', $instance->id);
        }

        return true;
    }

    /**
     * Event processor - cohort member removed.
     * @param stdClass $ca
     * @return bool
     */
    public static function member_removed($ca) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/group/lib.php");

        // NOTE: this may not be accurate if user is in several cohorts syncing to the same group, cron will fix it later.

        $sql = "SELECT DISTINCT e.id, e.customint2
                  FROM {enrol} e
                  JOIN {groups} g ON (g.id = e.customint2 AND g.courseid = e.courseid)
                  JOIN {groups_members} gm ON (gm.userid = :userid AND gm.groupid = g.id AND gm.component = 'enrol_groupsync' AND gm.itemid = e.id)
                 WHERE e.customint1 = :cohortid AND e.enrol = 'groupsync'
              ORDER BY e.id ASC";
        $instances = $DB->get_records_sql($sql, array('cohortid'=>$ca->cohortid, 'userid'=>$ca->userid));

        foreach ($instances as $instance) {
            groups_remove_member($instance->customint2, $ca->userid);
        }

        return true;
    }

    /**
     * Event processor - cohort deleted.
     * @param stdClass $cohort
     * @return bool
     */
    public static function deleted($cohort) {
        global $DB;

        // Does anything want to sync with this cohort?
        if (!$instances = $DB->get_records('enrol', array('customint1'=>$cohort->id, 'enrol'=>'groupsync'), 'id ASC')) {
            return true;
        }

        $plugin = enrol_get_plugin('groupsync');
        foreach ($instances as $instance) {
            $plugin->delete_instance($instance);
        }

        return true;
    }
}


/**
 * Sync all cohort course links.
 * @param int $courseid one course, empty mean all
 * @param bool $verbose verbose CLI output
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_groupsync_sync($courseid = NULL, $verbose = false) {
    global $CFG, $DB;
    require_once("$CFG->dirroot/group/lib.php");

    // Purge all roles if cohort sync disabled, those can be recreated later here by cron or CLI.
    if (!enrol_is_enabled('groupsync')) {
        if ($verbose) {
            mtrace('Cohort to group sync plugin is disabled, removing all group memberships.');
        }

        $sql = "SELECT gm.groupid, gm.userid
                  FROM {groups_members} gm
                 WHERE gm.component = 'enrol_groupsync'";
        $rs = $DB->get_recordset_sql($sql, array());
        foreach ($rs as $gm) {
            groups_remove_member($gm->groupid, $gm->userid);
        }
        $rs->close();

        return 2;
    }

    // Unfortunately this may take a long time, this script can be interrupted without problems.
    @set_time_limit(0);

    if ($verbose) {
        mtrace('Starting cohort to group membership synchronisation...');
    }

    if ($courseid) {
        $params = array('enabled'=>ENROL_INSTANCE_ENABLED, 'courseid'=>$courseid);
        $courseselect = "AND e.courseid = :courseid";

    } else {
        $params = array('enabled'=>ENROL_INSTANCE_ENABLED);
        $courseselect = "";
    }

    // Cleanup first.
    $sql = "SELECT DISTINCT gm.groupid, gm.userid, g.courseid
              FROM {groups_members} gm
              JOIN {groups} g ON (g.id = gm.groupid)
              JOIN {enrol} e ON (e.courseid = g.courseid AND e.enrol = 'groupsync')
              JOIN {cohort} c ON (c.id = e.customint1)
         LEFT JOIN {cohort_members} cm ON (cm.cohortid = c.id AND cm.userid = gm.userid)
             WHERE gm.component = 'enrol_groupsync' AND gm.itemid = e.id AND (e.status <> :enabled OR g.id <> e.customint2 OR cm.id IS NULL) $courseselect";
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $gm) {
        groups_remove_member($gm->groupid, $gm->userid);
        if ($verbose) {
            mtrace("Removing user $gm->userid from group $gm->groupid in course $gm->courseid");
        }
    }
    $rs->close();

    // Add missing group memberships.
    $sql = "SELECT DISTINCT e.id, e.customint2, e.courseid, ue.userid
              FROM {enrol} e
              JOIN {enrol} oe ON (oe.courseid = e.courseid and oe.enrol <> 'groupsync')
              JOIN {user_enrolments} ue ON (ue.enrolid = oe.id)
              JOIN {cohort} c ON (c.id = e.customint1)
              JOIN {cohort_members} cm ON (cm.cohortid = c.id AND cm.userid = ue.userid)
              JOIN {groups} g ON (g.courseid = e.courseid AND g.id = e.customint2)
         LEFT JOIN {groups_members} gm ON (gm.userid = ue.userid AND gm.groupid = g.id)
             WHERE e.enrol = 'groupsync' AND e.status = :enabled $courseselect AND gm.id IS NULL
          ORDER BY e.id ASC";
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $instance) {
        if (groups_add_member($instance->customint2, $instance->userid, 'enrol_groupsync', $instance->id)) {
            if ($verbose) {
                mtrace("Adding user $instance->userid to group $instance->customint2 in course $instance->courseid");
            }
        } else {
            if ($verbose) {
                mtrace("Could not add $instance->userid to group $instance->customint2 in course $instance->courseid");
            }
        }
    }
    $rs->close();

    if ($verbose) {
        mtrace('...synchronisation finished.');
    }

    return 0;
}
