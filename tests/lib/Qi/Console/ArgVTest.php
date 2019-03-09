<?php
/**
 * Qi_Console_ArgV Test class file
 *
 * @package Qis
 */

use PHPUnit\Framework\TestCase;

/**
 * Qi_Console_ArgV Test class
 *
 * @package Qis
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_ArgVTest extends TestCase
{
    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->_createObject('');
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
     * Create object
     *
     * @param array $args Arguments
     * @param array $rules Rules
     * @return void
     */
    protected function _createObject($args, $rules = null)
    {
        if (null === $rules) {
            $this->_object = new Qi_Console_ArgV($args);
        } else {
            $this->_object = new Qi_Console_ArgV($args, $rules);
        }
    }

    /**
     * Arguments are required
     *
     * @return void
     */
    public function testConstructorNoArguments()
    {
        $this->expectException(\ArgumentCountError::class);
        $this->expectExceptionMessage("Too few arguments");
        $this->_object = new Qi_Console_ArgV();
    }

    /**
     * Test constructor with empty argv
     *
     * @return void
     */
    public function testConstructorEmptyArgv()
    {
        $this->_createObject(array());
        $this->assertFalse($this->_object->hasData());
    }

    /**
     * Test construction with string
     *
     * @return void
     */
    public function testConstructorString()
    {
        $args     = 'foo';
        $expected = array(
            '__arg1' => 'foo',
        );

        $this->_createObject($args);
        $this->assertTrue($this->_object->hasData());
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a null argument
     *
     * @return void
     */
    public function testParseNullArgument()
    {
        $this->_object->parse();
        $this->assertFalse($this->_object->hasData());
    }

    /**
     * Test parsing an associated array
     *
     * @return void
     */
    public function testParseAssociatedArray()
    {
        $args = array(
            'foo' => 'bar',
            'baz' => 'quux',
        );

        $expected = array(
            '__arg1' => 'bar',
            '__arg2' => 'quux',
        );

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a single short option
     *
     * @return void
     */
    public function testParseSingleShortOption()
    {
        $args = array(
            '-f',
        );

        $expected = array(
            'f' => true,
        );

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parseing a single short option group
     *
     * @return void
     */
    public function testParseSingleShortOptionGroup()
    {
        $args = array(
            '-fv',
        );

        $expected = array(
            'f' => true,
            'v' => true,
        );

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a single long opton
     *
     * @return void
     */
    public function testParseSingleLongOption()
    {
        $args = array(
            '--foobar',
        );

        $expected = array(
            'foobar' => true,
        );

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a single short parameter without rules
     *
     * @return void
     */
    public function testParseSingleShortParameterWithoutRules()
    {
        $args = array(
            '-f',
            'myfile',
        );

        $expected = array(
            'f' => true,
            '__arg1' => 'myfile',
        );

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a single short parameter with rule
     *
     * @return void
     */
    public function testParseSingleShortParameterWithRule()
    {
        $rules = array(
            'f:' => 'The filename',
        );

        $args = array(
            'command',
            '-f',
            'myfile',
        );

        // Note: this case has backwards compatibility
        // with the old way of defining rules
        $expected = array(
            'f' => 'myfile',
            'The filename' => 'myfile',
        );

        $this->_createObject($args, $rules);

        //$this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a short parameter missing
     *
     * @expectedException Qi_Console_ArgVException
     * @return void
     */
    public function testParseShortParameterMissing()
    {
        $rules = array(
            'p:' => 'password',
        );

        $args = array(
            '-p',
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
    }

    /**
     * Detect a shunted short parameter
     *
     * @return void
     */
    public function testParseShortParameterShunt()
    {
        $rules = array(
            'p:' => 'password',
        );

        $args = array(
            '-psecret',
        );

        $expected = array(
            'p' => 'secret',
            'password' => 'secret', // because of backwards compat
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a short parameter shunt with a prior short parameter
     *
     * @return void
     */
    public function testParseShortParameterShuntWithPriorShortParameter()
    {
        $rules = array(
            'f' => 'Flag',
            'p:' => 'password',
        );

        $args = array(
            '-fpsecret',
        );

        $expected = array(
            'f' => true,
            'Flag' => true,
            'p' => 'secret',
            'password' => 'secret', // because of backwards compat
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test a long parameter
     *
     * @return void
     */
    public function testLongParameter()
    {
        $rules = array(
            'cctype:' => 'Card Type',
        );

        $args = array(
            '--cctype',
            'visa',
        );

        $expected = array(
            'cctype' => 'visa',
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a long parameter shunt
     *
     * @return void
     */
    public function testLongParameterShunt()
    {
        $rules = array(
            'cctype:' => 'Card Type',
        );

        $args = array(
            '--cctype=visa',
        );

        $expected = array(
            'cctype' => 'visa',
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test long parameter shunt when mixed with other params
     *
     * @return void
     */
    public function testLongParameterShuntWithOthers()
    {
        $rules = array(
            'arg:context' => 'Context',
            'arg:action'  => 'Action',
            'verbose|v'   => 'Verbose messaging',
            'config:'     => 'Configuration file to read',
        );

        $args = array(
            'command',
            'zc11',
            '--config=/some/path/config.ini',
            '-v',
            'ac676767',
        );

        $expected = array(
            'config'  => '/some/path/config.ini',
            '__arg1'  => 'zc11',
            'context' => 'zc11',
            '__arg2'  => 'ac676767',
            'action'  => 'ac676767',
            'v'       => true,
            'verbose' => true,
        );

        $this->_createObject($args, $rules);
        $this->assertEquals($expected, $this->_object->toArray());

        $command = array_shift($args);
        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test a situation with a long parameter specified without a rule
     *
     * @return void
     */
    public function testLongParameterShuntWithoutDefinedRuleBeforeArg()
    {
        $rules = array(
            'arg:action' => 'Argument',
            'limit|l'    => 'Limit',
        );

        $args = array(
            '--something',
            '--whack=1',
            'mycommand',
        );

        $expected = array(
            'something' => true,
            'whack'     => 1,
            'action'    => 'mycommand',
            '__arg1'    => 'mycommand',
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * This ensures that all the content after the first
     * equals sign is the value
     *
     * @return void
     */
    public function testLongParameterShuntValueContainingEqualsSign()
    {
        $rules = array(
            'cctype:' => 'Card Type',
        );

        $args = array(
            '--cctype=card=visa',
        );

        $expected = array(
            'cctype' => 'card=visa',
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * If a value is required
     *
     * @expectedException Qi_Console_ArgVException
     * @return void
     */
    public function testParseLongParameterMissingValue()
    {
        $rules = array(
            'cctype:' => 'Card Type',
        );

        $args = array(
            '--cctype',
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
    }

    /**
     * Test parsing a long and short option
     *
     * @return void
     */
    public function testParseLongAndShortOption()
    {
        $rules = array(
            'quiet|q' => 'Quiet mode',
        );

        $args = array(
            '-q',
        );

        $expected = array(
            'q' => true,
            'quiet' => true,
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a standalone argument
     *
     * @return void
     */
    public function testParseStandaloneArgument()
    {
        $rules = array(
            'arg:action' => 'Action to execute',
        );

        $args = array(
            'index',
        );

        $expected = array(
            'action' => 'index',
            '__arg1' => 'index',
        );

        $this->_createObject($args, $rules);

        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * If a numeric array is provided as rules, there will be no help values
     *
     * @return void
     */
    public function testParseRulesNumericArray()
    {
        $rules = array(
            'm',
        );

        $args = array(
            '-m',
        );

        $expected = array(
            'm' => true,
        );

        $this->_createObject($args, $rules);
        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing when a rules option is not considered numeric
     *
     * @return void
     */
    public function testParseRulesOptionIsNotConsideredNumeric()
    {
        $rules = array(
            'm1' => 'Mode1',
        );

        $args = array(
            '--m1',
        );

        $expected = array(
            'm1' => true,
        );

        $this->_createObject($args, $rules);
        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing a long and short when short name too long
     *
     * @return void
     */
    public function testParseRulesLongAndShortWhenShortNameIsLongerThanOneChar()
    {
        $rules = array(
            'flag|flag' => 'A flag',
        );

        $args = array(
            '-f',
        );

        $expected = array(
            'flag' => true,
            'f' => true,
        );

        $this->_createObject($args, $rules);
        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test parsing rules when arg definition is incomplete
     *
     * @return void
     */
    public function testParseRulesWhenArgDefinitionIsIncomplete()
    {
        $rules = array(
            'arg:' => 'Missing definition',
        );

        $args = array(
            'random',
        );

        $expected = array(
            '__arg1' => 'random',
        );

        $this->_createObject($args, $rules);
        $this->_object->parse($args);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Test get rule for options
     *
     * @return void
     */
    public function testGetRuleForOptions()
    {
        $rules = array(
            'm' => 'mode',
            'method|e' => 'method',
        );

        $args = array();

        $this->_createObject($args, $rules);
        $this->assertEquals(
            array('type' => 'option'),
            $this->_object->getRule('m')
        );

        $this->assertEquals(
            array('type' => 'option'),
            $this->_object->getRule('mode') // backwards compat
        );

        $this->assertEquals(
            array('type' => 'option'),
            $this->_object->getRule('e')
        );

        $this->assertEquals(
            array('type' => 'option'),
            $this->_object->getRule('method')
        );
    }

    /**
     * Test get rule for parameters
     *
     * @return void
     */
    public function testGetRuleForParameters()
    {
        $rules = array(
            'm:' => 'mode',
            'method|e:' => 'method',
        );

        $args = array();

        $this->_createObject($args, $rules);
        $this->assertEquals(
            array('type' => 'parameter'),
            $this->_object->getRule('m')
        );

        $this->assertEquals(
            array('type' => 'parameter'),
            $this->_object->getRule('mode') // backwards compat
        );

        $this->assertEquals(
            array('type' => 'parameter'),
            $this->_object->getRule('e')
        );

        $this->assertEquals(
            array('type' => 'parameter'),
            $this->_object->getRule('method')
        );
    }

    /**
     * Test add help array
     *
     * @return void
     */
    public function testAddHelpArray()
    {
        $result = $this->_object->addHelp(array(), 'text');
        $this->assertFalse($result);
    }

    /**
     * Test get help
     *
     * @return void
     */
    public function testGetHelp()
    {
        $rules = array(
            'verbose|v' => 'Use Verbose messaging',
            'list|l'    => 'List results',
        );

        $expected = array(
            'v|verbose' => 'Use Verbose messaging',
            'l|list'    => 'List results',
        );

        $this->_createObject('', $rules);
        $this->assertEquals($expected, $this->_object->getHelp());
    }

    /**
     * Test multiple options and arguments
     *
     * @return void
     */
    public function testMultipleOptionsAndArguments()
    {
        $rules = array(
            'verbose|v' => 'verbose messaging',
            'init|i:' => 'Initialization string',
            'l' => 'list',
            'a' => 'all',
            'arg:filename' => 'Filename',
        );

        $args = array(
            'command',
            '--verbose',
            '--init',
            '21008:43',
            '-la',
            'somefile',
        );

        $expected = array(
            'verbose' => true,
            'v' => true,
            'init' => '21008:43',
            'i' => '21008:43',
            'l' => true,
            'list' => true,
            'a' => true,
            'all' => true,
            '__arg1' => 'somefile',
            'filename' => 'somefile',
        );

        $this->_createObject($args, $rules);
        $this->assertEquals($expected, $this->_object->toArray());
    }

    /**
     * Missing value for argument --init
     *
     * @expectedException Qi_Console_ArgVException
     * @return void
     */
    public function testParseWithMultitpleArgumentsRequiredMissing()
    {
        $rules = array(
            'verbose|v' => 'verbose messaging',
            'init|i:' => 'Initialization string',
            'l' => 'list',
            'a' => 'all',
            'arg:filename' => 'Filename',
        );

        $args = array(
            'command',
            '--verbose',
            '--init',
            '-la',
            'somefile',
        );

        $this->_createObject($args, $rules);
    }

    /**
     * Test get Value
     *
     * @return void
     */
    public function testGetValue()
    {
        $args = array(
            'command',
            '--foobar',
        );

        $this->_createObject($args);

        $this->assertTrue($this->_object->foobar);
    }

    /**
     * Test getting a value that isn't set
     *
     * @return void
     */
    public function testGetValueNotSet()
    {
        $args = array(
            'foo',
            'bar',
        );

        $this->_createObject($args);

        $this->assertNull($this->_object->baz);
    }

    /**
     * Test get args
     *
     * @return void
     */
    public function testGetArgs()
    {
        $args = array(
            'foo',
            'bar',
            '--crannnnh',
            'baz',
            '-oj',
            'mudo',
        );

        $expected = array(
            'bar',
            'baz',
            'mudo',
        );

        $this->_createObject($args);
        $this->assertEquals($expected, $this->_object->getArgs());
    }
}
