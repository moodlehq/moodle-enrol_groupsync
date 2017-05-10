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
 * Provides the {@link enrol_groupsync_edit_form} class.
 *
 * @package    enrol_groupsync
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Enrolment instance edit form.
 *
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_groupsync_edit_form extends moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        global $DB;

        $mform  = $this->_form;

        list($instance, $plugin, $course) = $this->_customdata;
        $coursecontext = context_course::instance($course->id);

        $mform->addElement('header', 'general', get_string('pluginname', 'enrol_groupsync'));

        $nameattribs = array('size' => '20', 'maxlength' => '255');
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), $nameattribs);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $cohorts = array('' => get_string('choosedots'));
        list($sqlparents, $params) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids());
        $sql = "SELECT id, name, idnumber, contextid
                  FROM {cohort}
                 WHERE contextid $sqlparents
              ORDER BY name ASC, idnumber ASC";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $c) {
            $context = context::instance_by_id($c->contextid);
            if (!has_capability('moodle/cohort:view', $context)) {
                continue;
            }
            $cohorts[$c->id] = format_string($c->name);
        }
        $rs->close();
        $mform->addElement('select', 'customint1', get_string('cohort', 'cohort'), $cohorts);
        $mform->addRule('customint1', get_string('required'), 'required', null, 'client');

        $groups = array('' => get_string('choosedots'));
        foreach (groups_get_all_groups($course->id) as $group) {
            $groups[$group->id] = format_string($group->name, true, array('context' => $coursecontext));
        }
        $mform->addElement('select', 'customint2', get_string('addgroup', 'enrol_groupsync'), $groups);
        $mform->addRule('customint2', get_string('required'), 'required', null, 'client');

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, get_string('addinstance', 'enrol'));

        $this->set_data($instance);
    }

    /**
     * Validates the form data.
     *
     * @param array $data submitted form data
     * @param array $files not used here
     * @return array errors
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $params = array('customint2' => $data['customint2'], 'customint1' => $data['customint1'], 'courseid' => $data['courseid']);
        if ($DB->record_exists_select('enrol', "customint1 = :customint1 AND customint2 = :customint2 AND courseid = :courseid
                AND enrol = 'groupsync'", $params)) {
            $errors['customint2'] = get_string('instanceexists', 'enrol_groupsync');
        }

        return $errors;
    }
}
