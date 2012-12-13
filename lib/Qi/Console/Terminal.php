<?php
/**
 * Console Terminal class file
 *
 * @package Qi
 * @subpackage Console
 */

/** include Qi_Console_Std */
require_once 'Qi/Console/Std.php';

/**
 * Terminal
 *
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @version $Id$
 */
class Qi_Console_Terminal
{
    /**
     * @var object Terminfo Storage for the Terminfo object
     */
    protected $_terminfo;

    /**
     * @var float Width in columns of the terminal
     */
    protected $_columns = 80;

    /**
     * @var float Height in rows of the terminal
     */
    protected $_lines = 25;

    /**
     * @var mixed Whether this is a terminal
     */
    protected $_isatty = true;

    /**
     * Whether terminal is cygwin
     *
     * @var bool
     */
    protected $_isCygwin = false;

    /**
     * Create new Terminal object
     *
     * @param array $options options for initializing the object
     * @return void
     */
    public function __construct($options = array())
    {
        if (isset($_SERVER['TERM'])
            && strpos($_SERVER['TERM'], 'cygwin') !== false
        ) {
            $this->_isCygwin = true;
        }

        $this->_setIsatty();

        // Pass in terminfo object
        if (isset($options['terminfo'])) {
            $this->_terminfo = $options['terminfo'];
        } else {
            include_once 'Qi/Console/Terminfo.php';
            $this->_terminfo = new Qi_Console_Terminfo();
        }

        if ($this->_isatty) {
            $this->get_columns(true);
            $this->get_lines(true);
        }
    }

    /**
     * Set whether output is a terminal
     *
     * @return void
     */
    private function _setIsatty()
    {
        if (isset($_SERVER['TERM'])) {
            $term = $_SERVER['TERM'];
        } else {
            $term = null;
        }

        if ($this->_isCygwin) {
            $this->_isatty = true;
            return true;
        }

        if ($term == null && DIRECTORY_SEPARATOR == '\\') {
            $this->_isatty = false;
            return false;
        }

        if (!function_exists('posix_isatty')) {
            $this->_isatty = false;
            return false;
        }
        $this->_isatty = posix_isatty(STDOUT);
    }

    /**
     * Return whether output is a terminal
     *
     * @return bool
     */
    public function isatty()
    {
        return $this->_isatty;
    }

    /**
     * Set Is a tty manually
     *
     * If no parameters used it will default back to the original state based
     * on the default detection routine
     *
     * @param bool $val Value to set isatty
     * @return void
     */
    public function setIsatty($val = null)
    {
        if (null === $val) {
            $this->_setIsatty();
            return;
        }

        $this->_isatty = (bool) $val;
    }

    /**
     * A specialized print function for terminfo caps
     *
     * Only echo the text if the stdout is a tty
     *
     * @param mixed $text The text to print
     * @return void
     */
    public function printterm($text)
    {
        if ($this->_isatty) {
            Qi_Console_Std::out($text);
        }
    }

    /**
     * Clear the screen
     *
     * @return object Terminal self (to allow for chaining)
     */
    public function clear()
    {
        $this->printterm($this->_terminfo->clear());
        return $this;
    }

    /**
     * Move the cursor to a row and column
     *
     * @param int $row the row (range: 0-max)
     * @param int $column the column (range: 0-max)
     * @return object Terminal self (to allow for chaining)
     */
    public function locate($row, $column)
    {
        if (!is_int($row) || !is_int($column)) {
            return $this;
        }

        $this->printterm($this->_terminfo->cup($row, $column));
        return $this;
    }

    /**
     * Switch the style of characters to bold (bright)
     *
     * @return object Terminal self (to allow for chaining)
     */
    public function bold_type()
    {
        $this->printterm($this->_terminfo->bold());
        return $this;
    }

    /**
     * set the foreground (text) color
     *
     * @param int $num integer value corresponding to a color (0-8)
     * @return object Terminal self (to allow for chaining)
     */
    public function set_fgcolor($num)
    {
        $this->printterm($this->_terminfo->setaf($num));
        return $this;
    }

