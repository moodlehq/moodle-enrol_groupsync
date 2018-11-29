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
 * Defines {@link \enrol_groupsync\privacy\provider} class.
 *
 * @package     enrol_groupsync
 * @category    privacy
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_groupsync\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy API implementation for the Cohort members to group plugin.
 *
 * @copyright  2018 David Mudrák <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    use \core_privacy\local\legacy_polyfill;

    /**
     * Describe all the places where the Cohort members to group plugin stores some personal data.
     *
     * @param collection $collection Collection of items to add metadata to.
     * @return collection Collection with our added items.
     */
    public static function _get_metadata(collection $collection) {

        $collection->add_subsystem_link('core_group', [], 'privacy:metadata:subsystem:group');

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the specified user.
     *
     * @param int $userid ID of the user.
     * @return contextlist List of contexts containing the user's personal data.
     */
    public static function _get_contexts_for_userid($userid) {

        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {groups_members} gm
                       JOIN {groups} g ON gm.groupid = g.id
                       JOIN {context} ctx ON g.courseid = ctx.instanceid AND ctx.contextlevel = :contextlevel
                 WHERE gm.userid = :userid
                       AND gm.component = 'enrol_groupsync'";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        \core_group\privacy\provider::get_group_members_in_context($userlist, 'enrol_groupsync');
    }

    /**
     * Export personal data stored in the given contexts.
     *
     * @param approved_contextlist $contextlist List of contexts approved for export.
     */
    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                \core_group\privacy\provider::export_groups(
                    $context,
                    'enrol_groupsync',
                    [get_string('pluginname', 'enrol_groupsync')]
                );
            }
        }
    }

    /**
     * Delete personal data for all users in the context.
     *
     * @param context $context Context to delete personal data from.
     */
    public static function _delete_data_for_all_users_in_context(\context $context) {

        if ($context->contextlevel == CONTEXT_COURSE) {
            \core_group\privacy\provider::delete_groups_for_all_users($context, 'enrol_groupsync');
        }
    }

    /**
     * Delete personal data for the user in a list of contexts.
     *
     * @param approved_contextlist $contextlist List of contexts to delete data from.
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {

        if (!$contextlist->count()) {
            return;
        }

        \core_group\privacy\provider::delete_groups_for_user($contextlist, 'enrol_groupsync');
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        \core_group\privacy\provider::delete_groups_for_users($userlist, 'enrol_groupsync');
    }
}
