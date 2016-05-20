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
 * Adds new instance of enrol_groupsync to specified course.
 *
 * @package    enrol_groupsync
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/groupsync/edit_form.php");
require_once("$CFG->dirroot/enrol/groupsync/locallib.php");
require_once("$CFG->dirroot/group/lib.php");

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/groupsync:config', $context);

$PAGE->set_url('/enrol/groupsync/edit.php', array('courseid' => $course->id));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));
if (!enrol_is_enabled('groupsync')) {
    redirect($returnurl);
}

$enrol = enrol_get_plugin('groupsync');

$instance = new stdClass();
$instance->courseid   = $course->id;
$instance->customint1 = ''; // Cohort id.
$instance->customint2 = ''; // Group id.

// Try and make the manage instances node on the navigation active.
navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id' => $course->id)));

$mform = new enrol_groupsync_edit_form(null, array($instance, $enrol, $course));

if ($mform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $mform->get_data()) {
    $enrol->add_instance($course, array('name' => $data->name, 'status' => ENROL_INSTANCE_ENABLED,
        'customint1' => $data->customint1, 'customint2' => $data->customint2));
    enrol_groupsync_sync($course->id);
    redirect($returnurl);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_groupsync'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
