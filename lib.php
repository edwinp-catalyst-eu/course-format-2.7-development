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
 * This file contains main class for the course format TURFORLAG
 *
 * @since     Moodle 2.0
 * @package   format_turforlag
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the turforlag course format
 *
 * @package    format_turforlag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_turforlag extends format_base {

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else if ($section->section == 0) {
            return get_string('section0name', 'format_topics');
        } else {
            return get_string('topic').' '.$section->section; // TODO
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (!empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // if section is specified in course/view.php, make sure it is expanded in navigation
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // check if there are callbacks to extend course navigation
        parent::extend_course_navigation($navigation, $node);
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $current = -1;
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
                if ($this->is_section_current($section)) {
                    $current = $number;
                }
            }
        }
        return array('sectiontitles' => $titles, 'current' => $current, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array('search_forums', 'news_items', 'calendar_upcoming', 'recent_activity')
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Topics format uses the following options:
     * - coursedisplay
     * - numsections
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'numsections' => array(
                    'default' => $courseconfig->numsections,
                    'type' => PARAM_INT,
                ),
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseconfig = get_config('moodlecourse');
            $max = $courseconfig->maxsections;
            if (!isset($max) || !is_numeric($max)) {
                $max = 52;
            }
            $sectionmenu = array();
            for ($i = 0; $i <= $max; $i++) {
                $sectionmenu[$i] = "$i";
            }
            $courseformatoptionsedit = array(
                'numsections' => array(
                    'label' => new lang_string('numberweeks'),
                    'element_type' => 'select',
                    'element_attributes' => array($sectionmenu),
                ),
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                )
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        $elements = parent::create_edit_form_elements($mform, $forsection);

        // Increase the number of sections combo box values if the user has increased the number of sections
        // using the icon on the course page beyond course 'maxsections' or course 'maxsections' has been
        // reduced below the number of sections already set for the course on the site administration course
        // defaults page.  This is so that the number of sections is not reduced leaving unintended orphaned
        // activities / resources.
        if (!$forsection) {
            $maxsections = get_config('moodlecourse', 'maxsections');
            $numsections = $mform->getElementValue('numsections');
            $numsections = $numsections[0];
            if ($numsections > $maxsections) {
                $element = $mform->getElement('numsections');
                for ($i = $maxsections+1; $i <= $numsections; $i++) {
                    $element->addOption("$i", $i);
                }
            }
        }
        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'topics', we try to copy options
     * 'coursedisplay', 'numsections' and 'hiddensections' from the previous format.
     * If previous course format did not have 'numsections' option, we populate it with the
     * current number of sections
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        global $DB;
        if ($oldcourse !== null) {
            $data = (array)$data;
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    } else if ($key === 'numsections') {
                        // If previous format does not have the field 'numsections'
                        // and $data['numsections'] is not set,
                        // we fill it with the maximum section number from the DB
                        $maxsection = $DB->get_field_sql('SELECT max(section) from {course_sections}
                            WHERE course = ?', array($this->courseid));
                        if ($maxsection) {
                            // If there are no sections, or just default 0-section, 'numsections' will be set to default
                            $data['numsections'] = $maxsection;
                        }
                    }
                }
            }
        }
        return $this->update_format_options($data);
    }
}

function tur_get_course_intro_image($contextid) {

    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_resource', 'content', 0);

    if ($file = end($files)) {
        $filename = $file->get_filename();
        if ($filename != '.') {
            return moodle_url::make_file_url('/pluginfile.php',
                    "/{$contextid}/mod_resource/content/0/{$filename}");
        }
    }
}

function tur_get_course_intro($courseid) {
    global $DB;

    return $DB->get_field('resource', 'intro',
            array('course' => $courseid, 'name' => '_introduction'));
}

function turforlag_tabcontent_background_style() {

    $inlinestyle = '';
    $styles = array(
        'background-image' => turforlag_tabcontent_background_img(),
        'background-position' => 'right bottom',
        'background-repeat' => 'no-repeat'
    );
    foreach ($styles as $stylename => $stylevalue) {
        $inlinestyle .= $stylename . ': ' . $stylevalue . ';';
    }

    return $inlinestyle;
}

