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
 * File containing tests for privacy API implementation.
 *
 * @package     enrol_groupsync
 * @category    test
 * @copyright   2018 David Mudrák <david@moodle.com> based on code by Carlos Escobedo <carlos@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\request\writer;
use \core_privacy\local\request\approved_contextlist;
use \enrol_groupsync\privacy\provider;

/**
 * The privacy test class.
 *
 * @package    enrol_groupsync
 * @copyright  2018 David Mudrák <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_groupsync_privacy_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Basic setup for these tests.
     */
    public function setUp() {
        $this->enable_plugin();
    }

    /**
     * Helper function to enable the plugin.
     */
    protected function enable_plugin() {

        $enabled = enrol_get_plugins(true);
        $enabled['groupsync'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Test getting the context for the user ID related to this plugin.
     */
    public function test_get_contexts_for_userid() {
        global $DB;
        $this->resetAfterTest();

        $plugin = enrol_get_plugin('groupsync');
        $user1 = $this->getDataGenerator()->create_user();
        $cat1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id, 'manual');
        $cohort1 = $this->getDataGenerator()->create_cohort(
            array('contextid' => context_coursecat::instance($cat1->id)->id));
        $plugin->add_instance($course1, array(
            'customint1' => $cohort1->id,
            'roleid' => $studentrole->id,
            'customint2' => $group1->id)
        );

        cohort_add_member($cohort1->id, $user1->id);

        $this->assertTrue($DB->record_exists('groups_members', array(
            'groupid' => $group1->id,
            'userid' => $user1->id,
            'component' => 'enrol_groupsync')
        ));

        $contextlist = provider::get_contexts_for_userid($user1->id);

        $context = \context_course::instance($course1->id);
        $this->assertEquals(1, count($contextlist));
        $this->assertEquals($context->id, $contextlist->current()->id);
    }

    /**
     * Test for provider::get_users_in_context().
     */
    public function test_get_users_in_context() {
        global $DB;

        $this->resetAfterTest();

        $plugin = enrol_get_plugin('groupsync');
        $user1 = $this->getDataGenerator()->create_user();
        $cat1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id, 'manual');

        $cohort1 = $this->getDataGenerator()->create_cohort(
            array('contextid' => context_coursecat::instance($cat1->id)->id));
        $plugin->add_instance($course1, array(
            'customint1' => $cohort1->id,
            'roleid' => $studentrole->id,
            'customint2' => $group1->id)
        );

        cohort_add_member($cohort1->id, $user1->id);

        // Check if user1 is enrolled into course1 in group 1.
        $this->assertEquals(1, $DB->count_records('role_assignments', array()));
        $this->assertTrue($DB->record_exists('groups_members', array(
                'groupid' => $group1->id,
                'userid' => $user1->id,
                'component' => 'enrol_groupsync')
        ));

        $context = \context_course::instance($course1->id);

        $userlist = new \core_privacy\local\request\userlist($context, 'enrol_groupsync');
        \enrol_groupsync\privacy\provider::get_users_in_context($userlist);

        $this->assertEquals([$user1->id], $userlist->get_userids());
    }

    /**
     * Test that user data is exported correctly.
     */
    public function test_export_user_data() {
        global $DB;
        $this->resetAfterTest();

        $plugin = enrol_get_plugin('groupsync');
        $user1 = $this->getDataGenerator()->create_user();
        $cat1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id, 'manual');
        $cohort1 = $this->getDataGenerator()->create_cohort(
            array('contextid' => context_coursecat::instance($cat1->id)->id));
        $plugin->add_instance($course1, array(
            'customint1' => $cohort1->id,
            'roleid' => $studentrole->id,
            'customint2' => $group1->id)
        );
        cohort_add_member($cohort1->id, $user1->id);

        $this->setUser($user1);
        $contextlist = provider::get_contexts_for_userid($user1->id);
        $approvedcontextlist = new approved_contextlist($user1, 'enrol_groupsync', $contextlist->get_contextids());
        provider::export_user_data($approvedcontextlist);
        foreach ($contextlist as $context) {
            $writer = writer::with_context($context);
            $data = $writer->get_data([
                get_string('pluginname', 'enrol_groupsync'),
                get_string('groups', 'core_group')
            ]);
            $this->assertTrue($writer->has_any_data());
            if ($context->contextlevel == CONTEXT_COURSE) {
                $exportedgroups = $data->groups;
                $this->assertCount(1, $exportedgroups);
                $exportedgroup = reset($exportedgroups);
                $this->assertEquals($group1->name, $exportedgroup->name);
            }
        }
    }
    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest();

        $plugin = enrol_get_plugin('groupsync');
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $cat1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, $studentrole->id, 'manual');
        $cohort1 = $this->getDataGenerator()->create_cohort(
            array('contextid' => context_coursecat::instance($cat1->id)->id));
        $plugin->add_instance($course1, array(
            'customint1' => $cohort1->id,
            'roleid' => $studentrole->id,
            'customint2' => $group1->id)
        );

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);

        $this->assertEquals(2, $DB->count_records('groups_members', ['groupid' => $group1->id]));

        $coursecontext1 = context_course::instance($course1->id);
        provider::delete_data_for_all_users_in_context($coursecontext1);

        $this->assertEquals(0, $DB->count_records('groups_members', ['groupid' => $group1->id]));
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        $this->resetAfterTest();

        $plugin = enrol_get_plugin('groupsync');
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $cat1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));
        $course2 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course2->id));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, $studentrole->id, 'manual');
        $cohort1 = $this->getDataGenerator()->create_cohort(
            array('contextid' => context_coursecat::instance($cat1->id)->id));
        $plugin->add_instance($course1, array(
            'customint1' => $cohort1->id,
            'roleid' => $studentrole->id,
            'customint2' => $group1->id)
        );
        $plugin->add_instance($course2, array(
            'customint1' => $cohort1->id,
            'roleid' => $studentrole->id,
            'customint2' => $group2->id)
        );

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id);

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);

        $this->assertEquals(2, $DB->count_records('groups_members', ['groupid' => $group1->id]));
        $this->assertEquals(2, $DB->count_records('groups_members', ['groupid' => $group2->id]));

        $this->setUser($user1);
        $coursecontext1 = context_course::instance($course1->id);
        $coursecontext2 = context_course::instance($course2->id);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist($user1, 'enrol_groupsync',
            [$coursecontext1->id, $coursecontext2->id]);
        provider::delete_data_for_user($approvedcontextlist);

        $this->assertEquals(1, $DB->count_records('groups_members', ['groupid' => $group1->id]));
        $this->assertEquals(1, $DB->count_records('groups_members', ['groupid' => $group2->id]));
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {
        global $DB;

        $this->resetAfterTest();

        $plugin = enrol_get_plugin('groupsync');

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $cat1 = $this->getDataGenerator()->create_category();

        $course1 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));
        $course2 = $this->getDataGenerator()->create_course(array('category' => $cat1->id));

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course2->id));

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course2->id);

        $cohort1 = $this->getDataGenerator()->create_cohort(
            array('contextid' => context_coursecat::instance($cat1->id)->id));

        $plugin->add_instance($course1, array(
                'customint1' => $cohort1->id,
                'roleid' => $studentrole->id,
                'customint2' => $group1->id)
        );
        $plugin->add_instance($course2, array(
                'customint1' => $cohort1->id,
                'roleid' => $studentrole->id,
                'customint2' => $group2->id)
        );

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort1->id, $user3->id);

        $this->assertEquals(3, $DB->count_records('groups_members', ['groupid' => $group1->id]));
        $this->assertEquals(3, $DB->count_records('groups_members', ['groupid' => $group2->id]));

        $coursecontext1 = context_course::instance($course1->id);

        $approveduserlist = new \core_privacy\local\request\approved_userlist($coursecontext1, 'enrol_groupsync',
            [$user1->id, $user2->id]);
        provider::delete_data_for_users($approveduserlist);

        $this->assertEquals(1, $DB->count_records('groups_members', ['groupid' => $group1->id]));
        $this->assertEquals(3, $DB->count_records('groups_members', ['groupid' => $group2->id]));

        $approveduserlist = new \core_privacy\local\request\approved_userlist($coursecontext1, 'enrol_groupsync',
            [$user3->id]);
        provider::delete_data_for_users($approveduserlist);

        $this->assertEquals(0, $DB->count_records('groups_members', ['groupid' => $group1->id]));
        $this->assertEquals(3, $DB->count_records('groups_members', ['groupid' => $group2->id]));
    }
}
