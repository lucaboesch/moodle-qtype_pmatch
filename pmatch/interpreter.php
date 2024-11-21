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
 * This file contains code to interpret a pmatch expression.
 *
 * @package   qtype_pmatch
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/pmatch/pmatch/matcher.php');

/**
 *
 */
define('PMATCH_SPECIAL_CHARACTER', '[\(\)\\\\\|\?\*_\[\]]');
// All characters in many Unicode classes, but not the special ones.
/**
 *
 */
define('PMATCH_CHARACTER', '(?:(?!' . PMATCH_SPECIAL_CHARACTER . ')[\pL\pM\pN\pP\pS])');

/**
 *
 */
define('PMATCH_LNUM', '[0-9]+');
/**
 *
 */
define('PMATCH_DNUM', PMATCH_LNUM.'[\.]'.PMATCH_LNUM);
/**
 *
 */
define('PMATCH_HTML_EXPONENT', '[*xX]10<(sup|SUP)>([+-]?'.PMATCH_LNUM.')</(sup|SUP)>');
/**
 *
 */
define('PMATCH_EXPONENT_DNUM', '(('.PMATCH_LNUM.'|'.PMATCH_DNUM.')'.
                            '([eE][+-]?'.PMATCH_LNUM.'|'.PMATCH_HTML_EXPONENT.'))');
/**
 *
 */
define('PMATCH_NUMBER', '((([+|-])?'.PMATCH_EXPONENT_DNUM.')'.
                            '|(([+|-])?'.PMATCH_DNUM.')'.
                            '|(([+|-])?'.PMATCH_LNUM.'))');

/**
 *
 */
abstract class pmatch_interpreter_item {
    /**
     * @var string the interpreter error message.
     */
    protected $interpretererrormessage;
    /**
     * @var string the code that was interpreted.
     */
    public $codefragment;

    /**
     * @var pmatch_options the options for this pmatch.
     */
    public $pmatchoptions;

    /**
     * @var string regex pattern to match one character of pmatch code.
     */
    protected $pattern;

    /**
     * The constructor for a pmatch interpreter object.
     *
     * @param pmatch_options $pmatchoptions the options for this pmatch
     */
    public function __construct($pmatchoptions = null) {
        if (is_null($pmatchoptions)) {
            $pmatchoptions = new pmatch_options();
        }
        $this->pmatchoptions = $pmatchoptions;
    }

    /**
     * Interpret a pmatch expression as a string.
     *
     * @param string $string a pmatch expression as a string.
     * @param int $start where to start parsing.
     * @return array [bool, int] whether the expression was found, and the position after the code.
     */
    public function interpret($string, $start = 0) {
        $this->interpretererrormessage = '';
        list($found, $endofmatch) = $this->interpret_contents($string, $start);
        if ($found) {
            $this->codefragment = core_text::substr($string, $start, $endofmatch - $start);
        } else {
            $this->codefragment = '';
        }
        return [$found, $endofmatch];
    }

    /**
     * Convert the $string starting at $start into a tree of object representing parts of pmatch
     * code. This is the default method which is often overriden. It looks for $pattern which is a
     * regex with no modifying options.
     * @param string $string
     * @param int $start
     */
    protected function interpret_contents($string, $start) {
        // Regex pattern to match one character of pmatch code.
        list($found, $endofpattern, $subpatterns) = $this->find_pattern(
                $this->pattern, $string, $start);
        return [$found, $endofpattern];
    }

    /**
     *
     * Find an anchored case insensitive regular expression, searching from $start.
     * @param string $pattern
     * @param string $string
     * @param int $start
     * @return array $found bool is the pattern found,
     *               $endofpattern int the position of the end of the pattern,
     *               $matches array of matches of sub patterns with offset from $start
     */
    public function find_pattern($pattern, $string, $start) {
        $matches = [];
        preg_match($pattern.'iAu', core_text::substr($string, $start), $matches, PREG_OFFSET_CAPTURE);
        $found = !empty($matches);
        if ($found) {
            $endofpattern = $matches[0][1] + core_text::strlen($matches[0][0]) + $start;
        } else {
            $endofpattern = $start;
        }

        array_shift($matches); // Pop off the matched string and only return sub patterns.
        return [$found, $endofpattern, $matches];
    }

    /**
     * Get the error message for this interpreter object.
     *
     * @return string
     */
    public function get_error_message() {
        if (!empty($this->interpretererrormessage)) {
            return $this->interpretererrormessage;
        } else {
            return '';
        }
    }

