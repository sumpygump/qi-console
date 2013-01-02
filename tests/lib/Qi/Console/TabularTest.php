<?php
/**
 * Qi Console Tabular test class file
 *
 * @package Qis
 */

/**
 * Test class for Qi Console Tabular
 *
 * @uses BaseTestCase
 * @package Qis
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_TabularTest extends BaseTestCase
{
    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->_createObject();
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
     * @return void
     */
    protected function _createObject()
    {
        $this->_object = new Qi_Console_Tabular();
    }

    /**
     * Construct the object
     *
     * @return void
     */
    public function testConstructorNoArguments()
    {
        $this->_object = new Qi_Console_Tabular();

        $this->assertTrue(is_object($this->_object));

        $this->assertEquals(null, $this->_object->display(true));
        $this->assertEquals(array(), $this->_object->getData());
    }

    /**
     * Test set data string
     *
     * @return void
     */
    public function testSetDataString()
    {
        $data = 'this is a table';
        $this->_object->setData($data);

        $expected = "+-------------------+\n"
            . "|  this is a table  |\n"
            . "+-------------------+\n";

        $result = $this->_object->display(true);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test constructor with data as an object
     *
     * @expectedException Qi_Console_TabularException
     * @return void
     */
    public function testSetDataObject()
    {
        $data = new StdClass();

        $data->row = array('I tried');

        $this->_object->setData($data);

        $result = $this->_object->display(true);
    }

    /**
     * Test set data int
     *
     * @return void
     */
    public function testSetDataInt()
    {
        $data = 100;

        $this->_object->setData($data);
        $result = $this->_object->display(true);

        $expected = "+-------+\n"
            . "|  100  |\n"
            . "+-------+\n";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test parse options
     *
     * @return void
     */
    public function testParseOptions()
    {
        $data = 101;

        $options = array(
            'headers'     => array('heading'),
            'cellpadding' => 2,
            'cellalign'   => 'left',
            'incorrect'   => false,
        );

        $this->_object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------+\n"
            . "|  heading  |\n"
            . "+-----------+\n"
            . "|  101      |\n"
            . "+-----------+\n";

        $result = $this->_object->display(true);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test align right
     *
     * @return void
     */
    public function testAlignRight()
    {
        $data = 101;

        $options = array(
            'headers'   => array('heading'),
            'cellalign' => 'right',
        );

        $this->_object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------+\n"
            . "|  heading  |\n"
            . "+-----------+\n"
            . "|      101  |\n"
            . "+-----------+\n";

        $result = $this->_object->display(true);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test output
     *
     * @return void
     */
    public function testOutput()
    {
        $data = 101;

        $options = array(
            'headers'   => array('heading'),
            'cellalign' => 'right',
        );

        $this->_object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------+\n"
            . "|  heading  |\n"
            . "+-----------+\n"
            . "|      101  |\n"
            . "+-----------+\n";

        ob_start();
        $this->_object->display();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($expected, $result);
    }

    /**
     * Test multiple columns
     *
     * @return void
     */
    public function testMultipleColumns()
    {
        $data = array(
            array( 'Aartha', 'aartha@example.com', '42'),
        );

        $this->_object->setData($data);
        $this->_object->setHeaders(array('name', 'email', 'age'));

        $expected = "+-----------------------------------------+\n"
            . "|  name    |  email               |  age  |\n"
            . "+-----------------------------------------+\n"
            . "|  Aartha  |  aartha@example.com  |  42   |\n"
            . "+-----------------------------------------+\n";

        $result = $this->_object->display(true);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test multiple cell align
     *
     * @return void
     */
    public function testMultipleCellAlign()
    {
        $data = array(
            array('Aartha', 'aartha@example.com', '42'),
            array('James', 'j@c.c', '28'),
        );

        $options = array(
            'headers'   => array('name', 'email', 'age'),
            'cellalign' => array('', 'right', 'R'),
        );

        $this->_object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------------------------------------+\n"
            . "|  name    |  email               |  age  |\n"
            . "+-----------------------------------------+\n"
            . "|  Aartha  |  aartha@example.com  |   42  |\n"
            . "|  James   |               j@c.c  |   28  |\n"
            . "+-----------------------------------------+\n";

        $result = $this->_object->display(true);

        $this->assertEquals($expected, $result);
    }
}