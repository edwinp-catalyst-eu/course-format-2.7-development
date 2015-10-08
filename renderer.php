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
 * Renderer for outputting the turforlag course format.
 *
 * @package format_turforlag
 * @since Moodle 2.3
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for turforlag format.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_turforlag_renderer extends format_section_renderer_base {

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {

        $html = html_writer::start_tag('div', array('id' => 'turforlag_wrapper'));
        $html .= html_writer::start_tag('div', array('id' => 'tabs', 'class' => 'turforlag'));
        return $html;
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {

        $html = html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        return $html;
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the edit controls of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of links with edit controls
     */
    protected function section_edit_controls($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if (has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $controls[] = html_writer::link($url,
                                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marked'),
                                        'class' => 'icon ', 'alt' => get_string('markedthistopic'))),
                                    array('title' => get_string('markedthistopic'), 'class' => 'editing_highlight'));
            } else {
                $url->param('marker', $section->section);
                $controls[] = html_writer::link($url,
                                html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marker'),
                                    'class' => 'icon', 'alt' => get_string('markthistopic'))),
                                array('title' => get_string('markthistopic'), 'class' => 'editing_highlight'));
            }
        }

        return array_merge($controls, parent::section_edit_controls($course, $section, $onsectionpage));
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $course->numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available &&
                    !empty($thissection->availableinfo));
            if (!$showsection) {
                // If the hiddensections option is set to 'show hidden sections in collapsed
                // form', then display the hidden section message - UNLESS the section is
                // hidden by the availability system, which is set to hide the reason.
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section, $course->id);
                }

                continue;
            }

            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $course->numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));

            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php',
                array('courseid' => $course->id,
                      'increase' => true,
                      'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            echo html_writer::link($url, $icon.get_accesshide($straddsection), array('class' => 'increase-sections'));

            if ($course->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php',
                    array('courseid' => $course->id,
                          'increase' => false,
                          'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                echo html_writer::link($url, $icon.get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }

            echo html_writer::end_tag('div');
        } else {
            echo $this->end_section_list();
        }

    }

    public function generate_turforlag_course_format_html($structure) {

        echo html_writer::start_div('', array('id' => 'tabs'));
        echo $this->generate_turforlag_tabs_html($structure);
        echo $this->generate_turforlag_tabcontent_html($structure);
        echo html_writer::end_div();
    }

    public function generate_turforlag_tabs_html($structure) {

        $html = html_writer::start_tag('ul', array('class' => 'turforlag_tabs'));
        foreach ($structure as $sectionid => $section) {
            $link = html_writer::link('#tabs-' . $sectionid, $section['section']);
            $html .= html_writer::tag('li', $link, array('class' => 'progress_red')); // @TODO Dynamic progress class
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }

    public function generate_turforlag_tabcontent_html($structure) {

        $html = '';
        foreach ($structure as $sectionid => $section) {
            $html .= html_writer::start_div('turforlag_cf_content',
                    array('id' => 'tabs-' . $sectionid));
            $html .= html_writer::tag('h3', $section['section']);
            if (array_key_exists('parts', $structure[$sectionid])) {
                $html .= html_writer::start_div('', array('id' => 'subtabs-' . $sectionid, 'class' => 'turforlag_subtabs'));
                $html .= $this->generate_turforlag_subtabs_html($structure, $sectionid);
                $html .= $this->generate_turforlag_subtabcontent_html($structure, $sectionid);
                $html .= html_writer::end_div();
            }
            $html .= html_writer::end_div();
        }

        return $html;
    }

    public function generate_turforlag_subtabs_html($structure, $sectionid) {

        $html = html_writer::start_tag('ul', array('class' => 'turforlag_subtabs'));
        foreach ($structure[$sectionid]['parts'] as $subtabid => $subtab) {
            $link = html_writer::link("#subtabs-{$sectionid}-{$subtabid}", $subtab['name']);
            $html .= html_writer::tag('li', $link, array('class' => 'turforlag_cf_progress_green')); // @TODO Dynamic progress class
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }

    public function generate_turforlag_subtabcontent_html($structure, $sectionid) {

        $html = '';
        foreach ($structure[$sectionid]['parts'] as $subtabid => $subtab) {
            $html .= html_writer::start_div('turforlag_cf_subcontent', array('id' => "subtabs-{$sectionid}-{$subtabid}"));
            $html .= html_writer::start_tag('ul');
            foreach ($structure[$sectionid]['parts'][$subtabid]['modules'] as $moduleid => $module) {
                $url = new moodle_url("/mod/{$module['type']}/view.php", array('id' => $moduleid));
                $link = html_writer::link($url, $module['name']);
                $html .= html_writer::tag('li', $link, array('class' => 'turforlag_cf_progress_red')); // @TODO Dynamic progress class
            }
            $html .= html_writer::end_tag('ul');
            $html .= html_writer::end_div();
        }

        return $html;
    }
}