    /**
     * Set the error message for this interpreter object.
     *
     * @param string $errormessage The error message to set.
     * @param string $codefragment The code fragment that caused the error.
     * @return void
     * @throws coding_exception
     */
    public function set_error_message($errormessage, $codefragment) {
        $this->interpretererrormessage =
                                get_string('ie_'.$errormessage, 'qtype_pmatch', $codefragment);
    }

    /**
     * Get the matcher tree for this interpreter object. Can be used from an interpreter object at
     * any point in the tree.
     *
     * @param pmatch_options $externaloptions
     * @return pmatch_matcher_item a tree of child classes of pmatch_matcher_item
     */
    public function get_matcher($externaloptions) {
        $thistypename = $this->get_type_name_of_interpreter_object($this);
        $matchclassname = 'pmatch_matcher_'.$thistypename;
        return new $matchclassname($this, $externaloptions);
    }

    /**
     * Get the type name of an interpreter object.
     *
     * @param object $object The object to get the type name of.
     * @return string The type name of the object.
     */
    public function get_type_name_of_interpreter_object($object) {
        return core_text::substr(get_class($object), 19);
    }

    /**
     * Get the code fragment that was interpreted.
     *
     * @return mixed The code fragment that was interpreted.
     */
    public function get_code_fragment() {
        return $this->codefragment;
    }

    /**
     * Get the formatted expression string for this interpreter object.
     *
     * @param int $indentlevel the level of indentation to use.
     * @return mixed The formatted expression string.
     */
    public function get_formatted_expression_string($indentlevel = 0) {
        return $this->codefragment;
    }

    /**
     * Indent the code fragment by a given level.
     *
     * @param int $indentlevel The level of indentation to use.
     * @return string The indented code fragment.
     */
    protected function indent($indentlevel) {
        return str_repeat('    ', $indentlevel);
    }
}

/**
 *
 */
abstract class pmatch_interpreter_item_with_subcontents extends pmatch_interpreter_item {

    /**
     * @var array The sub contents for this item.
     */
    protected $subcontents = [];
    /**
     * @var int How many items can be contained as sub contents of this item. If 0 then no limit.
     */
    protected $limitsubcontents = 0;

