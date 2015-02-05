<?php

include_once($CFG->dirroot . '/course/lib.php');
include_once($CFG->libdir . '/coursecatlib.php');

class block_subordinate_courses extends block_list {
    function init() {
        $this->title = get_string('pluginname', 'block_subordinate_courses');
    }

    function has_config() {
        return false;
    }

    private function get_subordinates() {
        global $USER, $DB;

        $manager_field = $DB->get_record('user_info_field', array('shortname' => 'manager'));

        return $DB->get_records_sql("SELECT user.* FROM {user} user INNER JOIN {user_info_data} info_data ON info_data.userid = user.id

            WHERE info_data.fieldid = :fieldid AND  info_data.data = :data",
            array('fieldid' => $manager_field->id, 'data' => $USER->username));
    }

    private function get_datecourse_for_course($cid) {
        static $courses = array();

        if (!isset($courses[$cid])) {
            global $DB;
            // Several subordinates may be enrolled in the same datecourse - let's cache it to avoid hitting the DB more than neccesary
            $courses[$cid] = $DB->get_record('meta_datecourse', array("courseid" => $cid));
        }

        return $courses[$cid];
    }

    function get_content() {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
        $PAGE->requires->jquery();
        $PAGE->requires->js("/blocks/subordinate_courses/core.js");

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';
        $icon  = '<img src="' . $OUTPUT->pix_url('i/course') . '" class="icon" alt="" />';
        
        foreach ($this->get_subordinates() as $user) {
            $output = "<div class='subordinate_user expandable'><h3>" . $user->username . "</h3>";
            
            if ($courses = enrol_get_all_users_courses($user->id, false, null, 'visible DESC, fullname ASC')) {
                array_walk($courses, function ($course) use ($DB){
                    $datecourse = $this->get_datecourse_for_course($course->id);
                    
                    if ($datecourse) {   
                        $course->date = $datecourse->startdate;
                        $course->metaid = $datecourse->metaid;
                    }
                });
                $years = array();

                foreach ($courses as $c) {
                    @$date = date("Y",$c->date);
                    $years[$date][] = $c;
                }

                ksort($years);

                foreach ($years as $year => $courses) {
                    $output .= "<div class='year expandable indent'><h3>" . $year . "</h3>";

                    foreach ($courses as $course) {
                        $coursecontext =  context_course::instance($course->id);
                        $linkcss = $course->visible ? "" : " class=\"dimmed\" ";
                        
                        $output .="<div class='meta_course_block'><a $linkcss title=\""
                            . format_string($course->shortname, true, array('context' => $coursecontext))."\" ".
                            "href=\"$CFG->wwwroot/course/view.php?id=$course->id\">"
                            .$icon. format_string($course->fullname , true, array('context' => context_course::instance($course->id))) . "</a>";


                        if (isset($course->date)) {
                             $output .= "<div class='meta_info'>
                                            <span class='meta_course_date'> " . get_string("date"). ":  ".date("Y-m-d", $course->date)."</span>
                                            <span class='meta_course_details'><a href='". $CFG->wwwroot . "/blocks/metacourse/view_metacourse.php?id=".$course->metaid."'>Details</a></span>
                                        </div>";
                        } else {
                           
                        }
                        $output .= '</div>';
                    }



                    $output .= '</div>';
                }
            } else {
                $output .= '<div class="indent"><span class="no-results">No courses</span></div>';
            }
            $output .= '</div>';


            $this->content->items[] = $output;
        }

        return $this->content;
    }

    /**
     * Returns the role that best describes the course list block.
     *
     * @return string
     */
    public function get_aria_role() {
        return 'navigation';
    }
}


