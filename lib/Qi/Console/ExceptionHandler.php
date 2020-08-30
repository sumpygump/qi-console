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
    protected $isDebug = false;

    /**
     * Constructor
     *
     * @param Qi_Console_Terminal $terminal Terminal object
     * @param bool $bindHandlers Whether to immediately bind handlers
     * @return void
     */
    public function __construct($terminal, $bindHandlers = false, $isDebug = false)
    {
        $this->_terminal = $terminal;

        if ($bindHandlers === true) {
            $this->bindHandlers($isDebug);
        }
    }

    /**
     * Bind error handlers
     *
     * @return void
     */
    public function bindHandlers($isDebug = false)
    {
        $this->isDebug = (bool) $isDebug;

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
     * @param Exception $exception Exception object
     * @return void
     */
    public function handleException(\Throwable $exception)
    {
        $message = sprintf("%s: %s", get_class($exception), $exception->getMessage());

        if ($this->isDebug) {
            $message = sprintf(
                "%s: %s in %s on line %s",
                get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()
            );
        }

        echo "\n";
        $this->_terminal->pretty_message($message, 7, 1);
        echo "\n";

        if ($this->isDebug) {
            printf("%s\n", $exception->getTraceAsString());
        }

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