    /**
     * Interpret sub contents of item.
     *
     * @param string $string code that is to be interpreted
     * @param int $start position at which to start
     * @param array $branchfoundsofar (optional) items found so far, if any
     * @return array (longest possible branch that matches the longest string,
     *                string position after code for these items.)
     */
    protected function interpret_subcontents($string, $start, $branchfoundsofar = []) {
        $typestotry = $this->next_possible_subcontent($branchfoundsofar);
        $branchindex = 0;
        $childbranches = [];
        $childbranchcursor = [];

        // Iterate down all branches.
        foreach ($typestotry as $typetotry) {
            $childbranches[$branchindex] = $branchfoundsofar;
            list($typefound, $found, $childbranchcursor[$branchindex]) =
                    $this->interpret_subcontent_item($typetotry, $string, $start);
            if ($found && ($childbranchcursor[$branchindex] > $start)) {
                $childbranches[$branchindex][] = $typefound;
                if (($this->limitsubcontents == 0) ||
                            (count($childbranches[$branchindex]) < $this->limitsubcontents)) {
                    list($childbranches[$branchindex], $childbranchcursor[$branchindex]) =
                                $this->interpret_subcontents($string,
                                                            $childbranchcursor[$branchindex],
                                                            $childbranches[$branchindex]);
                }
            }
            if ($anyerrormessage = $typefound->get_error_message()) {
                $this->interpretererrormessage = $anyerrormessage;
            }
            $branchindex++;
        }

        // Find the branch that matches the longest string.
        array_multisort($childbranchcursor, SORT_DESC, SORT_NUMERIC, $childbranches);
        return [array_shift($childbranches), array_shift($childbranchcursor)];
    }
    /**
     *
     * What was the last type of sub contents found in $foundsofar
     * @param array $foundsofar
     * @return string the type of sub contents last found
     *                (prefix with 'pmatch_interpreter_' to get classname)
     */
    protected function last_subcontent_type_found($foundsofar) {
        if (!empty($foundsofar)) {
            return $this->get_type_name_of_interpreter_object($foundsofar[count($foundsofar) - 1]);
        } else {
            return '';
        }
    }
    /**
     *
     * In the branch of code matched so far what could be the next type.
     * @param array $foundsofar
     * @return array the types of sub contents that could come next
     *                (prefix with 'pmatch_interpreter_' to get classname)
     */
    protected function next_possible_subcontent($foundsofar) {
        return [];
    }
    /**
     * Interpret a sub content item.
     *
     * @param string $cancontaintype the type of sub content that can be contained
     * @param string $string code that is to be interpreted
     * @param int $start position at which to start
     * @return array the type of sub content found, whether it was found, and the position after the code.
     */
    protected function interpret_subcontent_item($cancontaintype, $string, $start) {
        $cancontainclassname = 'pmatch_interpreter_'.$cancontaintype;
        $cancontain = new $cancontainclassname($this->pmatchoptions);
        list($found, $aftercontent) = $cancontain->interpret($string, $start);
        if ($found) {
            return [$cancontain, true, $aftercontent];
        } else {
            return [$cancontain, false, $start];
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param string $string code that is to be interpreted
     * @param int $start position at which to start
     * @return array Whether the expression was found, and the position after the code.
     */
    protected function interpret_contents($string, $start) {
        list($this->subcontents, $endofcontents) = $this->interpret_subcontents($string, $start);
        $this->check_subcontents();
        return [(!empty($this->subcontents)), $endofcontents];
    }
    /**
     *
     * Any checks that need to be done on sub contents found, are done here. The default is to check
     * the last content type found and if the type is included in lastcontenttypeerrors report an
     * error.
     */
    protected function check_subcontents() {
        if (array_key_exists($this->last_subcontent_type_found($this->subcontents),
                                                                    $this->lastcontenttypeerrors)) {
            $this->set_error_message(
                $this->lastcontenttypeerrors[$this->last_subcontent_type_found($this->subcontents)],
                $this->codefragment);
        }
    }

    /**
     * @var string[]
     */
    protected $lastcontenttypeerrors = ['or_character' => 'lastsubcontenttypeorcharacter',
                                 'word_delimiter_space' => 'lastsubcontenttypeworddelimiter',
                                 'word_delimiter_proximity' => 'lastsubcontenttypeworddelimiter', ];

    /**
     * {@inheritDoc}
     *
     * @param string $string a pmatch expression as a string.
     * @param int $start where to start parsing.
     * @return array [bool, int] whether the expression was found, and the position after the code.
     */
    public function interpret($string, $start = 0) {
        list($found, $endofmatch) = parent::interpret($string, $start);
        $this->check_subcontents();
        return [$found, $endofmatch];
    }

    /**
     * Get the sub contents for this interpreter object.
     *
     * @return array The sub contents for this interpreter object.
     */
    public function get_subcontents() {
        return $this->subcontents;
    }

    /**
     * {@inheritDoc}
     *
     * @param int $indentlevel the level of indentation to use.
     * @return string The formatted expression string.
     */
    public function get_formatted_expression_string($indentlevel = 0) {
        $string = '';
        foreach ($this->subcontents as $subcontent) {
            $string .= $subcontent->get_formatted_expression_string($indentlevel + 1);
        }
        return $string;
    }
}

/**
 *
 */
abstract class pmatch_interpreter_item_with_enclosed_subcontents
                    extends pmatch_interpreter_item_with_subcontents {

    /**
     * @var string The pattern to match the opening bracket.
     */
    protected $openingpattern;
    /**
     * @var string The pattern to match the closing bracket.
     */
    protected $closingpattern;
    /**
     * @var string The error message to display if the closing pattern is missing.
     */
    protected $missingclosingpatternerror = '';

    /**
     * {@inheritDoc}
     *
     * @param string $string code that is to be interpreted
     * @param int $start position at which to start
     * @return array Whether the expression was found, and the position after the code.
     * @throws coding_exception
     */
    protected function interpret_contents($string, $start) {
        $subpatterns = [];
        list($found, $endofopening, $subpatterns) =
                                $this->find_pattern($this->openingpattern, $string, $start);

        if (!$found) {
            return [false, $start];
        }

        if (!empty($subpatterns)) {
            $subpattern = $subpatterns[0][0];
        } else {
            $subpattern = '';
        }
        if (!$this->interpret_subpattern_in_opening($subpattern)) {
            return [false, $start];
        }
        list($this->subcontents, $endofcontents) =
                                            $this->interpret_subcontents($string, $endofopening);
        if (empty($this->subcontents)) {
            $this->set_error_message('unrecognisedsubcontents', shorten_text($string, 20, true));
            return [false, $start];
        }
        list($found, $endofclosing, $subpatterns) =
                            $this->find_pattern($this->closingpattern, $string, $endofcontents);
        if (!$found) {
            if (!empty($this->missingclosingpatternerror)) {
                $this->set_error_message($this->missingclosingpatternerror,
                                            core_text::substr($string, $start, $endofcontents - $start));
            }
            return [true, $start];
        }
        return [true, $endofclosing];
    }

    /**
     * Interpret whether a subpattern is found in the opening pattern.
     *
     * @param string $subpattern the subpattern to be found in the opening pattern.
     * @return true Whether the subpattern is found in the opening pattern.
     */
    protected function interpret_subpattern_in_opening($subpattern) {
        return true;
    }

    /**
     * Get the formatted opening string for this interpreter object given
     * the indent level.
     *
     * @param int $indentlevel the level of indentation to use.
     * @return string The formatted expression string.
     */
    public function get_formatted_expression_string($indentlevel = 0) {
        $string = $this->indent($indentlevel). $this->formatted_opening()." (\n";
        $string .= parent::get_formatted_expression_string($indentlevel);
        $string .= $this->indent($indentlevel). ")\n";
        return $string;
    }

    /**
     * Get the formatted opening string for this interpreter object.
     *
     * @return string The formatted opening string.
     */
    protected function formatted_opening() {
        return ''; // Overridden in sub classes.
    }

}

/**
 *
 */
class pmatch_interpreter_whole_expression extends pmatch_interpreter_item_with_subcontents {
    /**
     * @var int
     */
    protected $limitsubcontents = 1;

    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        return ['not', 'match_any', 'match_all', 'match_options'];
    }

    /**
     * {@inheritDoc}
     *
     * @param int $indentlevel the level of indentation to use.
     * @return string The formatted expression string.
     */
    public function get_formatted_expression_string($indentlevel = 0) {
        return $this->subcontents[0]->get_formatted_expression_string($indentlevel);
    }
}

/**
 *
 */
class pmatch_interpreter_not extends pmatch_interpreter_item_with_enclosed_subcontents {
    /**
     * @var string
     */
    protected $openingpattern = '~\s*not\s*\(\s*~';
    /**
     * @var string
     */
    protected $closingpattern = '~\s*\)\s*~';
    /**
     * @var string
     */
    protected $missingclosingpatternerror = 'missingclosingbracket';
    /**
     * @var int
     */
    protected $limitsubcontents = 1;

    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        return ['match_any', 'match_all', 'match_options'];
    }

