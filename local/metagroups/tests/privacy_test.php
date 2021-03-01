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
 * @package    local_metagroups
 * @copyright  2018 Paul Holden (pholden@greenhead.ac.uk)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\request\approved_contextlist,
    \core_privacy\local\request\writer,
    \local_metagroups\privacy\provider;

/**
 * Unit tests for Privacy API
 *
 * @group local_metagroups
 */
class local_metagroups_privacy_testcase extends \core_privacy\tests\provider_testcase {

    /** @var stdClass test course. */
    protected $course1;

    /** @var stdClass test linked course. */
    protected $course2;

    /** @var stdClass test group. */
    protected $group;

    /** @var stdClass test user. */
    protected $user;

    /**
     * Test setup
     *
     * @return void
     */
    protected function setUp() {
        $this->resetAfterTest(true);

        // Create test courses.
        $this->course1 = $this->getDataGenerator()->create_course(['groupmode' => VISIBLEGROUPS]);
        $this->course2 = $this->getDataGenerator()->create_course(['groupmode' => VISIBLEGROUPS]);

        // Enable metacourse enrolment plugin.
        $enabled = enrol_get_plugins(true);
        $enabled['meta'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));

        // Create metacourse enrolment instance.
        $meta = enrol_get_plugin('meta');
        $meta->add_instance($this->course2, ['customint1' => $this->course1->id]);

        // Create a group in parent course.
        $this->group = $this->getDataGenerator()->create_group(['courseid' => $this->course1->id]);

        // Create user, add them to parent course/group.
        $this->user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($this->user->id, $this->course1->id, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $this->group->id, 'userid' => $this->user->id]);
    }

    /**
     * Tests provider get_contexts_for_userid method
     *
     * @return void
     */
    public function test_get_contexts_for_userid() {
        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $this->assertCount(1, $contextlist);

        list($context) = $contextlist->get_contexts();

        $expected = context_course::instance($this->course2->id, MUST_EXIST);
        $this->assertSame($expected, $context);
    }

    /**
     * Tests provider get_contexts_for_userid method when user has no group membership
     *
     * @return void
     */
    public function test_get_contexts_for_userid_no_group_membership() {
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertEmpty($contextlist);
    }

    /**
     * Test provider export_user_data method
     *
     * @return void
     */
    public function test_export_user_data() {
        $this->setUser($this->user);

        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $approvedcontextlist = new approved_contextlist($this->user, 'local_metagroups', $contextlist->get_contextids());

        provider::export_user_data($approvedcontextlist);

        list($context) = $approvedcontextlist->get_contexts();
        $contextpath = [get_string('pluginname', 'local_metagroups'), get_string('groups', 'core_group')];

        $writer = writer::with_context($context);
        $data = $writer->get_data($contextpath);
        $this->assertTrue($writer->has_any_data());

        $this->assertCount(1, $data->groups);
        $this->assertSame($this->group->name, reset($data->groups)->name);
    }
}