    /**
     * set the background color
     *
     * @param int $num integer value corresponding to a color (0-8)
     * @return object Terminal self (to allow for chaining)
     */
    public function set_bgcolor($num)
    {
        $this->printterm($this->_terminfo->setab($num));
        return $this;
    }

    /**
     * Prompt the user for input
     *
     * @param string $text The text string with the prompt message
     * @return object Terminal self (to allow for chaining)
     */
    public function prompt($text)
    {
        print $text;
        return Qi_Console_Std::in();
    }

    /**
     * Attempt to get the number of columns of the current terminal
     *
     * @param bool $force Whether to force getting value from terminal
     * @return mixed
     */
    public function get_columns($force = false)
    {
        if (DIRECTORY_SEPARATOR != "\\" || $this->_isCygwin) {
            if ($force) {
                $cmd = "tput cols";
                exec($cmd, $output, $return);
                if (!$return) {
                    $this->_columns = trim($output[0]);
                }
            } else {
                $this->_columns = $this->_terminfo->getCapability('cols');
            }
        } else {
            // TODO: if windows, use the command 'mode' to get the columns
            $this->_columns = 80;
        }

        return $this->_columns;
    }

    /**
     * Attempt to get the number of rows of the current terminal
     *
     * @param mixed $force Whether to force getting value from terminal
     * @return int
     */
    public function get_lines($force=false)
    {
        if (DIRECTORY_SEPARATOR != "\\" || $this->_isCygwin) {
            if ($force) {
                $cmd = "tput lines";
                exec($cmd, $output, $return);
                if (!$return) {
                    $this->_lines = trim($output[0]);
                }
            } else {
                $this->_lines = $this->_terminfo->getCapability('lines');
            }
        } else {
            // TODO: if windows, use the command 'mode' to get the lines
            $this->_lines = 25 ;
        }
        return $this->_lines;
    }

    /**
     * Attempt to center the given text on the current line
     *
     * @param string $text The text to be centered
     * @return object Terminal self (to allow for chaining)
     */
    public function center_text($text)
    {
        $x = (int) (($this->_columns - strlen($text)) / 2);

        // Assume cursor is on the beginning of the line
        // Didn't use hpa for lack of support
        $text = str_pad($text, $this->_columns, ' ', STR_PAD_BOTH);

        echo $text;

        return $this;
    }

    /**
     * Format and output a pretty message (colors, padding)
     *
     * @param string $text The text to display
     * @param int $fg Foreground color
     * @param int $bg Background color
     * @param mixed $size The width of the text box
     * @param bool $verticalPadding Include vertical padding
     * @return object Terminal self (to allow for chaining)
     */
    public function pretty_message($text, $fg=7, $bg=4, $size=null,
        $verticalPadding = true)
    {
        if (null === $size) {
            $size = $this->_columns;
        }
        $len = strlen($text) + 4;

        // Setup a string to switch to original pair,
        // add a newline character and then switch back to the desired colors
        $start   = $this->do_setaf($fg) . $this->do_setab($bg);
        $end     = $this->do_op() . "\n";
        $newline = $end . $start;

        if ($len > $size || strpos($text, "\n") !== false) {
            $len   = $size;
            $text  = wordwrap($text, $size-4, "\n");
            $lines = explode("\n", $text);
            $text  = '';
            foreach ($lines as $line) {
                $line = "  " . trim($line);

                $text .= str_pad($line, $size, ' ') . $newline;
            }
        } else {
            $text = "  " . $text . "  " . $newline;
        }

        if ($verticalPadding) {
            $padding = str_repeat(' ', $len);
        } else {
            $padding = '';
            $end     = trim($end);
            $newline = $end . $start;
        }

        $out = $start
            . $padding . $newline
            . $text
            . $padding
            . $end;

        print $out;

        return $this;
    }

