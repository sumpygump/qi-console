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
class Qi_Console_StdTest extends TestCase
{
    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp()
    {
    }

    /**
     * Tear down after each test
     *
     * @return void
     */
    public function tearDown()
    {
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
