<?php
/**
 * Console Std class file
 *
 * @package Qi
 * @subpackage Console
 */

/**
 * Wrapper for stdin, stdout and stderr
 *
 * This class provides methods for sending and receiving
 * input from stdin and output to stdout. Good for making
 * cli php scripts
 *
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @version $Id$
 */
class Qi_Console_Std
{
    /**
     * Get from stdin
     *
     * @param string $mode Php function to use to get input
     * @param string $response Forced response if unit test
     * @return string Input from stdin
     */
    public static function in($mode = 'fgets', $response = null)
    {
        if (strpos($_SERVER['SCRIPT_NAME'], 'phpunit')) {
            return $response;
        }
        switch ($mode) {
        case "fgetc":
            return fgetc(STDIN);
            break;
        case "fgets":
        default:
            return fgets(STDIN);
            break;
        }
    }

    /**
     * Send a string to the stdout.
     *
     * @param string $text The string to send
     * @return void
     */
    public static function out($text)
    {
        if (strpos($_SERVER['SCRIPT_NAME'], 'phpunit')) {
            // detect if running unit tests and output normally
            print $text;
        } else {
            // otherwise strictly output to stdout
            fwrite(STDOUT, $text);
        }
    }

    /**
     * Send a string to stderr.
     *
     * @param string $text The string to send
     * @return void
     */
    public static function err($text)
    {
        fwrite(STDERR, $text);
    }
}