    /**
     * Output a box
     *
     * @param int $y The y position of the upper left hand corner of the box
     * @param int $x The x position of the upper left hand corner of the box
     * @param int $w The width of the box
     * @param int $h The height of the box
     * @return void
     */
    public function make_box($y, $x, $w, $h)
    {
        if ($this->has_capability('smacs')) {
            //$this->enacs();
            //$this->smacs();
            $this->start_alt_charset_mode();
            $tl    = "l";
            $tr    = "k";
            $bl    = "m";
            $br    = "j";
            $horiz = "q";
            $vert  = "x";
        } else {
            $tl    = "+";
            $tr    = "+";
            $bl    = "+";
            $br    = "+";
            $horiz = "-";
            $vert  = "|";
        }

        $this->locate($y, $x);

        echo $tl . str_repeat($horiz, $w) . $tr;

        for ($i = 1; $i < $h; $i++) {
            $this->locate($y + $i, $x);
            echo $vert . str_repeat(' ', $w) . $vert;
        }

        $this->locate($y+$h, $x);

        echo $bl . str_repeat($horiz, $w) . $br;

        if ($this->has_capability('rmacs')) {
            //$this->rmacs();
            $this->end_alt_charset_mode();
        }
    }

    /**
     * Alternate way of invoking smacs()
     *
     * @return void
     */
    public function start_alt_charset_mode()
    {
        $this->printterm(chr(27) . chr(40) . chr(48));
    }

    /**
     * Alternate way of invoking rmacs()
     *
     * @return void
     */
    public function end_alt_charset_mode()
    {
        $this->printterm(chr(27) . chr(40) . chr(66));
    }

    /**
     * Magic call method
     *
     * Attempts to execute the method to _terminfo->doCapability()
     *
     * @param string $method The name of the method being called
     * @param array $args An array of arguments passed to the method
     * @return string
     */
    public function __call($method, $args)
    {
        $out  = '';
        $echo = true;

        if (substr($method, 0, 3) == 'do_') {
            $method = substr($method, 3);
            $echo   = false;
        }

        if ($this->_terminfo->hasCapability($method)) {
            $args = array_merge(array($method), $args);
            $out  = call_user_func_array(
                array($this->_terminfo, 'doCapability'), $args
            );
        } else {
            // Not a capability
            // do nothing
        }

        if ($echo) {
            $this->printterm($out);
        } else {
            // Need to detect whether output is to a tty
            if ($this->_isatty) {
                return $out;
            } else {
                return '';
            }
        }

        // return object so that methods can be chained
        return $this;
    }

    /**
     * Just get the capability string parsed, instead of echoing it
     *
     * @param string $cap_name The capability name
     * @param array $args Args to pass to the capability
     * @return string
     */
    public function do_capability($cap_name, $args = array())
    {
        if (!$this->_terminfo->hasCapability($cap_name)) {
            trigger_error($cap_name . " not a cap");
        }

        $args = array_merge(array($cap_name), $args);
        $out  = call_user_func_array(
            array($this->_terminfo, 'doCapability'), $args
        );

        if ($this->_isatty) {
            return $out;
        } else {
            return '';
        }
    }

    /**
     * Get the (verbose) capability string of a certain capability.
     *
     * @param string $cap_name The name of the capability code
     * @param bool $verbose Flag to indicate if verbose results are returned
     * @return string
     */
    public function get_capability($cap_name, $verbose=false)
    {
        if ($verbose) {
            return $this->_terminfo->displayCapability($cap_name);
        } else {
            return $this->_terminfo->getCapability($cap_name, $verbose);
        }
    }

    /**
     * Report whether the _terminfo object has a certain capability
     *
     * @param mixed $cap_name Capability name
     * @return bool
     */
    public function has_capability($cap_name)
    {
        return $this->_terminfo->hasCapability($cap_name);
    }

    /**
     * Whether terminal is cygwin
     *
     * @return void
     */
    public function isCygwin()
    {
        return $this->_isCygwin;
    }

    /**
     * Execute _terminfo->dump()
     * Will ouput a listing of all the capabilities with their descriptions
     *
     * @return void
     */
    public function dump()
    {
        $this->_terminfo->dump();
    }

    /**
     * Debugging method to dump the cache for examining
     *
     * @return void
     */
    public function dump_cache()
    {
        $this->_terminfo->dump_cache();
    }
}
