<?php
/**
 * Qi_Console_Client Test class file
 *
 * @package Qi
 */

/**
 * TestingClient
 *
 * @uses Qi_Console_Client
 * @package Qi
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class TestingClient extends Qi_Console_Client
{
    /**
     * displayWarning
     *
     * @param mixed $message Message
     * @param bool $ensureNewline Whether to ensure new line
     * @return void
     */
    public function displayWarning($message, $ensureNewline = true)
    {
        return parent::_displayWarning($message, $ensureNewline);
    }

    /**
     * displayMessage
     *
     * @param mixed $message Message
     * @param bool $ensureNewline Whether to ensure new line
     * @param int $color Color value
     * @return void
     */
    public function displayMessage($message, $ensureNewline = true, $color = 2)
    {
        return parent::_displayMessage($message, $ensureNewline, $color);
    }
}

/**
 * Qi_Console_Client Test class
 *
 * @uses BaseTestCase
 * @package Qi
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_ClientTest extends BaseTestCase
{
    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        // We need to fake an xterm Terminfo
        $terminfo = new Qi_Console_Terminfo(false, 'xterm');

        $options = array(
            'terminfo' => $terminfo,
        );

        $terminal = new Qi_Console_Terminal($options);
        $terminal->setIsatty(true);

        $args = new Qi_Console_ArgV(array('a' => '1'));

        $this->_object = new TestingClient($args, $terminal);
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
     * testConstructor
     *
     * @expectedException PHPUnit_Framework_Error
     * @return void
     */
    public function testConstructor()
    {
        $client = new Qi_Console_Client();
    }

    /**
     * Test display warning normal behavior
     *
     * @return void
     */
    public function testDisplayWarning()
    {
        ob_start();
        $this->_object->displayWarning('There is something amiss');
        $result = ob_get_contents();
        ob_end_clean();

        $expected = "\033[31mThere is something amiss\n\033[39;49m";
        $this->assertEquals($expected, $result);
    }

    /**
     * Test display warning with new line not enforced
     *
     * @return void
     */
    public function testDisplayWarningNoNewLine()
    {
        ob_start();
        $this->_object->displayWarning('There is something amiss', false);
        $result = ob_get_contents();
        ob_end_clean();

        $expected = "\033[31mThere is something amiss\033[39;49m";
        $this->assertEquals($expected, $result);
    }
}
