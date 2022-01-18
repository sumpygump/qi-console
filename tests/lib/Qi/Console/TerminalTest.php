<?php

/**
 * Qi Console Terminal Test Class file
 *
 * @package Qi
 */

use PHPUnit\Framework\TestCase;

/**
 * Qi_Console_TerminalTest
 *
 * @package Qi
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class TerminalTest extends TestCase
{
    public $object;

    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        $_SERVER['TERM'] = 'cygwin';
        $this->createObject();
    }

    /**
     * Create object
     *
     * @return void
     */
    protected function createObject()
    {
        $terminfo = new Qi_Console_Terminfo(false, 'xterm');

        $options = array(
            'terminfo' => $terminfo,
        );

        $this->object = new Qi_Console_Terminal($options);
        $this->object->setIsatty(true);
    }

    /**
     * Test constructing with no arguments
     *
     * @return void
     */
    public function testConstructor()
    {
        $this->object = new Qi_Console_Terminal();

        $this->assertTrue(is_object($this->object));
    }

    /**
     * Test constructor with terminfo
     *
     * @return void
     */
    public function testConstructorWithTerminfo()
    {
        $terminfo = new Qi_Console_Terminfo();

        $options = array(
            'terminfo' => $terminfo,
        );

        $this->object = new Qi_Console_Terminal($options);

        $this->assertTrue(is_object($this->object));
        $this->assertInstanceOf('Qi_Console_Terminal', $this->object);
    }

    /**
     * Test cygwin
     *
     * @return void
     */
    public function testCygwin()
    {
        $_SERVER['TERM'] = 'cygwin';

        $this->object = new Qi_Console_Terminal();

        $this->assertTrue(is_object($this->object));
        $this->assertTrue($this->object->isCygwin());
        $this->assertTrue($this->object->isatty());
    }

    /**
     * Test is a tty
     *
     * @return void
     */
    public function testIsAtty()
    {
        $_SERVER['TERM'] = 'x';
        $this->object = new Qi_Console_Terminal();

        $this->assertTrue(is_object($this->object));
        $this->assertFalse($this->object->isCygwin());
    }

    /**
     * Test set isatty false
     *
     * @return void
     */
    public function testSetIsattyFalse()
    {
        $this->object->setIsatty(false);
        $this->assertFalse($this->object->isatty());
    }

    /**
     * Test set isatty null
     *
     * @return void
     */
    public function testSetIsattyNull()
    {
        $this->object->setIsatty();

        // Since phpunit can be invoked differently, we want to make sure that
        // passing in null will match whatever posix_isatty says about STDOUT
        if (posix_isatty(STDOUT)) {
            $this->assertTrue($this->object->isatty());
        } else {
            $this->assertTrue($this->object->isatty());
        }
    }

    /**
     * Test set isatty null unset term
     *
     * @return void
     */
    public function testSetIsattyNullUnsetTerm()
    {
        unset($_SERVER['TERM']);

        $this->object = new Qi_Console_Terminal();

        $this->object->setIsatty();

        // Since phpunit can be invoked differently, we want to make sure that
        // passing in null will match whatever posix_isatty says about STDOUT
        if (posix_isatty(STDOUT)) {
            $this->assertTrue($this->object->isatty());
        } else {
            $this->assertFalse($this->object->isatty());
        }
    }

    /**
     * Test print term
     *
     * @return void
     */
    public function testPrintTerm()
    {
        $this->object->setIsatty(true);

        ob_start();
        $this->object->printterm('hi there');
        $result = ob_get_contents();
        ob_end_clean();

        $expected = 'hi there';

        $this->assertEquals($expected, $result);
    }

    /**
     * Test clear
     *
     * @return void
     */
    public function testClear()
    {
        $this->object->setIsatty(true);

        ob_start();
        $this->object->clear();
        $result = ob_get_contents();
        ob_end_clean();

        $expected = chr(27) . '[';

        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test locate
     *
     * @return void
     */
    public function testLocate()
    {
        ob_start();
        $this->object->locate(1, 1);
        $result = ob_get_contents();
        ob_end_clean();

        $expected = chr(27) . '[';

        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test the locate method with invalid parameters
     *
     * If row and column are not integers, it just returns the instance of the
     * terminal object.
     *
     * @return void
     */
    public function testLocateInvalidParameters()
    {
        ob_start();
        $object = $this->object->locate(new StdClass(), 'a');
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($object, $this->object);
    }

    /**
     * Test bold type
     *
     * @return void
     */
    public function testBoldType()
    {
        ob_start();
        $this->object->bold_type();
        $result = ob_get_contents();
        ob_end_clean();

        $expected = chr(27) . '[';

        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test fg color
     *
     * @return void
     */
    public function testFgColor()
    {
        ob_start();
        $this->object->set_fgcolor(1);
        $result = ob_get_contents();
        ob_end_clean();

        $expected = chr(27) . '[';

        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test bg color
     *
     * @return void
     */
    public function testBgColor()
    {
        ob_start();
        $this->object->set_bgcolor(1);
        $result = ob_get_contents();
        ob_end_clean();

        $expected = chr(27) . '[';

        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test center text
     *
     * @return void
     */
    public function testCenterText()
    {
        ob_start();
        $this->object->center_text('series of bizarre');
        $result = ob_get_contents();
        ob_end_clean();

        $string = '                               series of bizarre';
        $this->assertStringContainsString($string, $result);
    }

    /**
     * Test pretty message
     *
     * @return void
     */
    public function testPrettyMessage()
    {
        ob_start();
        $this->object->pretty_message('salmon dip');
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString(chr(27) . '[', $result);
    }

    /**
     * Test pretty message longer than size
     *
     * @return void
     */
    public function testPrettyMessageLongerThanSize()
    {
        $message = 'One thing you should realize is that things '
            . 'that are longer are usually better, but you know, life.';

        ob_start();
        $this->object->pretty_message($message, 7, 4, 80);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString(chr(27) . '[', $result);
        $this->assertStringContainsString('  better, but', $result);

        // There is a line break between `usually' and `better'
        $this->assertStringNotContainsString(' usually better', $result);
    }

    /**
     * Test pretty message with no vertical padding
     *
     * @return void
     */
    public function testPrettyMessageWithNoVerticalPadding()
    {
        $message = 'This is a test message.';

        ob_start();
        $this->object->pretty_message($message, 7, 4, null, false);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString(chr(27) . '[', $result);
        $this->assertStringContainsString('  This is', $result);
    }

    /**
     * Test make box
     *
     * @return void
     */
    public function testMakeBox()
    {
        ob_start();
        $this->object->make_box(5, 5, 10, 10);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString(chr(27) . '[', $result);
        $this->assertStringContainsString('lqqqqqqqqqqk', $result);
    }

    /**
     * Test make box with no smacs
     *
     * @return void
     */
    public function testMakeBoxWithNoSmacs()
    {
        $terminfo = new Qi_Console_Terminfo();

        $options = array(
            'terminfo' => $terminfo,
        );

        $this->object = new Qi_Console_Terminal($options);
        $this->object->setIsatty(false);

        ob_start();
        $this->object->make_box(5, 5, 10, 10);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringNotContainsString(chr(27) . '[', $result);
        if (strpos($result, '|')) {
            $this->assertEquals(
                '+----------+|          ||          ||          ||          '
                . '||          ||          ||          ||          ||          '
                . '|+----------+',
                $result
            );
        } else {
            $this->assertEquals(
                'lqqqqqqqqqqkx          xx          xx          xx          '
                . 'xx          xx          xx          xx          xx          '
                . 'xmqqqqqqqqqqj',
                $result
            );
        }
    }

    /**
     * Test magic call with echo
     *
     * @return void
     */
    public function testMagicCallWithEcho()
    {
        ob_start();
        $this->object->op();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString(chr(27) . '[', $result);
    }

    /**
     * Test magic call with no tty
     *
     * @return void
     */
    public function testMagicCallWithNoTty()
    {
        $this->object->setIsatty(false);

        $result = $this->object->do_op();

        $this->assertEquals('', $result);
    }

    /**
     * Test do capability
     *
     * @return void
     */
    public function testDoCapability()
    {
        $result = $this->object->do_capability('op');
        $this->assertStringContainsString(chr(27) . '[', $result);
    }

    /**
     * Test do capability for invalid cap
     *
     * @return void
     */
    public function testDoCapabilityForInvalidCapability()
    {
        ob_start();
        $this->object->do_capability('xxxxx');
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame("xxxxx not a cap", $result);
    }

    /**
     * Test do capability with no tty
     *
     * @return void
     */
    public function testDoCapabilityWithNotTtty()
    {
        $this->object->setIsatty(false);
        $result = $this->object->do_capability('op');

        $this->assertEquals('', $result);
    }

    /**
     * Test get capability
     *
     * @return void
     */
    public function testGetCapability()
    {
        $result = $this->object->get_capability('op');

        $this->assertStringContainsString('\\E[', $result);
    }

    /**
     * Test get capability verbose
     *
     * @return void
     */
    public function testGetCapabilityVerbose()
    {
        $result = $this->object->get_capability('op', true);

        $this->assertStringContainsString(
            "op : (orig_pair) Set default pair to its original value = '\E[",
            $result
        );
    }

    /**
     * Test dump caps
     *
     * @return void
     */
    public function testDumpCaps()
    {
        ob_start();
        $this->object->dump();
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
        $this->object->do_op();

        ob_start();
        $this->object->dumpCache();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString('op => 1B', $result);
    }
}