    /**
     * Get the formatted opening string for this interpreter object.
     *
     * @return string The formatted opening string.
     */
    protected function formatted_opening() {
        return 'not';
    }
}

/**
 *
 */
class pmatch_interpreter_match extends pmatch_interpreter_item_with_enclosed_subcontents {
    /**
     * @var string
     */
    protected $openingpattern = '~match([_a-z0-4]*)\s*\(\s*~';
    /**
     * @var string
     */
    protected $closingpattern = '~\s*\)\s*~';
    /**
     * @var string
     */
    protected $missingclosingpatternerror = 'missingclosingbracket';
}

/**
 *
 */
class pmatch_interpreter_match_any extends pmatch_interpreter_match {
    /**
     * {@inheritDoc}
     *
     * @param string $options the subpattern to be found in the opening pattern.
     * @return true Whether the subpattern is found in the opening pattern.
     */
    protected function interpret_subpattern_in_opening($options) {
        return ($options == '_any');
    }

    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        return ['match_any', 'match_all', 'match_options', 'not'];
    }

    /**
     * Get the formatted opening string for this interpreter object.
     *
     * @return string The formatted opening string.
     */
    protected function formatted_opening() {
        return 'match_any';
    }
}

/**
 *
 */
class pmatch_interpreter_match_all extends pmatch_interpreter_match {
    /**
     * {@inheritDoc}
     *
     * @param string $options the subpattern to be found in the opening pattern.
     * @return true Whether the subpattern is found in the opening pattern.
     */
    protected function interpret_subpattern_in_opening($options) {
        return ($options == '_all');
    }

    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        return ['match_any', 'match_all', 'match_options', 'not'];
    }

    /**
     * Get the formatted opening string for this interpreter object.
     *
     * @return string The formatted opening string.
     */
    protected function formatted_opening() {
        return 'match_all';
    }
}

/**
 *
 */
class pmatch_word_level_options {
    /**
     * @var bool $allowextracharacters The allow extra characters option.
     */
    protected $allowextracharacters;
    /**
     * @var bool $misspellingallowreplacechar The misspelling replace character option.
     */
    protected $misspellingallowreplacechar;
    /**
     * @var bool $misspellingallowtransposetwochars The misspelling allow transpose two characters option.
     */
    protected $misspellingallowtransposetwochars;
    /**
     * @var bool $misspellingallowextrachar The misspelling allow extra characters option.
     */
    protected $misspellingallowextrachar;
    /**
     * @var bool $misspellingallowfewerchar The misspelling allow fewer characters option.
     */
    protected $misspellingallowfewerchar;
    /**
     * @var int $misspellings The number of misspellings allowed.
     */
    protected $misspellings;

