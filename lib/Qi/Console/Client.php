<?php
/**
 * Console Client class file 
 *
 * @package Qi
 * @subpackage Console
 */

/**
 * Console Client class
 * 
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_Client
{
    /**
     * Run time arguments
     * 
     * @var string
     */
    protected $_args = '';

    /**
     * Terminal
     * 
     * @var mixed
     */
    protected $_terminal;

    /**
     * Verbosity level
     *
     * @var int
     */
    protected static $_verbose = 0;

    /**
     * Constructor
     * 
     * @param Qi_Console_ArgV $args Args object
     * @param Qi_Console_Terminal $terminal Terminal object
     * @return void
     */
    public function __construct(Qi_Console_ArgV $args,
        Qi_Console_Terminal $terminal)
    {
        $this->_args = $args;

        $this->_terminal = $terminal;

        $this->init();
    }

    /**
     * Method to be overwritten in the extending class
     * 
     * @return void
     */
    public function init()
    {
    }

    /**
     * Display a warning message
     *
     * @param string $message Warning message
     * @param bool $ensureNewline Whether a new line should be appended
     * @return void
     */
    protected function _displayWarning($message, $ensureNewline = true)
    {
        $this->_displayMessage($message, $ensureNewline, 1); //red
    }

    /**
     * Display a message
     *
     * @param mixed $message Message
     * @param mixed $ensureNewline Whether a new line should be appended
     * @param int $color Color to use
     * @return void
     */
    protected function _displayMessage($message, $ensureNewline = true,
        $color = 2)
    {
        if ($ensureNewline && substr($message, -1) != "\n") {
            $message .= "\n";
        }

        $this->_terminal->setaf($color);
        echo $message;
        $this->_terminal->op();
    }

    /**
     * Display an error
     *
     * @param string $message Error message
     * @return void
     */
    protected function _displayError($message)
    {
        echo "\n";
        $this->_terminal->pretty_message($message, 7, 1);
        echo "\n";
    }

    /**
     * Exit with error message
     *
     * @param string $message Error message
     * @return void
     */
    protected function _halt($message)
    {
        $this->_displayError($message);
        $this->_safeExit(2);
    }

    /**
     * Exit program safely
     * 
     * @param int $status Exit status
     * @return void
     */
    protected function _safeExit($status = 0)
    {
        if ($this->_terminal->isatty()) {
            $this->_resetTty();
        }

        exit($status);
    }

    /**
     * Reset tty mode
     *
     * If not windows, revert back to a sane tty
     * 
     * @return void
     */
    protected function _resetTty()
    {
        if (DIRECTORY_SEPARATOR != "\\") {
            // unix
            shell_exec('stty sane');
        }
    }
}
