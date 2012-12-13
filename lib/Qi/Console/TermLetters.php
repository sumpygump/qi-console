<?php
/**
 * Term Letters class file
 *
 * @package Qi
 * @subpackage Console
 */

/**
 * TermLetters 
 * 
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_TermLetters
{
    /**
     * Terminal object
     *
     * @var object
     */
    protected $_terminal;

    /**
     * Color of text
     * 
     * @var int
     */
    protected $_color = 2;

    /**
     * Default color
     * 
     * @var int
     */
    protected $_defaultColor = 2;

    /**
     * Whether to consider escape codes when parsing text
     * 
     * @var bool
     */
    protected $_useEscapeCodes = true;

    /**
     * Buffer for lines of the letters
     * 
     * @var array
     */
    protected $_lineBuffer = array();

    /**
     * Length of the buffer
     * 
     * @var int
     */
    protected $_bufferLen = 0;

    /**
     * Specified width in chars of printable area
     * 
     * @var float
     */
    protected $_width = 180;

    /**
     * Enable auto wrap of long lines
     * 
     * @var mixed
     */
    protected $_enableWrap = true;

    /**
     * Constructor
     * 
     * @param array $options Additional options
     * @return void
     */
    public function __construct($options = array())
    {
        if (isset($options['color'])) {
            $this->_color = $options['color'];
        }

        if (isset($options['terminal'])) {
            $this->_terminal = $options['terminal'];
        } else {
            $this->_terminal = new Qi_Console_Terminal();
        }

        $this->_width = $this->_terminal->get_columns($this->_terminal->isatty());

        if (isset($options['width'])) {
            $this->_width = $options['width'];
        }

        if (isset($options['enable_wrap'])) {
            $this->_enableWrap = (bool) $options['enableWrap'];
        }

        if (isset($options['use_escape_codes'])) {
            $this->_useEscapeCodes = (bool) $options['use_escape_codes'];
        }
    }

    /**
     * Echo a termlet phrase
     * 
     * @param string $string String to convert to letters
     * @return void
     */
    public function techo($string)
    {
        $string = str_split($string);
        $size   = count($string);

        for ($i = 0; $i < $size; $i++) {
            $char = $string[$i];
            switch ($char) {
            case "\n":
                $this->_echoBuffer();
                break;
            case "\\":
                if (!$this->_useEscapeCodes) {
                    $len = $this->addChar($char);
                    continue;
                }

                // If this was the last letter, add it and move along
                if ($i + 1 == $size) {
                    $this->addChar("\\");
                    continue;
                }

                $nextChar = $string[$i + 1];
                if (preg_match("/[0-8]/", $nextChar)) {
                    if ($nextChar == 0) {
                        $this->_color = $this->_defaultColor;
                    } else {
                        $this->_color = $nextChar;
                    }
                    $i++;
                    continue;
                }

                switch ($nextChar) {
                case "n":
                    $this->_echoBuffer();
                    $i++;
                    break;
                case "\\":
                    $i++;
                    // fall through
                default:
                    $this->addChar("\\");
                    break;
                }
                break;
            default:
                $len = $this->addChar($char);
                break;
            }
        }

        $this->_echoBuffer();
    }

    /**
     * Echo the current buffer
     * 
     * @return void
     */
    protected function _echoBuffer()
    {
        echo implode("\n", $this->_lineBuffer);
        echo "\n";
        $this->_resetLineBuffer();
        $this->_terminal->op();
    }

    /**
     * Reset the line buffer
     * 
     * @return void
     */
    protected function _resetLineBuffer()
    {
        $this->_lineBuffer = array();
        $this->_bufferLen = 0;
    }

    /**
     * Add a char to the buffer
     * 
     * @param string $char Character to add
     * @return int The new buffer length
     */
    public function addChar($char)
    {
        $text = $this->generate_letter($char);

        if ($text === false) {
            return false;
        }

        $lines = explode("\n", $text);

        if ($this->_enableWrap
            && $this->getLetterWidth($char) + $this->_bufferLen + 1 > $this->_width
        ) {
            $this->_echoBuffer();
        } 

        $l = 0;
        foreach ($lines as $line) {
            $this->_addToLineBuffer($l, $line);
            $l++;
        }

        // Add the width of this char to the buffer len (include the space 
        // in between the chars
        $this->_bufferLen = $this->_bufferLen + $this->getLetterWidth($char) + 1;

        return $this->_bufferLen;
    }

    /**
     * Add string to a specific line buffer
     *
     * Each line buffer represents a line of text. Each line is stored in the 
     * array so that strings representing the letter shapes can be added to a 
     * line independently until the buffer needs to be flushed (echoed)
     *
     * E.g.
     *  Line 1: X  x XXXXX
     *  Line 2: X  X   X
     *  Line 3: XXXX   X
     *  Line 4: X  X   X
     *  Line 5: X  X XXXXX
     * 
     * @param int $index Index of line buffer
     * @param string $text String to append to that buffer
     * @return int Length of buffer
     */
    protected function _addToLineBuffer($index, $text)
    {
        if (!isset($this->_lineBuffer[$index])) {
            $this->_lineBuffer[$index] = $text;
        } else {
            $this->_lineBuffer[$index] = $this->_lineBuffer[$index] . ' ' . $text;
        }

        return strlen($this->_lineBuffer[$index]);
    }

    /**
     * Get the letter width for a given letter
     * 
     * @param string $letter Letter (one character)
     * @return int
     */
    public function getLetterWidth($letter)
    {
        if (!isset($this->_letters[$letter])) {
            return 0;
        }

        $letterRows = explode("\n", $this->_letters[$letter]);

        return strlen($letterRows[0]);
    }

    /**
     * Generate a letter shape
     * 
     * @param string $letter
     * @return string
     */
    public function generate_letter($letter)
    {
        if (!isset($this->_letters[$letter])) {
            return false;
        }

        $letter_data = $this->_letters[$letter];
        $len = strlen($letter_data);
        $out = '';
        $letter_data = str_split($letter_data);

        foreach ($letter_data as $char) {
            switch ($char) {
            case ' ':
                $out .= $this->_terminal->do_op();
                $out .= " ";
                break;
            case 'X':
                $out .= $this->_terminal->do_setab($this->_color);
                $out .= " ";
                break;
            case '\'':
                $out .= $this->_terminal->do_setaf($this->_color);
                $out .= $this->uchr(9600);
                break;
            case ',':
                $out .= $this->_terminal->do_setaf($this->_color);
                $out .= $this->uchr(9604);
                break;
            case '#':
                $out .= $this->_terminal->do_setaf($this->_color);
                $out .= $this->uchr(9608);
                break;
            case '-':
                $out .= $this->_terminal->do_setaf($this->_color);
                $out .= $this->uchr(9642);
                break;
            case "\n":
                $out .= $this->_terminal->do_op();
                $out .= "\n";
                break;
            default:
                break;
            }
        }

        $out .= $this->_terminal->do_capability('op');

        return $out;
    }

    /**
     * Get a character given a unicode char code
     *
     * It's a unicode version of chr()
     * 
     * @param int $codes One or more codes
     * @return string
     */
    public function uchr($codes)
    {
        if (is_scalar($codes)) {
            $codes = func_get_args();
        }

        $str = '';
        foreach ($codes as $code) {
            $str.= html_entity_decode('&#' . $code . ';', ENT_NOQUOTES, 'UTF-8');
        }

        return $str;
    }

    /**
     * Definition of default letters set
     * 
     * Uses the following characters to represent the blocks that define each 
     * character: ' , #
     *
     * !"#$%&'()*+,-./0123456789:;<=>?@
     * ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`
     * abcdefghijklmnopqrstuvwxyz{|}~
     *
     * @var array
     */
    protected $_letters = array(
        " " => "    \n    \n    \n    \n    ",
        "!" => " , \n # \n # \n , \n   ",
        '"' => ", ,\n' '\n   \n   \n   ",
        "#" => " , , \n,#,#,\n,#,#,\n # # \n     ",
        "$" => "  , \n,'''\n '',\n'#' \n    ",
        "%" => "     \n,   ,\n  ,' \n,'  ,\n     ",
        "&" => " ,   \n','  \n# ',,\n',,',\n     ",
        "'" => " , \n ' \n   \n   \n   ",
        "(" => " ,'\n#  \n#  \n', \n  '", 
        ")" => "', \n  #\n  #\n ,'\n'  ", 
        "*" => "  ,  \n',#,'\n'###'\n' # '\n     ",
        "+" => "     \n  #  \n''#''\n  '  \n     ",
        "," => "  \n  \n  \n##\n,'",
        "-" => "    \n    \n''''\n    \n    ",
        "." => "  \n  \n  \n##\n  ",
        "/" => "   ,\n  ,'\n ,' \n,'  \n'   ",
        "0" => " ,, \n# ,#\n#' #\n',,'\n    ",
        "1" => " , \n'# \n # \n,#,\n   ",
        "2" => " ,, \n'  #\n ,' \n#,,,\n    ",
        "3" => " ,, \n'  #\n '',\n',,'\n    ",
        "4" => "   , \n ,'# \n#,,#,\n   # \n     ",
        "5" => ",,,,\n#   \n''',\n',,'\n    ",
        "6" => "  ,,\n,'  \n#'',\n',,'\n    ",
        "7" => ",,,,\n  ,'\n #  \n #  \n    ",
        "8" => " ,, \n#  #\n,'',\n',,'\n    ",
        "9" => " ,, \n#  #\n ''#\n,,' \n    ",
        ":" => "  \n##\n  \n##\n  ",
        ";" => "  \n##\n  \n##\n,'",
        "<" => "   ,\n ,' \n',  \n  ',\n    ",
        "=" => "    \n,,,,\n,,,,\n    \n    ",
        ">" => ",   \n ', \n  ,'\n,'  \n    ",
        "?" => " ,, \n'  #\n ,' \n ,  \n    ",
        "@" => " ,, \n#  #\n# ##\n',, \n    ",
        "A" => " ,, \n#  #\n#''#\n#  #\n    ",
        "B" => ",,, \n#  #\n#'',\n#,,'\n    ",
        "C" => " ,, \n#  '\n#   \n',,'\n    ",
        "D" => ",,, \n#  #\n#  #\n#,,'\n    ",
        "E" => ",,,,\n#   \n#'''\n#,,,\n    ",
        "F" => ",,,,\n#   \n#'''\n#   \n    ",
        "G" => " ,, \n#  '\n# '#\n',,'\n    ",
        "H" => ",  ,\n#  #\n#''#\n#  #\n    ",
        "I" => ",,,\n # \n # \n,#,\n   ",
        "J" => " ,,,,\n   # \n   # \n',,' \n     ",
        "K" => ",   ,\n# ,' \n#',  \n#  ',\n     ",
        "L" => ",   \n#   \n#   \n#,,,\n    ",
        "M" => ",   ,\n#','#\n#   #\n#   #\n     ",
        "N" => ",   ,\n#', #\n#  '#\n#   #\n     ",
        "O" => " ,, \n#  #\n#  #\n',,'\n    ",
        "P" => ",,, \n#  #\n#'' \n#   \n    ",
        "Q" => " ,,, \n#   #\n# , #\n',,',\n     ",
        "R" => ",,, \n#  #\n##' \n# ',\n    ",
        "S" => " ,, \n#  '\n '',\n',,'\n    ",
        "T" => ",,,,,\n  #  \n  #  \n  #  \n     ",
        "U" => ",  ,\n#  #\n#  #\n',,'\n    ",
        "V" => ",   ,\n#   #\n#   #\n ',' \n     ",
        "W" => ",   ,\n#   #\n# , #\n#' '#\n     ",
        "X" => ",   ,\n', ,'\n ,', \n#   #\n     ",
        "Y" => ",   ,\n', ,'\n  #  \n  #  \n     ",
        "Z" => ",,,,\n  ,'\n,'  \n#,,,\n    ",
        "[" => "#''\n#  \n#  \n#  \n'''",
        "\\" => ",   \n',  \n ', \n  ',\n   '",
        "]" => "''#\n  #\n  #\n  #\n'''",
        "^" => " , \n' '\n   \n   \n   ",
        "_" => "     \n     \n     \n     \n'''''",
        "`" => ", \n '\n  \n  \n  ",
        "a" => "    \n,,, \n ,,#\n',,#\n    ",
        "b" => ",   \n#,, \n#  #\n#,,'\n    ",
        "c" => "    \n ,,,\n#   \n',,,\n    ",
        "d" => "   ,\n ,,#\n#  #\n',,#\n    ",
        "e" => "    \n ,, \n#,,#\n',,,\n    ",
        "f" => "  , \n # '\n'#' \n #  \n    ",
        "g" => "    \n ,, \n#  #\n',,#\n,,,'",
        "h" => ",   \n#,, \n#  #\n#  #\n    ",
        "i" => ",\n,\n#\n#\n ",
        "j" => "  , \n  , \n  # \n  # \n,,' ",
        "k" => ",   \n#  ,\n#,' \n# ',\n    ",
        "l" => ",  \n#  \n#  \n',,\n   ",
        "m" => "     \n,, , \n# # #\n# # #\n     ",
        "n" => "    \n,,, \n#  #\n#  #\n    ",
        "o" => "    \n ,, \n#  #\n',,'\n    ",
        "p" => "    \n ,, \n#  #\n#,,'\n#   ",
        "q" => "    \n ,, \n#  #\n',,#\n   #",
        "r" => "    \n,,, \n#  '\n#   \n    ",
        "s" => "    \n ,,,\n',, \n,,,'\n    ",
        "t" => " ,  \n,#, \n #  \n ','\n    ",
        "u" => "    \n,  ,\n#  #\n',,#\n    ",
        "v" => "     \n,   ,\n#   #\n ',' \n     ",
        "w" => "     \n,   ,\n# , #\n',','\n     ",
        "x" => "     \n,   ,\n ',' \n,' ',\n     ",
        "y" => "    \n,  ,\n#  #\n',,#\n ,,'",
        "z" => "     \n,,,,,\n  ,' \n,#,,,\n     ",
        "{" => " ,''\n ', \n'', \n #  \n  ''",
        "|" => " # \n # \n # \n # \n ' ",
        "}" => "'', \n ,' \n ,''\n  # \n''  ",
        "~" => "    \n    \n,','\n    \n    ",
    );
}