    /**
     * The constructor for a pmatch word level options object.
     */
    public function __construct() {
        $this->reset_options();
    }

    /**
     * Reset the options for this pmatch.
     *
     * @return void
     */
    public function reset_options() {
        $this->allowextracharacters = false;
        $this->misspellingallowreplacechar = false;
        $this->misspellingallowtransposetwochars = false;
        $this->misspellingallowextrachar = false;
        $this->misspellingallowfewerchar = false;
        $this->misspellings = 1;
    }

    /**
     * Set the allow extra characters option.
     *
     * @param bool $allowextracharacters The allow extra characters option.
     * @return void
     */
    public function set_allow_extra_characters($allowextracharacters) {
        $this->allowextracharacters = $allowextracharacters;
    }

    /**
     * Set the misspelling replace character option.
     *
     * @param bool $misspellingallowreplacechar The misspelling replace character option.
     * @return void
     */
    public function set_misspelling_allow_replace_char($misspellingallowreplacechar) {
        $this->misspellingallowreplacechar = $misspellingallowreplacechar;
    }

    /**
     * Set the misspelling allow transpose two characters option.
     *
     * @param bool $misspellingallowtransposetwochars The misspelling allow transpose two characters option.
     * @return void
     */
    public function set_misspelling_allow_transpose_two_chars($misspellingallowtransposetwochars) {
        $this->misspellingallowtransposetwochars = $misspellingallowtransposetwochars;
    }

    /**
     * Set the misspelling allow extra characters option.
     *
     * @param bool $misspellingallowextrachar The misspelling allow extra characters option.
     * @return void
     */
    public function set_misspelling_allow_extra_char($misspellingallowextrachar) {
        $this->misspellingallowextrachar = $misspellingallowextrachar;
    }

    /**
     * Set the misspelling allow fewer characters option.
     *
     * @param bool $misspellingallowfewerchar The misspelling allow fewer characters option.
     * @return void
     */
    public function set_misspelling_allow_fewer_char($misspellingallowfewerchar) {
        $this->misspellingallowfewerchar = $misspellingallowfewerchar;
    }

    /**
     * Set the number of misspellings allowed.
     *
     * @param int $misspellings The number of misspellings allowed.
     * @return void
     */
    public function set_misspellings($misspellings) {
        $this->misspellings = $misspellings;
    }

    /**
     * Get the allow extra characters option value.
     *
     * @return bool The allow extra characters option value.
     */
    public function get_allow_extra_characters() {
        return $this->allowextracharacters;
    }

    /**
     * Get the allow replace character option value.
     *
     * @return bool The allow replace character option value.
     */
    public function get_misspelling_allow_replace_char() {
        return $this->misspellingallowreplacechar;
    }

    /**
     * Get the allow transpose two characters option value.
     *
     * @return bool The allow transpose two characters option value.
     */
    public function get_misspelling_allow_transpose_two_chars() {
        return $this->misspellingallowtransposetwochars;
    }

    /**
     * Get the allow extra characters option value.
     *
     * @return bool The allow extra characters option value.
     */
    public function get_misspelling_allow_extra_char() {
        return $this->misspellingallowextrachar;
    }

    /**
     * Get the allow fewer characters option value.
     *
     * @return int The allow fewer characters option value.
     */
    public function get_misspelling_allow_fewer_char() {
        return $this->misspellingallowfewerchar;
    }

    /**
     * Get the number of misspellings allowed.
     *
     * @return int The number of misspellings allowed.
     */
    public function get_misspellings() {
        return $this->misspellings;
    }

    /**
     * Get the options for this pmatch as a string.
     *
     * @return string The options for this pmatch as a string.
     */
    public function get_options_as_string() {
        $string = '';
        if ($this->misspellingallowreplacechar &&
                    $this->misspellingallowextrachar &&
                    $this->misspellingallowfewerchar &&
                    $this->misspellingallowtransposetwochars) {
            $string .= 'm';
            if ($this->misspellings == 2) {
                $string .= '2';
            }
        } else if ($this->misspellingallowreplacechar ||
                    $this->misspellingallowextrachar ||
                    $this->misspellingallowfewerchar ||
                    $this->misspellingallowtransposetwochars) {
            $string .= 'm';
            if ($this->misspellingallowreplacechar) {
                $string .= 'r';
            }
            if ($this->misspellingallowtransposetwochars) {
                $string .= 't';
            }
            if ($this->misspellingallowextrachar) {
                $string .= 'x';
            }
            if ($this->misspellingallowfewerchar) {
                $string .= 'f';
            }
        } else if ($this->allowextracharacters) {
            $string .= 'c';
        }
        return $string;
    }
}

