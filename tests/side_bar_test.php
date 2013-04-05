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
 * Allows for arbitrarily adding resources or activities to extra (non-standard) course sections with instance
 * configuration for the block title.
 *
 * @package    block_side_bar
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @copyright  2013 onwards Justin Filip
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/blocks/side_bar/locallib.php');

/**
 * @group block_side_bar
 */
class blockSideBarTestcase extends advanced_testcase {
    /** @var phpunit_data_generator A reference to the data generator object for creating test data */
    private $dg;

    /**
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp() {
        parent::setUp();

        // $this->resetAfterTest();

        $dg = $this->getDataGenerator();
    }

    /**
     * Validate that the course Side Bar block section was created with the cortect settings.
     *
     * @param object $section A course_sections record with, at minimum the name, summary, section, and visible properties.
     * @param int $sectionnum The section number that should be present.
     */
    private function validate_sidebar_course_section($section, $sectionnum) {
        $this->assertEquals(get_string('sidebar', 'block_side_bar'), $section->name);
        $this->assertEquals(get_string('sectionsummary', 'block_side_bar'), $section->summary);
        $this->assertEquals($sectionnum, $section->section);
        $this->assertEquals(1, $section->visible);
    }

    /**
     * Create a new sidebar course section and set it up with the required values
     *
     * @param int $courseid The course record ID for the section to belong to.
     * @param int $sectionnum The section number that should be present.
     */
    private function create_sidebar_course_section($courseid, $sectionnum) {
        global $DB;

        $dg = $this->getDataGenerator();
        $dg->create_course_section(array('course' => $courseid, 'section' => 1000));
        $section = $DB->get_record('course_sections', array('course' => $courseid, 'section' => 1000), 'id, section, name, visible');
        $section->name          = get_string('sidebar', 'block_side_bar');
        $section->summary       = get_string('sectionsummary', 'block_side_bar');
        $section->summaryformat = FORMAT_HTML;
        $section->visible       = true;
        $DB->update_record('course_sections', $section);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_null() {
        block_side_bar_create_section(null);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_int() {
        block_side_bar_create_section(1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_string() {
        block_side_bar_create_section('string');
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_array() {
        block_side_bar_create_section(array(1, 2, 3));
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains no orphaned
     * sections (orphaned being sections that exist beyond the number of sections configured for the course).
     */
    public function test_create_section_works_with_no_orphaned_sections() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $page = $dg->create_module('page', array('course' => $course->id, 'section' => 1));

        // Setup the containing course section
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(2, $sectioninfo->section);
        $this->assertEquals(3, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 2);
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains orphaned sections but
     * no activity modules within an orphaned section (orphaned being sections that exist beyond the number of sections configured for
     * the course).
     */
    public function test_create_section_works_with_empty_orphaned_sections() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 3));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(4, $sectioninfo->section);
        $this->assertEquals(5, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 4);
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains one orphaned section
     * and that section contains an activity module (orphaned being sections that exist beyond the number of sections configured for
     * the course).
     */
    public function test_create_section_works_with_one_non_empty_orphaned_section() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 2));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(3, $sectioninfo->section);
        $this->assertEquals(4, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 3);
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains multiple orphaned
     * sections but only the "highest" orphaned section contains an activity module (orphaned being sections that exist beyond the
     * number of sections configured for the course).
     */
    public function test_create_section_works_with_multiple_orphaned_sections_high_not_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 2));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 3));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 4));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(5, $sectioninfo->section);
        $this->assertEquals(6, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 5);
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains multiple orphaned
     * sections but only the "lowest" orphaned section contains an activity module (orphaned being sections that exist beyond the
     * number of sections configured for the course).
     */
    public function test_create_section_works_with_multiple_orphaned_sections_low_not_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 3));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 4));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 4));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(5, $sectioninfo->section);
        $this->assertEquals(6, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 5);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_null() {
        block_side_bar_migrate_old_section(null, 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_int() {
        block_side_bar_migrate_old_section(1, 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_string() {
        block_side_bar_migrate_old_section('string', 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_array() {
        block_side_bar_migrate_old_section(array(1, 2, 3), 1);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_null() {
        block_side_bar_migrate_old_section(new stdClass(), null);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_int_zero() {
        block_side_bar_migrate_old_section(new stdClass(), 0);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_int_negative() {
        block_side_bar_migrate_old_section(new stdClass(), -1);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_string() {
        block_side_bar_migrate_old_section(new stdClass(), 'string');
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_array() {
        block_side_bar_migrate_old_section(new stdClass(), array(1, 2, 3));
    }

    /**
     * Validate that the course section we wish to migrate not existing returns the appropriate result.
     */
    public function test_migrate_old_section_invalid_course_section_returns_null() {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $this->assertEquals(null, block_side_bar_migrate_old_section($course, 1000));
    }

    /**
     * Test that the old sidebar course section migrates into the new position when there are no "fillter" sections that have been created.
     */
    public function test_migrate_old_section_with_no_filler() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        $this->create_sidebar_course_section($course->id, 1000);

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11);
    }

    /**
     * Test that the old sidebar course section migrates into the new position when there are "fillter" course sections that have
     * been created. Filler sections are sections created between the course section count value and the section number of the
     * sidebar block section (in this case sections 11 - 999). None of these filler sections contain activity module instances.
     */
    public function test_migrate_old_section_with_empty_filler() {
        global $DB;

        if (!defined('PHPUNIT_LONGTEST') || PHPUNIT_LONGTEST != true) {
            $this->markTestSkipped('Set PHPUNIT_LONGTEST to true to execute this test');
        }

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 999; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an "old" style sidebar course section
        $this->create_sidebar_course_section($course->id, 1000);

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11);
    }


    /**
     * Test that the old sidebar course section migrates into the new position when there are "fillter" course sections that have
     * been created. Filler sections are sections created between the course section count value and the section number of the
     * sidebar block section (in this case sections 11 - 999). Every 100th filler section contains an activity module instance
     * (i.e. sections 100,200, ... ,900). The sidebar course section (1000) also contains an activity.
     */
    public function test_migrate_old_section_with_nonempty_filler() {
        global $DB;

        if (!defined('PHPUNIT_LONGTEST') || PHPUNIT_LONGTEST != true) {
            $this->markTestSkipped('Set PHPUNIT_LONGTEST to true to execute this test');
        }

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 999; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));

            // Populate each 100th section with an activity module
            if ($i % 100 == 0) {
                $page = $dg->create_module('page', array('course' => $course->id), array('section' => $i));
            }
        }

        // Create an "old" style sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 1000);
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 1000));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(20, $sectioninfo->section);
        $this->assertEquals(20, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 20);
    }
}