function turforlag_tabcontent_background_img() {

    // temporary development url
    return 'url(http://staging27.turteori.dk/pluginfile.php/3280/mod_resource/content/1/backgroundstruck-2.png);';
}

function tur_course_structure($courseid) {
    global $DB, $USER;

    $sections = $DB->get_records_select('course_sections',
            "course = ? and visible = ? AND summary <> ''",
            array($courseid, 1), 'section', 'section, id, summary, sequence');
    $sections = array_values($sections);

    $structure = array();
    foreach ($sections as $sectionid => $section) {
        $structure[$sectionid]['section'] = $section->summary;
        if ($sectionid == 0) {
            list($sequencesql, $params) = $DB->get_in_or_equal(explode(',', $section->sequence), SQL_PARAMS_NAMED);
            $sql = "SELECT c.id AS intromodulecontextid
                      FROM {context} c
                      JOIN {course_modules} cm ON cm.id = c.instanceid
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {resource} r ON r.id = cm.instance
                     WHERE c.contextlevel = :contextlevel
                       AND m.name = :modulename
                       AND r.name = :resourcename
                       AND c.instanceid {$sequencesql}";
            $params['contextlevel'] = CONTEXT_MODULE;
            $params['modulename'] = 'resource';
            $params['resourcename'] = '_introduction';

            $structure[0]['courseid'] = $courseid;
            if ($intromodulecontextid = $DB->get_field_sql($sql, $params)) {
                $structure[0]['intromodulecontextid'] = $intromodulecontextid;
            }
            continue;
        }

        if (isset($section->sequence) && $section->sequence) {

            list($sequencesql, $params) = $DB->get_in_or_equal(explode(',', $section->sequence));

            $sql = "SELECT cm.id, cm.indent,
                            l.name AS labelname,
                            q.name AS quizname,
                            qa.attempt AS quizattempt,
                            CASE
                                WHEN qa.state IS NOT NULL THEN
                                    qa.state
                                WHEN qa.state IS NULL AND q.name IS NOT NULL THEN
                                    'unstarted'
                            END AS quizstate,
                            s.name AS scormname,
                            CASE
                                WHEN sst.value IS NOT NULL THEN
                                    sst.value
                                WHEN sst.value IS NULL AND s.name IS NOT NULL THEN
                                    'unstarted'
                            END AS scormstate
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                 LEFT JOIN {label} l ON (l.id = cm.instance AND m.name = 'label')
                 LEFT JOIN {quiz} q ON (q.id = cm.instance AND m.name = 'quiz')
                 LEFT JOIN {quiz_attempts} qa ON (qa.quiz = q.id AND qa.userid = {$USER->id})
                 LEFT JOIN {scorm} s ON (s.id = cm.instance AND m.name = 'scorm')
                 LEFT JOIN {scorm_scoes_track} sst ON (sst.scormid = s.id
                                AND sst.element = 'cmi.core.lesson_status'
                                AND sst.userid = {$USER->id})
                     WHERE cm.id {$sequencesql}
                       AND cm.visible = 1 ";

            $sql .=  " ORDER BY CASE cm.id ";
            $sequencearray = explode(',', $section->sequence);
            for ($i = 0; $i < count($sequencearray); $i++) {
                $sql .= 'WHEN ' . $sequencearray[$i] . ' THEN ' . $i . ' ';
            }
            $sql .= 'END';

            $sectionmodules = $DB->get_records_sql($sql, $params);
            $sectionmodules = array_values($sectionmodules);

            for ($i = 0; $i < count($sectionmodules); $i++) {

                switch ($sectionmodules[$i]->indent) {
                    case 0:
                        if ($sectionmodules[$i]->labelname) {
                            $structure[$sectionid]['parts'][$i]['name'] = $sectionmodules[$i]->labelname;
                            $structure[$sectionid]['parts'][$i]['type'] = 'label';
                            $structure[$sectionid]['parts'][$i]['moduleid'] = $sectionmodules[$i]->id;
                        }
                        if ($sectionmodules[$i]->quizname) {
                            $structure[$sectionid]['parts'][$i]['name'] = $sectionmodules[$i]->quizname;
                            $structure[$sectionid]['parts'][$i]['type'] = 'quiz';
                            switch ($sectionmodules[$i]->quizstate) {
                                case 'finished':
                                    $quizstate = 'completed';
                                    break;
                                case 'inprogress':
                                    $quizstate = 'inprogress';
                                    break;
                                default:
                                    $quizstate = 'unstarted';
                                    break;
                            }
                            $structure[$sectionid]['parts'][$i]['status'] = $quizstate;
                            $structure[$sectionid]['parts'][$i]['moduleid'] = $sectionmodules[$i]->id;
                        }
                        if ($sectionmodules[$i]->scormname) {
                            $structure[$sectionid]['parts'][$i]['name'] = $sectionmodules[$i]->scormname;
                            $structure[$sectionid]['parts'][$i]['type'] = 'scorm';
                            switch ($sectionmodules[$i]->scormstate) {
                                case 'completed':
                                    $scormstate = 'completed';
                                    break;
                                case 'incomplete':
                                    $scormstate = 'inprogress';
                                    break;
                                default:
                                    $scormstate = 'unstarted';
                                    break;
                            }
                            $structure[$sectionid]['parts'][$i]['status'] = $scormstate;
                            $structure[$sectionid]['parts'][$i]['moduleid'] = $sectionmodules[$i]->id;
                        }
                        $parent = $i;
                        break;
                    case 1:
                        if (!isset($parent)) {
                            $parent = $i;
                        }
                        if ($sectionmodules[$i]->quizname && isset($structure[$sectionid]['parts'][$parent]['name'])) {
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['name'] = $sectionmodules[$i]->quizname;
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['type'] = 'quiz';
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['status'] = $sectionmodules[$i]->quizstate;
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['moduleid'] = $sectionmodules[$i]->id;
                        }
                        if ($sectionmodules[$i]->scormname && isset($structure[$sectionid]['parts'][$parent]['name'])) {
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['name'] = $sectionmodules[$i]->scormname;
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['type'] = 'scorm';
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['status'] = $sectionmodules[$i]->scormstate;
                            $structure[$sectionid]['parts'][$parent]['modules'][$sectionmodules[$i]->id]['moduleid'] = $sectionmodules[$i]->id;
                        }
                        break;
                }
            }

            if (isset($structure[$sectionid]['parts'])) {
                foreach ($structure[$sectionid]['parts'] as $sectionpartid => $sectionpart) {
                    if (array_key_exists('modules', $sectionpart)) {

                        $inprogressmodules = array();
                        $completedmodules = array();
                        $unstartedmodules = array();

                        foreach ($sectionpart['modules'] as $sectionpartmoduleid => $sectionpartmodule) {
                            switch ($sectionpartmodule['status']) {
                                case 'inprogress':
                                    $inprogressmodules[] = $sectionpartmoduleid;
                                    break;
                                case 'completed':
                                    $completedmodules[] = $sectionpartmoduleid;
                                    break;
                                default:
                                    $unstartedmodules[] = $sectionpartmoduleid;
                                    break;
                            }
                        }

                        if (count($completedmodules) == count($sectionpart['modules'])) {
                            $status = 'completed';
                        } else if (count($unstartedmodules) == count($sectionpart['modules'])) {
                            $status = 'unstarted';
                        } else {
                            $status = 'inprogress';
                        }

                        $structure[$sectionid]['parts'][$sectionpartid]['status'] = $status;
                    }
                }
            }
        }

        foreach ($structure as $section) {

            if (array_key_exists('parts', $section)) {

                $inprogresssections = array();
                $completedsections = array();
                $unstartedsections = array();

                foreach ($section['parts'] as $partid => $part) {
                    switch ($part['status']) {
                        case 'inprogress':
                            $inprogresssections[] = $partid;
                            break;
                        case 'completed':
                            $completedsections[] = $partid;
                            break;
                        default:
                            $unstartedsections[] = $partid;
                            break;
                    }
                }

                if (count($completedsections) == count($section['parts'])) {
                    $status = 'completed';
                } else if (count($unstartedsections) == count($section['parts'])) {
                    $status = 'unstarted';
                } else {
                    $status = 'inprogress';
                }

                $structure[$sectionid]['status'] = $status;
            }
        }
    }

    $structure = array_values($structure);

    return $structure;
}
