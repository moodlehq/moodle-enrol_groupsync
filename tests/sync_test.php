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
 * Provides the {@link enrol_groupsync_testcase} class.
 *
 * @package    enrol_groupsync
 * @category   test
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/enrol/groupsync/locallib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/group/lib.php');

/**
 * Unit tests for synchronisation of cohort to group membership.
 *
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_groupsync_testcase extends advanced_testcase {

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
     * Helper function to disable the plugin.
     */
    protected function disable_plugin() {
        $enabled = enrol_get_plugins(true);
        unset($enabled['groupsync']);
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Test implicit sync via observed events.
     */
    public function test_observer_sync() {
        global $DB;

        $this->resetAfterTest();

        // Setup a few courses and categories.

        $this->enable_plugin();

        $cohortplugin = enrol_get_plugin('groupsync');
        $manualplugin = enrol_get_plugin('manual');

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->assertNotEmpty($teacherrole);
        $managerrole = $DB->get_record('role', array('shortname' => 'manager'));
        $this->assertNotEmpty($managerrole);

        $course1 = $this->getDataGenerator()->create_course(array());
        $course2 = $this->getDataGenerator()->create_course(array());
        $course3 = $this->getDataGenerator()->create_course(array());
        $course4 = $this->getDataGenerator()->create_course(array());
        $maninstance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $maninstance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $maninstance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'manual'), '*', MUST_EXIST);

        $group1a = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group1b = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course2->id));

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        $manualplugin->enrol_user($maninstance1, $user4->id, $teacherrole->id);
        $manualplugin->enrol_user($maninstance1, $user3->id, $managerrole->id);
        $manualplugin->enrol_user($maninstance2, $user2->id, $studentrole->id);

        $this->assertTrue(groups_add_member($group2->id, $user2->id));

        $id = $cohortplugin->add_instance($course1, array('customint1' => $cohort1->id, 'customint2' => $group1a->id));
        $cohortinstance1 = $DB->get_record('enrol', array('id' => $id));

        $id = $cohortplugin->add_instance($course1, array('customint1' => $cohort2->id, 'customint2' => $group1b->id));
        $cohortinstance2 = $DB->get_record('enrol', array('id' => $id));

        $id = $cohortplugin->add_instance($course2, array('customint1' => $cohort3->id, 'customint2' => $group2->id));
        $cohortinstance3 = $DB->get_record('enrol', array('id' => $id));

        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(1, $DB->count_records('groups_members', array()));

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user1->id);
        cohort_add_member($cohort3->id, $user1->id);

        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(1, $DB->count_records('groups_members', array()));

        cohort_add_member($cohort1->id, $user3->id);
        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('groups_members', array()));
        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user3->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance1->id)));

        cohort_add_member($cohort2->id, $user2->id);
        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('groups_members', array()));
        $this->assertFalse($DB->record_exists('groups_members', array('groupid' => $group2->id, 'userid' => $user2->id,
            'component' => 'enrol_groupsync')));
        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group2->id, 'userid' => $user2->id)));

        $manualplugin->enrol_user($maninstance1, $user1->id, $studentrole->id);
        $this->assertEquals(4, $DB->count_records('role_assignments', array()));
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('groups_members', array()));
        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user1->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance1->id)));

        $manualplugin->unenrol_user($maninstance1, $user1->id);
        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('groups_members', array()));
        $this->assertFalse($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user1->id)));

        cohort_remove_member($cohort1->id, $user3->id);
        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(1, $DB->count_records('groups_members', array()));
        $this->assertFalse($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user3->id)));

        cohort_remove_member($cohort2->id, $user2->id);
        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(1, $DB->count_records('groups_members', array()));
        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group2->id, 'userid' => $user2->id)));

        cohort_add_member($cohort1->id, $user3->id);
        cohort_add_member($cohort2->id, $user2->id);
        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('groups_members', array()));
        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user3->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance1->id)));

        cohort_delete_cohort($cohort1);
        $this->assertEquals(3, $DB->count_records('role_assignments', array()));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(1, $DB->count_records('groups_members', array()));
        $this->assertFalse($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user3->id)));
    }

    /**
     * Test explicit sync via enrol_groupsync_sync()
     */
    public function test_sync() {
        global $DB;
        $this->resetAfterTest();

        // Setup a few courses and categories.

        $this->disable_plugin();

        $cohortplugin = enrol_get_plugin('groupsync');
        $manualplugin = enrol_get_plugin('manual');

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->assertNotEmpty($teacherrole);
        $managerrole = $DB->get_record('role', array('shortname' => 'manager'));
        $this->assertNotEmpty($managerrole);

        $course1 = $this->getDataGenerator()->create_course(array());
        $course2 = $this->getDataGenerator()->create_course(array());
        $course3 = $this->getDataGenerator()->create_course(array());
        $course4 = $this->getDataGenerator()->create_course(array());
        $maninstance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $maninstance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $maninstance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'manual'), '*', MUST_EXIST);

        $group1a = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group1b = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course2->id));

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        $id = $cohortplugin->add_instance($course1, array('customint1' => $cohort1->id, 'customint2' => $group1a->id));
        $cohortinstance1 = $DB->get_record('enrol', array('id' => $id));

        $id = $cohortplugin->add_instance($course1, array('customint1' => $cohort2->id, 'customint2' => $group1b->id));
        $cohortinstance2 = $DB->get_record('enrol', array('id' => $id));

        $id = $cohortplugin->add_instance($course2, array('customint1' => $cohort3->id, 'customint2' => $group2->id));
        $cohortinstance3 = $DB->get_record('enrol', array('id' => $id));

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user3->id);
        cohort_add_member($cohort2->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort3->id, $user1->id);

        $manualplugin->enrol_user($maninstance1, $user1->id, $studentrole->id);
        $manualplugin->enrol_user($maninstance1, $user4->id, $teacherrole->id);
        $manualplugin->enrol_user($maninstance1, $user3->id, $managerrole->id);
        $manualplugin->enrol_user($maninstance2, $user1->id, $studentrole->id);
        $manualplugin->enrol_user($maninstance2, $user2->id, $studentrole->id);

        $this->assertTrue(groups_add_member($group2->id, $user2->id));

        $this->assertEquals(5, $DB->count_records('role_assignments', array()));
        $this->assertEquals(5, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(1, $DB->count_records('groups_members', array()));

        $this->enable_plugin();

        enrol_groupsync_sync($course1->id, false);

        $this->assertEquals(5, $DB->count_records('role_assignments', array()));
        $this->assertEquals(5, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('groups_members', array()));

        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user1->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance1->id)));
        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group1b->id, 'userid' => $user1->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance2->id)));
        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user3->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance1->id)));

        enrol_groupsync_sync(null, false);
        $this->assertEquals(5, $DB->count_records('role_assignments', array()));
        $this->assertEquals(5, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(5, $DB->count_records('groups_members', array()));

        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group2->id, 'userid' => $user1->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance3->id)));

        $DB->delete_records('cohort_members', array('userid' => $user1->id));

        enrol_groupsync_sync($course1->id, false);

        $this->assertEquals(5, $DB->count_records('role_assignments', array()));
        $this->assertEquals(5, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('groups_members', array()));

        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group2->id, 'userid' => $user1->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance3->id)));

        enrol_groupsync_sync(null, false);

        $this->assertEquals(5, $DB->count_records('role_assignments', array()));
        $this->assertEquals(5, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('groups_members', array()));

        $this->assertTrue($DB->record_exists('groups_members', array('groupid' => $group1a->id, 'userid' => $user3->id,
            'component' => 'enrol_groupsync', 'itemid' => $cohortinstance1->id)));
    }
}
