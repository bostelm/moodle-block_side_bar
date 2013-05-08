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
 * @see        block_site_main_menu
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @copyright  2011 onwards Justin Filip
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_side_bar extends block_list {
    public function init() {
        global $CFG;

        $this->title = get_string('pluginname', 'block_side_bar');

        // Make sure the global section start value is set.
        if (!isset($CFG->block_side_bar_section_start)) {
            set_config('block_side_bar_section_start', 1000);
        }
    }

    /**
     * Parent class version of this function simply returns NULL This should be implemented by the derived class to return the content object.
     *
     * @return object The content object
     */
    public function get_content() {
        global $USER, $CFG, $DB, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items  = array();
        $this->content->icons  = array();
        $this->content->footer = '';

        if (empty($this->instance)) {
            if (!isset($this->content)) {
                $this->content = new stdClass();
            }
            return $this->content;
        }

        if (!isset($this->config->title)) {
            if (!isset($this->config)) {
                $this->config = new stdClass();
            }
            $this->config->title = '';
        }

        $course = $this->page->course;
        require_once($CFG->dirroot.'/course/lib.php');
        $context   = context_course::instance($course->id);
        $isediting = $this->page->user_is_editing() && has_capability('moodle/course:manageactivities', $context);

        $section_start = $CFG->block_side_bar_section_start;

        // Create a new section for this block (if necessary).
        if (empty($this->config->section)) {
            require_once($CFG->dirroot.'/blocks/side_bar/locallib.php');
            if (null == ($section = block_side_bar_create_section($course))) {
                return $this->content;
            }

            $this->config->section    = $section->section;
            $this->config->section_id = $section->id;
            parent::instance_config_commit();

        } else {
            if (empty($this->config->section_id)) {
                $params = array(
                    'course'  => $course->id,
                    'section' => $this->config->section
                );
                $section = $DB->get_record('course_sections', $params);

                $this->config->section_id = $section->id;
                parent::instance_config_commit();
            } else {
                $section = $DB->get_record('course_sections', array('id' => $this->config->section_id));
                if (empty($section)) {
                    require_once($CFG->dirroot.'/blocks/side_bar/locallib.php');
                    if (null == ($section = block_side_bar_create_section($course))) {
                        return $this->content;
                    }

                    $this->config->section    = $section->section;
                    $this->config->section_id = $section->id;
                    parent::instance_config_commit();
                }
            }

            // Double check that the section number hasn't been modified by something else.
            // Fixes problem found by Charlotte Owen when moving 'center column' course sections.
            if ($section->section != $this->config->section) {
                $section->section = $this->config->section;
                $DB->update_record('course_sections', $section);
            }
        }

        // extra fast view mode
        $modinfo = get_fast_modinfo($course);
        if (!$isediting) {
            if (!empty($modinfo->sections[$this->config->section])) {
                $options = array('overflowdiv' => true);
                foreach ($modinfo->sections[$this->config->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }

                    list($content, $instancename) = get_print_section_cm_text($cm, $course);

                    if (!($url = $cm->get_url())) {
                        $this->content->items[] = $content;
                        $this->content->icons[] = '';
                    } else {
                        $linkcss = $cm->visible ? '' : ' class="dimmed" ';
                        // Accessibility: incidental image - should be empty Alt text
                        $icon = '<img src="'.$cm->get_icon_url().'" class="icon" alt="" />&nbsp;';
                        $this->content->items[] = '<a title="'.$cm->modplural.'" '.$linkcss.' '.$cm->extra.' href="'.
                                                  $url.'">'.$icon.$instancename.'</a>';
                    }
                }
            }

            return $this->content;
        }

        // slow & hacky editing mode
        $ismoving = ismoving($course->id);

        if (!$cs = $DB->get_record('course_sections', array('section' => $this->config->section, 'course' => $course->id))) {
            debugging('Could not get course section record for section '.$this->config->section, DEBUG_DEVELOPER);
            return $this->content;
        }

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info($this->config->section);

        $modnames = get_module_types_names();

        $groupbuttons     = $course->groupmode;
        $groupbuttonslink = (!$course->groupmodeforce);

        if ($ismoving) {
            $strmovehere = get_string('movehere');
            $strmovefull = strip_tags(get_string('movefull', '', "'$USER->activitycopyname'"));
            $strcancel= get_string('cancel');
            $stractivityclipboard = $USER->activitycopyname;
        }

        // Casting $course->modinfo to string prevents one notice when the field is null
        $editbuttons = '';

        if ($ismoving) {
            $this->content->icons[] = '<img src="'.$OUTPUT->pix_url('t/move').'" class="iconsmall" alt="" />';
            $this->content->items[] = $USER->activitycopyname.'&nbsp;(<a href="'.$CFG->wwwroot.'/course/mod.php?'.
                                      'cancelcopy=true&amp;sesskey='.sesskey().'">'.$strcancel.'</a>)';
        }

        if (!empty($modinfo->sections[$this->config->section])) {
            $options = array('overflowdiv' => true);
            foreach ($modinfo->sections[$this->config->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if (!$mod->uservisible) {
                    continue;
                }

                if (!$ismoving) {
                    if ($groupbuttons) {
                        if (! $mod->groupmodelink = $groupbuttonslink) {
                            $mod->groupmode = $course->groupmode;
                        }

                    } else {
                        $mod->groupmode = false;
                    }
                    $editbuttons = '<div class="buttons">'.make_editing_buttons($mod, true, true).'</div>';
                } else {
                    $editbuttons = '';
                }
                if ($mod->visible || has_capability('moodle/course:viewhiddenactivities', $context)) {
                    if ($ismoving) {
                        if ($mod->id == $USER->activitycopy) {
                            continue;
                        }
                        $this->content->items[] = '<a title="'.$strmovefull.'" href="'.$CFG->wwwroot.'/course/mod.php'.
                                                  '?moveto='.$mod->id.'&amp;sesskey='.sesskey().'"><img style="height'.
                                                  ':16px; width:80px; border:0px" src="'.$OUTPUT->pix_url('movehere').
                                                  '" alt="'.$strmovehere.'" /></a>';
                        $this->content->icons[] = '';
                    }
                    list($content, $instancename) = get_print_section_cm_text($modinfo->cms[$modnumber], $course);
                    $linkcss = $mod->visible ? '' : ' class="dimmed" ';

                    if (!($url = $mod->get_url())) {
                        $this->content->items[] = $content.$editbuttons;
                        $this->content->icons[] = '';
                    } else {
                        // Accessibility: incidental image - should be empty Alt text
                        $icon = '<img src="'.$mod->get_icon_url().'" class="icon" alt="" />&nbsp;';
                        $this->content->items[] = '<a title="'.$mod->modfullname.'" '.$linkcss.' '.$mod->extra.
                                                  ' href="'.$url.'">'.$icon.$instancename.'</a>'.$editbuttons;
                    }
                }
            }
        }

        if ($ismoving) {
            $this->content->items[] = '<a title="'.$strmovefull.'" href="'.$CFG->wwwroot.'/course/mod.php?'.
                                      'movetosection='.$section->id.'&amp;sesskey='.sesskey().'"><img style="height'.
                                      ':16px; width:80px; border:0px" src="'.$OUTPUT->pix_url('movehere').'" alt="'.
                                      $strmovehere.'" /></a>';
            $this->content->icons[] = '';
        }

        if (!empty($modnames)) {
            $this->content->footer = print_section_add_menus($course, $this->config->section, $modnames, true, true, $this->config->section);
            // Replace modchooser with dropdown
            $this->content->footer = str_replace('hiddenifjs addresourcedropdown', 'visibleifjs addresourcedropdown', $this->content->footer);
            $this->content->footer = str_replace('visibleifjs addresourcemodchooser', 'hiddenifjs addresourcemodchooser', $this->content->footer);
        } else {
            $this->content->footer = '';
        }

        return $this->content;
    }

    /**
     * Delete everything related to this instance if you have been using persistent storage other than the configdata field.
     *
     * @return bool Status result
     */
    public function instance_delete() {
        global $CFG, $DB;

        if (empty($this->instance) || !isset($this->config->section)) {
            return true;
        }

        // Cleanup the section created by this block and any course modules.
        $sql = "SELECT cm.id, cm.instance, mm.name AS modname
                  FROM {course_sections} cs
            INNER JOIN {course_modules} cm ON cm.section = cs.id
            INNER JOIN {modules} mm ON mm.id = cm.module
                 WHERE cs.section = :section
                       AND cs.course = :course";

        $params = array(
            'section' => $this->config->section,
            'course'  => $this->page->course->id
        );

        if ($mods = $DB->get_records_sql($sql, $params)) {
            foreach ($mods as $mod) {
                $mod_lib = $CFG->dirroot.'/mod/'.$mod->modname.'/lib.php';
                if (file_exists($mod_lib)) {
                    require_once($mod_lib);

                    $delete_func = $mod->modname.'_delete_instance';

                    if (function_exists($delete_func)) {
                        $delete_func($mod->instance);
                    }
                }
            }
        }

        // Delete the course section for this block instance
        $DB->delete_records('course_sections_availability', array('coursesectionid' => $this->config->section_id));
        $DB->delete_records('course_sections_avail_fields', array('coursesectionid' => $this->config->section_id));
        $DB->delete_records('course_sections', array('id' => $this->config->section_id));
        rebuild_course_cache($this->page->course->id, true);

        // if (!$section = $DB->get_record('course_sections', $params)) {
        //  return true;
        // }

        // if ($modules = $DB->get_recordset('course_modules', array('section' => $section->id))) {
        //  $mods = array();

        //  foreach($modules as $module) {
        //      $modid = $module->module;

        //      if (!isset($mods[$modid])) {
        //          $mods[$modid] = $DB->get_field('modules', 'name', array('id' => $modid));
        //      }

        //      $mod_lib = $CFG->dirroot.'/mod/'.$mods[$modid].'/lib.php';

        //      if (file_exists($mod_lib)) {
        //          require_once($mod_lib);

        //          $delete_func = $mods[$modid].'_delete_instance';

        //          if (function_exists($delete_func)) {
        //              $delete_func($module->instance);
        //          }
        //      }
        //  }
        // }
    }

    /**
     * This function is called on your subclass right after an instance is loaded. Use this function to act on instance data just
     * after it's loaded and before anything else is done. For instance: if your block will have different title's depending on
     * location (site, course, blog, etc)
     */
    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = $this->config->title;
        }
    }

    /**
     * Subclasses should override this and return true if the subclass block has a config_global.html file.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Are you going to allow multiple instances of each block? If yes, then it is assumed that the block WILL USE per-instance configuration.
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Which page types this block may appear on.
     *
     * The information returned here is processed by the
     * {@link blocks_name_allowed_in_format()} function. Look there if you need
     * to know exactly how this works.
     *
     * Default case: everything except mod and tag.
     *
     * @return array page-type prefix => true/false.
     */
    public function applicable_formats() {
        return array(
            'site-index'  => true,
            'course-view' => true
        );
    }

    /**
     * Code executed after a course is restored with this block present in the restore data. Allows for setting the new course
     * section ID for the restored course as part of this block's instance configuration.
     *
     * @return bool Status indicator
     */
    public function after_restore() {
        // Get the correct course_sections record ID for the new course
        $section = $DB->get_record('course_sections', 'course', $this->instance->pageid, 'section', $this->config->section);

        if (!empty($section->id)) {
            $this->config->section_id = $section->id;
            parent::instance_config_commit();
        }

        return true;
    }
}
