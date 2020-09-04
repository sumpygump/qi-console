<?php
/**
 * Qi Console Terminfo Test Class file
 *
 * @package Qi
 */

use PHPUnit\Framework\TestCase;

/**
 * Qi_Console_TerminfoTest
 *
 * @package Qi
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_TerminfoTest extends TestCase
{
    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->_createObject();
    }

    /**
     * Create object
     *
     * @return void
     */
    protected function _createObject()
    {
        $this->_object = new Qi_Console_Terminfo(false, 'xterm');
    }

    /**
     * Test get capability
     *
     * @return void
     */
    public function testGetCapability()
    {
        $capabilityInfo = $this->_object->getCapability('op');

        $expected = '\E[39;49m';

        $this->assertEquals($expected, $capabilityInfo);
    }

    /**
     * Test get capability verbose
     *
     * @return void
     */
    public function testGetCapabilityVerbose()
    {
        $capabilityInfo = $this->_object->getCapability('op', true);

        $expected = "op : (orig_pair) "
            . "Set default pair to its original value = '";

        $this->assertStringContainsString($expected, $capabilityInfo);
    }

    /**
     * Test get capability no exist
     *
     * @return void
     */
    public function testGetCapabilityNoExist()
    {
        $capabilityInfo = $this->_object->getCapability('xxxxxx', true);

        $this->assertFalse($capabilityInfo);
    }

    /**
     * Test display capability
     *
     * @return void
     */
    public function testDisplayCapability()
    {
        $info = $this->_object->displayCapability('op');

        $expected = "op : (orig_pair) "
            . "Set default pair to its original value = '\E[39;49m'";

        $this->assertEquals($expected, $info);
    }

    /**
     * Test display capability not capable
     *
     * @return void
     */
    public function testDisplayCapabilityNotCapable()
    {
        // Load a less capable terminal
        $this->_object = new Qi_Console_Terminfo(false);

        $info = $this->_object->displayCapability('sgr1');

        $expected = "sgr1 : (set_a_attributes) "
            . "Define second set of video attributes #1-#6 = 'NOT CAPABLE'";

        $this->assertEquals($expected, $info);
    }

    /**
     * Test has capability
     *
     * @return void
     */
    public function testHasCapability()
    {
        $result = $this->_object->hasCapability('am');

        $this->assertTrue($result);
    }

    /**
     * Test has capability no capable
     *
     * @return void
     */
    public function testHasCapabilityNotCapable()
    {
        // Load a less capable terminal
        $this->_object = new Qi_Console_Terminfo(false);

        $result = $this->_object->hasCapability('sgr1');

        $this->assertFalse($result);
    }

    /**
     * Test dump
     *
     * @return void
     */
    public function testDump()
    {
        ob_start();
        $this->_object->dump();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString('[am] => (', $result);
    }

    /**
     * Test dump cache
     *
     * @return void
     */
    public function testDumpCache()
    {
        // Run a cap so the cache gets populated with something
        $this->_object->doCapability('op');
        $this->_object->doCapability('op1');
        $this->_object->doCapability('op');

        ob_start();
        $this->_object->dumpCache();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString('op => 1B', $result);
    }

    /**
     * Test magic call
     *
     * @return void
     */
    public function testMagicCall()
    {
        $result = $this->_object->setab(5);

        $expected = chr(27) . '[';

        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test magic call none
     *
     * @return void
     */
    public function testMagicCallNone()
    {
        $result = $this->_object->foofoo();

        $this->assertFalse($result);
    }

    /**
     * Test do capability with cache
     *
     * @return void
     */
    public function testDoCapabilityWithCache()
    {
        $this->_object->op();
        $this->_object->op();

        ob_start();
        $this->_object->dumpCache();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertNotEmpty($result);
    }

    /**
     * Test get terminfo bin data
     *
     * @return void
     */
    public function testGetTerminfoBinData()
    {
        $this->_object->getTerminfoBinData();
        $this->assertTrue(true);
    }
}
