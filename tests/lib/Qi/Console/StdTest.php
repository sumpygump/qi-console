<?php

/**
 * Qi_Console_Std Test class file
 *
 * @package Qis
 */

use PHPUnit\Framework\TestCase;

/**
 * Qi_Console_Std Test class
 *
 * @package Qis
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class StdTest extends TestCase
{
    public function testIn()
    {
        $input = Qi_Console_Std::in('fgets', '1123');
        $this->assertEquals('1123', $input);
    }

    public function testMultiIn()
    {
        Qi_Console_Std::$inputs = ["first", "second"];
        $input = Qi_Console_Std::in('fgets');
        $this->assertEquals('first', $input);

        $input = Qi_Console_Std::in('fgets');
        $this->assertEquals('second', $input);

        $input = Qi_Console_Std::in('fgets');
        $this->assertEquals('', $input);
    }

    /**
     * Test out method
     *
     * @return void
     */
    public function testOut()
    {
        ob_start();
        Qi_Console_Std::out('text');
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('text', $result);
    }
}