/**
 *
 */
class pmatch_phrase_level_options {
    /**
     * @var bool Whether to allow proximity of.
     */
    protected $allowproximityof;
    /**
     * @var bool Whether to allow any word order.
     */
    protected $allowanywordorder;
    /**
     * @var bool Whether to allow extra words.
     */
    protected $allowextrawords;

    /**
     * The constructor for a pmatch phrase level options object.
     */
    public function __construct() {
        $this->reset_options();
    }

    /**
     * Get the allow proximity of option value.
     *
     * @return bool
     */
    public function get_allow_proximity_of() {
        return $this->allowproximityof;
    }

    /**
     * Get the allow proximity of option value.
     *
     * @return bool
     */
    public function get_allow_any_word_order() {
        return $this->allowanywordorder;
    }

    /**
     * Get the allow extra words option value.
     *
     * @return bool
     */
    public function get_allow_extra_words() {
        return $this->allowextrawords;
    }

    /**
     * Reset the options for this pmatch.
     *
     * @return void
     */
    public function reset_options() {
        $this->allowanywordorder = false;
        $this->allowextrawords = false;
        $this->allowproximityof = 2;
    }

    /**
     * Set the allow proximity of option.
     *
     * @param bool $allowproximityof Whether to allow proximity of.
     * @return void
     */
    public function set_allow_proximity_of($allowproximityof) {
        $this->allowproximityof = $allowproximityof;
    }

    /**
     * Set the allow any word order option.
     *
     * @param bool $allowanywordorder Whether to allow any word order.
     * @return void
     */
    public function set_allow_any_word_order($allowanywordorder) {
        $this->allowanywordorder = $allowanywordorder;
    }

    /**
     * Set the allow extra words option.
     *
     * @param bool $allowextrawords Whether to allow extra words.
     * @return void
     */
    public function set_allow_extra_words($allowextrawords) {
        $this->allowextrawords = $allowextrawords;
    }

    /**
     * Get the options for this pmatch as a string.
     *
     * @return string The options for this pmatch as a string.
     */
    public function get_options_as_string() {
        $string = '';
        if ($this->allowanywordorder) {
            $string .= 'o';
        }
        if ($this->allowextrawords) {
            $string .= 'w';
        }
        if ($this->allowproximityof != 2) {
            $string .= 'p'.$this->allowproximityof;
        }
        return $string;
    }
}

/**
 *
 */
class pmatch_interpreter_match_options extends pmatch_interpreter_match {

    /** @var pmatch_word_level_options */
    public $wordleveloptions;

    /** @var pmatch_phrase_level_options */
    public $phraseleveloptions;

    /**
     * The constructor for a pmatch match options interpreter object.
     *
     * @param array $pmatchoptions The options for this pmatch.
     */
    public function __construct($pmatchoptions) {
        parent::__construct($pmatchoptions);
        $this->wordleveloptions = new pmatch_word_level_options();
        $this->phraseleveloptions = new pmatch_phrase_level_options();
    }

