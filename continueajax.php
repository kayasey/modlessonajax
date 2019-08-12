<?php


define('AJAX_SCRIPT', true);

require_once('../../config.php');
global $USER;
$sesskey    = optional_param('sesskey', null, PARAM_RAW);
$action     = optional_param('action', false, PARAM_ALPHA);
$validactions = array('viewlesson');

$context = context_system::instance();
$PAGE->set_context($context);

if ($action and in_array($action, $validactions) and !empty($USER->id)) {
	
	if (!confirm_sesskey($sesskey)) {
		echo json_encode(array('msgerror' => get_string('invalidsesskey', 'error')));
		die;
	}
	$params = array();
	if ($action === 'viewlesson') {
		$func = 'viewlesson';
		array_push($params, 0);
		array_push($params, 'trash');
		array_push($params, $mailpagesize);
	}
	echo json_encode(call_user_func_array($func, $params));
	
	die;
} else {
	echo json_encode(array('msgerror' => get_string('invalidsesskey', 'error'),
							'info' => '',
							'html' => '',
							'redirect' => $CFG->wwwroot
						));
	die;
}

function viewlesson(){
	global $PAGE, $CFG, $DB, $sesskey;
	$outputhtml = '';

	require_once("../../config.php");
	require_once($CFG->dirroot.'/mod/lesson/locallib.php');
	
	$id = required_param('id', PARAM_INT);
	
		
	$cm = get_coursemodule_from_id('lesson', $id, 0, false, MUST_EXIST);
	$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
	$lesson = new lesson($DB->get_record('lesson', array('id' => $cm->instance), '*', MUST_EXIST), $cm, $course);

	require_login($course, false, $cm);
	require_sesskey();

	// Apply overrides.
	$lesson->update_effective_access($USER->id);

	$context = $lesson->context;
	$canmanage = $lesson->can_manage();
	$lessonoutput = $PAGE->get_renderer('mod_lesson');
	
	$url = new moodle_url('/mod/lesson/continue.php', array('id'=>$cm->id));
	$PAGE->set_url($url);
	$PAGE->set_pagetype('mod-lesson-view');
	$PAGE->navbar->add(get_string('continue', 'lesson'));

	
	// This is the code updates the lesson time for a timed test
	// get time information for this user
	if (!$canmanage) {
		$lesson->displayleft = lesson_displayleftif($lesson);
		$timer = $lesson->update_timer();
		if (!$lesson->check_time($timer)) {
			//redirect(new moodle_url('/mod/lesson/viewajax.php', array('id' => $cm->id, 'pageid' => LESSON_EOL, 'outoftime' => 'normal')));
			
			return array(
					'msgerror' => '',
					'info' => 'Mesaj gönderildi',
					'ajaxredirect'=>(string)new moodle_url('/mod/lesson/viewajax.php', array('id' => $cm->id, 'pageid' => LESSON_EOL, 'outoftime' => 'normal','action'=>'viewlessonajax','sesskey'=>$sesskey)),
					'id'=>$cm->id,
					'pageid'=>LESSON_EOL,
					'outoftime'=>'normal',
					'html' => $outputhtml,
					'sesskey' => $sesskey,
					'newajaxcall' => true,
				);
			die; // Shouldn't be reached, but make sure.
		}
	} else {
		$timer = new stdClass;
	}

	// record answer (if necessary) and show response (if none say if answer is correct or not)
	$page = $lesson->load_page(required_param('pageid', PARAM_INT));

	$reviewmode = $lesson->is_in_review_mode();

	// Process the page responses.
	
	$result = $lesson->process_page_responses($page);

	if ($result->nodefaultresponse || $result->inmediatejump) {
		// Don't display feedback or force a redirecto to newpageid.
		//redirect(new moodle_url('/mod/lesson/viewajax.php', array('id'=>$cm->id,'pageid'=>$result->newpageid)));
	// Set Messages.
		return array(
					'msgerror' => '',
					'ajaxredirect'=>(string)new moodle_url('/mod/lesson/viewajax.php', array('id'=>$cm->id,'pageid'=>$result->newpageid,'sesskey'=>$sesskey,'action'=>'viewlessonajax')),
					'html' => '',
					'sesskey' => $sesskey,
					'newajaxcall' => true,
				);
	}
	
	$lesson->add_messages_on_page_process($page, $result, $reviewmode);

	$PAGE->set_url('/mod/lesson/viewajax.php', array('id' => $cm->id, 'pageid' => $page->id));
	$PAGE->set_subpage($page->id);

	/// Print the header, heading and tabs
	lesson_add_fake_blocks($PAGE, $cm, $lesson, $timer);
	$outputhtml .= $lessonoutput->header($lesson, $cm, 'view', true, $page->id, get_string('continue', 'lesson'));

	if ($lesson->displayleft) {
		$outputhtml .=  '<a name="maincontent" id="maincontent" title="'.get_string('anchortitle', 'lesson').'"></a>';
	}
	// This calculates and prints the ongoing score message
	if ($lesson->ongoing && !$reviewmode) {
		$outputhtml .=  $lessonoutput->ongoing_score($lesson);
	}
	if (!$reviewmode) {
		$outputhtml .=  format_text($result->feedback, FORMAT_MOODLE, array('context' => $context, 'noclean' => true));
	}

	// User is modifying attempts - save button and some instructions
	if (isset($USER->modattempts[$lesson->id])) {
		$content = $OUTPUT->box(get_string("gotoendoflesson", "lesson"), 'center');
		$content .= $OUTPUT->box(get_string("or", "lesson"), 'center');
		$content .= $OUTPUT->box(get_string("continuetonextpage", "lesson"), 'center');
		$url = new moodle_url('/mod/lesson/viewajax.php', array('id' => $cm->id, 'pageid' => LESSON_EOL));
		$outputhtml .=  $content . $OUTPUT->single_button($url, get_string('finish', 'lesson'));
	}

	// Review button back
	if (!$result->correctanswer && !$result->noanswer && !$result->isessayquestion && !$reviewmode && $lesson->review && !$result->maxattemptsreached) {
		$url = new moodle_url('/mod/lesson/viewajax.php', array('id' => $cm->id, 'pageid' => $page->id));
		$outputhtml .= $OUTPUT->single_button($url, get_string('reviewquestionback', 'lesson'));
	}

	$url = new moodle_url('/mod/lesson/viewajax.php', array('id'=>$cm->id, 'pageid'=>$result->newpageid));

	if ($lesson->review && !$result->correctanswer && !$result->noanswer && !$result->isessayquestion && !$result->maxattemptsreached) {
		// If both the "Yes, I'd like to try again" and "No, I just want to go on  to the next question" point to the same
		// page then don't show the "No, I just want to go on to the next question" button. It's confusing.
		if ($page->id != $result->newpageid) {
			// Button to continue the lesson (the page to go is configured by the teacher).
			$outputhtml .=  $OUTPUT->single_button($url, get_string('reviewquestioncontinue', 'lesson'));
		}
	} else {
		// Normal continue button
		$outputhtml .=  $OUTPUT->single_button($url, get_string('continue', 'lesson'));
	}

	$outputhtml .=  $lessonoutput->footer();

			
	return array(
					'msgerror' => '',
					'info' => 'Mesaj gönderildi',
					'html' => $outputhtml,
					'redirect' => '',
				);
	}