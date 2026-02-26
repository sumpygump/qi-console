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
    protected $args = '';

    /**
     * Terminal
     *
     * @var mixed
     */
    protected $terminal;

    /**
     * Verbosity level
     *
     * @var int
     */
    protected static $verbose = 0;

    /**
     * Constructor
     *
     * @param Qi_Console_ArgV $args Args object
     * @param Qi_Console_Terminal $terminal Terminal object
     * @return void
     */
    public function __construct(
        Qi_Console_ArgV $args,
        Qi_Console_Terminal $terminal
    ) {
        $this->args = $args;

        $this->terminal = $terminal;

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
    protected function displayWarning($message, $ensureNewline = true)
    {
        $this->displayMessage($message, $ensureNewline, 1); //red
    }

    /**
     * Display a message
     *
     * @param mixed $message Message
     * @param mixed $ensureNewline Whether a new line should be appended
     * @param int $color Color to use
     * @return void
     */
    protected function displayMessage(
        $message,
        $ensureNewline = true,
        $color = 2
    ) {
        if ($ensureNewline && substr($message, -1) != "\n") {
            $message .= "\n";
        }

        $this->terminal->setaf($color);
        echo $message;
        $this->terminal->op();
    }

    /**
     * Display an error
     *
     * @param string $message Error message
     * @return void
     */
    protected function displayError($message)
    {
        echo "\n";
        $this->terminal->pretty_message($message, 7, 1);
        echo "\n";
    }

    /**
     * Exit with error message
     *
     * @param string $message Error message
     * @return void
     */
    protected function halt($message)
    {
        $this->displayError($message);
        $this->_safeExit(2);
    }

    /**
     * Exit program safely
     *
     * @param int $status Exit status
     * @return void
     */
    protected function safeExit($status = 0)
    {
        if ($this->terminal->isatty()) {
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
    protected function resetTty()
    {
        if (DIRECTORY_SEPARATOR != "\\") {
            // unix
            shell_exec('stty sane');
        }
    }
}