    /**
     * {@inheritDoc}
     *
     * @param string $options the subpattern to be found in the opening pattern.
     * @return true Whether the subpattern is found in the opening pattern.
     */
    protected function interpret_subpattern_in_opening($options) {
        // General checks.
        if (empty($options)) {
            return true;
        }
        if ($options == '_any' || $options == '_all') {
            return false;
        }
        if ($options[0] != '_') {
            $this->set_error_message('illegaloptions', $options);
            return false;
        }
        $this->phraseleveloptions->reset_options();
        $wlopt = $this->wordleveloptions;
        $wlopt->reset_options();
        $misspellingoptionmatches = [];
        $cursor = 1; // Start at second character after '_'.
        while ($cursor < core_text::strlen($options)) {
            if (0 === preg_match('~c|o|w|m([frtx2])*|p[0-4]~A',
                                        core_text::substr($options, $cursor),
                                        $misspellingoptionmatches)) {
                $this->set_error_message('illegaloptions', $options);
                return false;
            }
            $thisoption = $misspellingoptionmatches[0];
            switch ($thisoption[0]) {
                case 'm' :
                    if (core_text::strlen($thisoption) == 1) {
                        $wlopt->set_misspelling_allow_replace_char(true);
                        $wlopt->set_misspelling_allow_transpose_two_chars(true);
                        $wlopt->set_misspelling_allow_extra_char(true);
                        $wlopt->set_misspelling_allow_fewer_char(true);
                    } else {
                        $misspellingoptioncursor = 0;
                        do {
                            switch ($thisoption[1 + $misspellingoptioncursor]) {
                                case 'r' :
                                    $wlopt->set_misspelling_allow_replace_char(true);
                                    break;
                                case 't' :
                                    $wlopt->set_misspelling_allow_transpose_two_chars(true);
                                    break;
                                case 'x' :
                                    $wlopt->set_misspelling_allow_extra_char(true);
                                    break;
                                case 'f' :
                                    $wlopt->set_misspelling_allow_fewer_char(true);
                                    break;
                                case '2' :
                                    $wlopt->set_misspellings(2);
                                    $wlopt->set_misspelling_allow_replace_char(true);
                                    $wlopt->set_misspelling_allow_transpose_two_chars(true);
                                    $wlopt->set_misspelling_allow_extra_char(true);
                                    $wlopt->set_misspelling_allow_fewer_char(true);
                                    break;
                                default :
                                    $this->set_error_message('illegaloptions', $options);
                                    return false;
                            }
                            $misspellingoptioncursor ++;
                        } while (isset($thisoption[1 + $misspellingoptioncursor]));
                    }
                    break;
                case 'c' :
                    $wlopt->set_allow_extra_characters(true);
                    break;
                case 'p' :
                    if (0 === preg_match('~[0-4]$~A', $thisoption[1])) {
                        $this->set_error_message('illegaloptions', $options);
                        return false;
                    } else {
                        $this->phraseleveloptions->set_allow_proximity_of($thisoption[1]);
                    }
                    break;
                case 'o' :
                    $this->phraseleveloptions->set_allow_any_word_order(true);
                    break;
                case 'w' :
                    $this->phraseleveloptions->set_allow_extra_words(true);
                    break;
                default :
                    $this->set_error_message('illegaloptions', $options);
                    return false;
            }
            $cursor = $cursor + core_text::strlen($thisoption);
        }
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        switch ($this->last_subcontent_type_found($foundsofar)) {
            case '':
            case 'word_delimiter_space':
            case 'word_delimiter_proximity':
                return ['or_list'];
            case 'or_list':
                return ['word_delimiter_space', 'word_delimiter_proximity'];
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param int $indentlevel the level of indentation to use.
     * @return string The formatted expression string.
     */
    public function get_formatted_expression_string($indentlevel = 0) {
        $string = $this->indent($indentlevel);
        $string .= $this->formatted_opening();
        $string .= ' (';
        foreach ($this->subcontents as $subcontent) {
            $string .= $subcontent->get_formatted_expression_string($indentlevel + 1);
        }
        $string .= ")\n";
        return $string;
    }

    /**
     * Get the formatted opening string for this interpreter object.
     *
     * @return string The formatted opening string.
     */
    protected function formatted_opening() {
        $options = '';
        $options .= $this->wordleveloptions->get_options_as_string();
        $options .= $this->phraseleveloptions->get_options_as_string();
        if (!empty($options)) {
            return 'match_'.$options;
        } else {
            return 'match';
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param string $string code that is to be interpreted
     * @param int $start position at which to start
     * @param array $branchfoundsofar (longest possible branch that matches the longest string,
     *        string position after code for these items.)
     */
    protected function interpret_subcontents($string, $start, $branchfoundsofar = []) {
        list($found, $end) = parent::interpret_subcontents($string, $start, $branchfoundsofar);
        if (!count($branchfoundsofar)) {
            if ($found && !empty($this->pmatchoptions->wordstoreplace)) {
                $subcontentsstr = core_text::substr($string, $start, $end - $start);
                $subcontentsstrwithsyn = preg_replace($this->pmatchoptions->wordstoreplace,
                        $this->pmatchoptions->synonymtoreplacewith, $subcontentsstr);
                if ($subcontentsstrwithsyn != $subcontentsstr) {
                    list($found, ) =
                        parent::interpret_subcontents($subcontentsstrwithsyn, 0, $branchfoundsofar);
                }
            }
        }
        return [$found, $end];
    }
}

/**
 *
 */
class pmatch_interpreter_or_list extends pmatch_interpreter_item_with_subcontents {
    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        switch ($this->last_subcontent_type_found($foundsofar)) {
            case '':
            case 'or_character':
                return ['or_list_phrase', 'number', 'word'];
            case 'word':
            case 'number':
            case 'or_list_phrase':
                return ['or_character'];
        }
    }
}


/**
 *
 * This is the same as an or_list but with no or_list_phrases.
 *
 */
class pmatch_interpreter_synonym extends pmatch_interpreter_item_with_subcontents {
    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        switch ($this->last_subcontent_type_found($foundsofar)) {
            case '':
            case 'or_character':
                return ['number', 'word'];
            case 'number':
            case 'word':
                return ['or_character'];
        }
    }
}

/**
 *
 */
class pmatch_interpreter_or_character extends pmatch_interpreter_item {
    /**
     * @var string
     */
    protected $pattern = '~\|~';
}

/**
 *
 */
class pmatch_interpreter_or_list_phrase extends pmatch_interpreter_item_with_enclosed_subcontents {

    /**
     * @var string
     */
    protected $openingpattern = '~\[~';
    /**
     * @var string
     */
    protected $closingpattern = '~\]~';
    /**
     * @var string
     */
    protected $missingclosingpatternerror = 'missingclosingbracket';
    /**
     * @var int
     */
    protected $limitsubcontents = 1;

    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        return ['phrase'];
    }

    /**
     * {@inheritDoc}
     *
     * @param int $indentlevel the level of indentation to use.
     * @return string The formatted expression string.
     */
    public function get_formatted_expression_string($indentlevel = 0) {
        $string = '[';
        foreach ($this->subcontents as $subcontent) {
            $string .= $subcontent->get_formatted_expression_string($indentlevel + 1);
        }
        $string .= ']';
        return $string;
    }
}

/**
 *
 */
class pmatch_interpreter_phrase extends pmatch_interpreter_item_with_subcontents {
    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        switch ($this->last_subcontent_type_found($foundsofar)) {
            case '':
            case 'word_delimiter_space':
            case 'word_delimiter_proximity':
                return ['synonym'];
            case 'synonym':
                return ['word_delimiter_space', 'word_delimiter_proximity'];
        }
    }
}

