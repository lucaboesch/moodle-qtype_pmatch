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
 * Pattern-match question definition class.
 *
 * @package   qtype_pmatch
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_pmatch\local\spell\qtype_pmatch_spell_checker;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/question/type/pmatch/pmatchlib.php');

/**
 * Represents a pattern-match  question.
 *
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_pmatch_question extends question_graded_by_strategy
        implements question_response_answer_comparer {

    /** @var bool whether answers should be graded case-sensitively. */
    public $usecase;

    /** @var string add more words to the dictionary. */
    public $extenddictionary;

    /** @var string not really used here, the value used is stored in the pmatch_options,
     * but this gets set because of extra_question_fields() so we need to declare it. */
    public $sentencedividers;

    /** @var string not really used here, the value used is stored in the pmatch_options,
     * but this gets set because of extra_question_fields() so we need to declare it. */
    public $converttospace;

    /** @var bool whether to allow students to use subscript. */
    public $allowsubscript;

    /** @var bool whether to allow students to use super script. */
    public $allowsuperscript;

    /** @var bool whether to warn student if their response is longer than 20 words. */
    public $forcelength;

    /** @var bool whether to spell check students response. */
    public $applydictionarycheck;

    /** @var string to be used for 'Preview question' and 'Answer sheet' in print. */
    public $modelanswer;

    /** @var string to be used for display a pre-fill answer */
    public $responsetemplate;

    /** @var pmatch_options options for pmatch expression matching. */
    public $pmatchoptions;

    /** @var array of question_answer. */
    public $answers = [];

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(new question_first_matching_answer_grading_strategy($this));
    }

    /**
     * Get the expected data for the response.
     *
     * @return array The expected data.
     */
    public function get_expected_data() {
        return ['answer' => PARAM_RAW_TRIMMED];
    }

    /**
     * {@inheritDoc}
     *
     * @param array $response the response
     * @return mixed|string|null a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param array $response the response
     * @return bool whether the response can be graded.
     */
    public function is_gradable_response(array $response) {
        if (!array_key_exists('answer', $response) || ((!$response['answer']) && $response['answer'] !== '0')) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param array $response the response
     * @return bool whether this response is a complete answer to this question.
     */
    public function is_complete_response(array $response) {
        if ($this->is_gradable_response($response)) {
            return (count($this->validate($response)) === 0);
        } else {
            return false;
        }
    }

    /**
     * Validate the response.
     *
     * @param array $response the response
     * @return array the validation errors.
     * @throws coding_exception
     */
    protected function validate(array $response) {
        $responsevalidationerrors = [];

        if (!array_key_exists('answer', $response) || ((!$response['answer']) && $response['answer'] !== '0')) {
            return [get_string('pleaseenterananswer', 'qtype_pmatch')];
        }

        $parsestring = new pmatch_parsed_string($response['answer'], $this->pmatchoptions);
        if (!$parsestring->is_parseable()) {
            $a = $parsestring->unparseable();
            $responsevalidationerrors[] = get_string('unparseable', 'qtype_pmatch', $a);
        }
        if ($this->applydictionarycheck != qtype_pmatch_spell_checker::DO_NOT_CHECK_OPTION &&
                !$parsestring->is_spelled_correctly() && (!$this->allowsubscript && !$this->allowsuperscript)) {
            $misspelledwords = $parsestring->get_spelling_errors();
            $a = join(' ', $misspelledwords);
            $responsevalidationerrors[] = get_string('spellingmistakes', 'qtype_pmatch', $a);
        }
        if ($this->forcelength) {
            if ($parsestring->get_word_count() > 20) {
                $responsevalidationerrors[] = get_string('toomanywords', 'qtype_pmatch');
            }
        }
        return $responsevalidationerrors;
    }

    /**
     * {@inheritDoc}
     *
     * @param array $response the response
     * @return lang_string|mixed|string|null the message.
     * @throws coding_exception
     */
    public function get_validation_error(array $response) {
        $errors = $this->validate($response);
        if (count($errors) === 1) {
            return array_pop($errors);
        } else {
            $errorslist = html_writer::alist($errors);
            return get_string('errors', 'qtype_pmatch', $errorslist);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param array $prevresponse the responses previously recorded for this question.
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer');
    }

    /**
     * {@inheritDoc}
     *
     * @return array of question answers.
     */
    public function get_answers() {
        return $this->answers;
    }

    /**
     * Compare a response with an answer.
     *
     * @param array $response the response
     * @param question_answer $answer the answer
     * @return bool whether the response matches the answer
     */
    public function compare_response_with_answer(array $response, question_answer $answer) {
        if ($answer->answer == '*') {
            return true;
        }
        // Normally this test won't be called if answer is not set.
        // However, it can be called like that from combined, so this test is necessary.
        if (!isset($response['answer'])) {
            return false;
        }
        return self::compare_string_with_pmatch_expression($response['answer'],
                                                            $answer->answer,
                                                            $this->pmatchoptions);
    }

    /**
     * Compare a string with a pmatch expression.
     *
     * @param string $string the string to be matched.
     * @param string $expression the expression being matched.
     * @param array $options the options for the pmatch expression.
     * @return bool whether the string matches the expression.
     * @throws coding_exception
     */
    public static function compare_string_with_pmatch_expression($string, $expression, $options) {
        $string = new pmatch_parsed_string($string, $options);
        $expression = new pmatch_expression($expression, $options);
        return $expression->matches($string);
    }

    /**
     * {@inheritDoc}
     *
     * @return string[]|null parameter name => value.
     */
    public function get_correct_response() {
        if ($this->modelanswer === '' || $this->modelanswer === null) {
            // We don't have a correct answer.
            return null;
        }
        return ['answer' => $this->modelanswer];
    }

    /**
     * {@inheritDoc}
     *
     * @param question_attempt $qa the question attempt being displayed.
     * @param question_display_options $options the options that control display of the question.
     * @param string $component the name of the component we are serving files for.
     * @param string $filearea the name of the file area.
     * @param array $args the remaining bits of the file path.
     * @param bool $forcedownload whether the user must be forced to download the file.
     * @return bool true if the user can access this file.
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'answerfeedback') {
            $currentanswer = $qa->get_last_qt_var('answer');
            $answer = $qa->get_question()->get_matching_answer(['answer' => $currentanswer]);
            $answerid = reset($args); // Itemid is answer id.
            return $options->feedback && $answerid == $answer->id;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component,
                                                                $filearea, $args, $forcedownload);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param question_attempt_step $step The question attempt step
     * @param int $variant which variant of this question to start
     * @return void
     * @throws coding_exception
     */
    public function start_attempt(question_attempt_step $step, $variant) {
        $this->pmatchoptions->lang = $this->applydictionarycheck;
        $step->set_qt_var('_responselang', $this->pmatchoptions->lang);
    }

    /**
     * {@inheritDoc}
     *
     * @param question_attempt_step $step The question attempt step
     * @return void
     */
    public function apply_attempt_state(question_attempt_step $step) {
        $this->pmatchoptions->lang = $step->get_qt_var('_responselang');
    }

    /**
     * Get the context of the question.
     *
     * @return bool|\core\context the context of the question.
     * @throws coding_exception
     */
    public function get_context() {
        return context::instance_by_id($this->contextid);
    }

    /**
     * Check that current user has the required capability.
     *
     * @param string $type the type of capability.
     * @return bool whether the user has the required capability.
     * @throws coding_exception
     */
    protected function has_question_capability($type) {
        global $USER;
        $context = $this->get_context();
        return has_capability("moodle/question:{$type}all", $context) ||
                ($USER->id == $this->createdby && has_capability("moodle/question:{$type}mine", $context));
    }

    /**
     * Check that current user can view the question.
     *
     * @return bool Whether the user can view the question.
     * @throws coding_exception
     */
    public function user_can_view() {
        return $this->has_question_capability('view');
    }

    /**
     * Check that current user can see the missing dictionary warning message.
     *
     * @return bool True ìf user has the require capability, otherwise False
     */
    public function user_can_see_missing_dict_warning() {
        return $this->has_question_capability('edit');
    }

    /**
     * Checks whether the spell-check language for this question is available on the server.
     *
     * @return bool returns false if the question is set to use spell-checking, and the required
     *      language dictionaries are not available.
     */
    public function is_spell_check_laguage_available() {
        $spellchecklanguagesdata = get_config('qtype_pmatch', 'spellcheck_languages');
        if (!$spellchecklanguagesdata) {
            return false;
        }
        $availablelangs = explode(',', $spellchecklanguagesdata);

        return !in_array($this->applydictionarycheck, $availablelangs) &&
                $this->applydictionarycheck !== qtype_pmatch_spell_checker::DO_NOT_CHECK_OPTION;
    }

    /**
     * Modify the current answer base on question display option and response template.
     *
     * @param string|null $currentanswer the current answer of user in the question attempt.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string|null
     */
    public function modify_current_answer(?string $currentanswer, question_display_options $options): ?string {
        if (!$currentanswer && !$options->readonly) {
            $currentanswer = $this->responsetemplate;
        }
        return $currentanswer;
    }
}
