<?php
/**
 * Exception Handler (console version) 
 *
 * @package Qi
 * @subpackage Console
 */

/**
 * Console Exception Handler class
 * 
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_ExceptionHandler
{
    /**
     * Constructor
     * 
     * @param Qi_Console_Terminal $terminal Terminal object
     * @param bool $bindHandlers Whether to immediately bind handlers
     * @return void
     */
    public function __construct($terminal, $bindHandlers = false)
    {
        $this->_terminal = $terminal;

        if ($bindHandlers === true) {
            $this->bindHandlers();
        }
    }

    /**
     * Bind error handlers
     * 
     * @return void
     */
    public function bindHandlers()
    {
        set_exception_handler(array($this, 'handleException'));
        set_error_handler(array($this, 'handleError'));
    }

    /**
     * Handle error
     * 
     * @return void
     */
    public function handleError()
    {
        list($errno, $message, $file, $line) = func_get_args();

        $message = self::_getErrorCodeName($errno)
            . ": " . $message . " in " . $file . ":" . $line;

        $this->_terminal->setaf(1);
        echo $message . "\n";
        $this->_terminal->op();
    }

    /**
     * Handle exception
     * 
     * @param Exception $e
     * @return void
     */
    public function handleException(Exception $e)
    {
        echo "\n";
        $this->_terminal->pretty_message($e->getMessage(), 7, 1);
        echo "\n";
        
        exit(1);
    }

    /**
     * Convert an error code into the PHP error constant name
     *
     * @param int $code The PHP error code
     * @return string
     */
    private static function _getErrorCodeName($code)
    {
        $error_levels = array(
            1     => 'E_ERROR',
            2     => 'E_WARNING',
            4     => 'E_PARSE',
            8     => 'E_NOTICE',
            16    => 'E_CORE_ERROR',
            32    => 'E_CORE_WARNING',
            64    => 'E_COMPILE_ERROR',
            128   => 'E_COMPILE_WARNING',
            256   => 'E_USER_ERROR',
            512   => 'E_USER_WARNING',
            1024  => 'E_USER_NOTICE',
            2048  => 'E_STRICT',
            4096  => 'E_RECOVERABLE_ERROR',
            8192  => 'E_DEPRECATED',
            16384 => 'E_USER_DEPRECATED',
        );

        return $error_levels[$code];
    }
}
