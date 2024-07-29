<?php
/**
 * Block Feedback Block
 *
 * @package    block_feedback_block
 * @author     Pratik K Lalan
 * @copyright  2024 Pratik K Lalan <lalan.pratik755@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

defined('MOODLE_INTERNAL') || die();

class block_feedback_block extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_feedback_block');
    }

    public function applicable_formats() {
        return array('site' => true, 'my' => true, 'course-view' => true, 'user-profile' => true);
    }    

    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {
        global $OUTPUT, $DB, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        if (isloggedin() && !isguestuser()) {
            // Check if the user is a site admin or has the capability to add comments
            if (is_siteadmin() || has_capability('block/feedback_block:addfeedback', context_system::instance())) {
                // Render comments form for admins or users with the add comment capability
                $this->content->text = $this->render_comments_form($OUTPUT, $DB, $USER);
            } else {
                // If the user is not an admin or doesn't have the add comment capability,
                // Display default content (course name, comments, etc.)
                $this->content->text = $this->render_default_content($OUTPUT, $DB, $USER);
            }
        } else {
            // If the user is not logged in or is a guest user, display no content
            $this->content->text = '';
        }

        return $this->content;
    }

    protected function render_default_content($OUTPUT, $DB, $USER){
        // Students view for feedback block
        $html = '<div class="default-content" style="max-height: 300px; overflow-y: auto;">';
        
        // Retrieve comments for the logged-in user
        $comments = $DB->get_records('block_feedback', array('userid' => $USER->id));
    
        if ($comments) {
            $html .= '<h6>My Feedbacks</h6>';
    
            foreach ($comments as $comment) {
                // Retrieve course name based on courseid
                $course = $DB->get_record('course', array('id' => $comment->courseid));
    
                if ($course) {
                    // Format the timestamp to display the date and time
                    $timestamp = date('Y-m-d H:i:s', $comment->timestamp);
    
                    // Output the comment information
                    $html .= '<label><i style="color: blue">'. $comment->commentbyfullname .' </i> have provided a feedback for the course <i>'. $course->fullname .'</i></label>';
                    $html .= '<strong>Feedback: </strong><label>'. $comment->comment .'</label></br>';
                    $html .= '<small>Date: '. $timestamp .'</small><hr>';
                }
            }
        } else {
            $html .= '<p>No feedbacks found.</p>';
        }
        $html .= '</div>';
        return $html;
    }
    

    protected function render_comments_form($OUTPUT, $DB, $USER) {
        global $USER;
        // Get the user ID from the URL parameters
        $s_user_id = optional_param('id', 0, PARAM_INT);
        $html = '';

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $comment = trim($_POST['comment']);
            $student_id = (int)$_POST['student_id'];
            $course_id = (int)$_POST['select_course'];
            $comment_by_username = $USER->username;

            if (!empty($student_id) && !empty($comment)) {
                // Insert comment into database
                $comment_data = new stdClass();
                $comment_data->userid = $student_id;
                $comment_data->courseid = $course_id;
                $comment_data->commentbyid = $USER->id;
                $comment_data->commentbyfullname = $comment_by_username;
                $comment_data->comment = $comment;
                $comment_data->timestamp = time();
                $DB->insert_record('block_feedback', $comment_data);

                // Display success message
                $html .= $OUTPUT->notification('Feedback added successfully', 'notifysuccess');
            } else {
                // Display error message if any field is empty
                $html .= $OUTPUT->notification('All fields are required', 'notifyproblem');
            }
        }

        // Display form for adding comments
        $html = "";
        $html .= '<form action="#" method="post">';
        
        $courses = $DB->get_records_sql("
                    SELECT c.id, c.fullname 
                    FROM {course} c 
                    JOIN {enrol} e ON e.courseid = c.id
                    JOIN {user_enrolments} ue ON ue.enrolid = e.id
                    WHERE ue.userid = :userid
                    ", array('userid' => $s_user_id));
        
        $html .= '<label for="select_course">Select Course: </label>';
        $html .= '<select name="select_course" id="select_course" class="form-control">';
        foreach ($courses as $course) {
            $html .= '<option value="' . $course->id . '">' . $course->fullname . '</option>';
        }
        $html .= '</select>';
        $html .= '</br>';
        $html .= '<label for="comment">Add Feedback: </label>';
        $html .= '<input type="text" name="student_id" hidden id="student_id" value="' . $s_user_id . '" />';
        $html .= '<textarea name="comment" id="comment" class="form-control"></textarea></br>';
        $html .= '<input type="submit" value="Submit" class="btn btn-primary">';
        $html .= '</form>';
        $html .= '<hr>';
        $html .= $this->render_comments_details($OUTPUT, $DB, $s_user_id);
        return $html;
    }

    protected function render_comments_details($OUTPUT, $DB, $s_user_id){
        $html = "";
        // Students view for feedback block
        $html .= '<div class="default-content" style="max-height: 300px; overflow-y: auto;">';
        
        // Retrieve comments for the logged-in user
        $comments = $DB->get_records('block_feedback', array('userid' => $s_user_id));
    
        if ($comments) {
            $html .= '<h5>User Feedback</h5>';
            foreach ($comments as $comment) {
                // Retrieve course name based on courseid
                $course = $DB->get_record('course', array('id' => $comment->courseid));
    
                if ($course) {
                    // Format the timestamp to display the date and time
                    $timestamp = date('Y-m-d H:i:s', $comment->timestamp);
                    // Output the comment information
                    $html .= '<label>You - <i style="color: blue">'. $comment->commentbyfullname .' </i> have provided the feedback for the course <i>'. $course->fullname .'</i></label>';
                    $html .= '<strong>Feedback: </strong><label>'. $comment->comment .'</label></br>';
                    $html .= '<small>Date: '. $timestamp .'</small><hr>';
                }
            }
        } else {
            $html .= '<p>No feedbacks found.</p>';
        }
        $html .= '</div>';

        return $html;
    }
}