/**
 *
 */
class pmatch_interpreter_word_delimiter_space extends pmatch_interpreter_item {
    /**
     * @var string
     */
    protected $pattern = '~\s+~';
}

/**
 *
 */
class pmatch_interpreter_word_delimiter_proximity extends pmatch_interpreter_item {
    /**
     * @var string
     */
    protected $pattern = '~\_~';
}

/**
 *
 */
class pmatch_interpreter_word extends pmatch_interpreter_item_with_subcontents {
    /**
     * {@inheritDoc}
     *
     * @param array $foundsofar (optional) items found so far, if any
     * @return array the types of sub contents that could come next
     */
    protected function next_possible_subcontent($foundsofar) {
        return ['character_in_word', 'special_character_in_word',
                     'wildcard_match_multiple', 'wildcard_match_single', ];
    }
}

/**
 *
 */
class pmatch_interpreter_number extends pmatch_interpreter_item {
    /**
     * {@inheritdoc}
     *
     * @param array $pmatchoptions The options for this pmatch.
     */
    public function __construct($pmatchoptions) {
        parent::__construct($pmatchoptions);
        $this->pattern = '~'.PMATCH_NUMBER.'~';
    }
}

/**
 *
 */
class pmatch_interpreter_character_in_word extends pmatch_interpreter_item {
    /**
     * {@inheritdoc}
     *
     * @param array $pmatchoptions The options for this pmatch.
     */
    public function __construct($pmatchoptions) {
        parent::__construct($pmatchoptions);
        $this->pattern = '~'.PMATCH_CHARACTER.'~';
    }
}

/**
 *
 */
class pmatch_interpreter_special_character_in_word extends pmatch_interpreter_item {
    /**
     * {@inheritdoc}
     *
     * @param array $pmatchoptions The options for this pmatch.
     */
    public function __construct($pmatchoptions) {
        parent::__construct($pmatchoptions);
        $this->pattern = '~\\\\'.PMATCH_SPECIAL_CHARACTER.'~';
    }
}

/**
 *
 */
class pmatch_interpreter_wildcard_match_single extends pmatch_interpreter_item {
    /**
     * @var string
     */
    protected $pattern = '~\?~';
}

/**
 *
 */
class pmatch_interpreter_wildcard_match_multiple extends pmatch_interpreter_item {
    /**
     * @var string
     */
    protected $pattern = '~\*~';
}
