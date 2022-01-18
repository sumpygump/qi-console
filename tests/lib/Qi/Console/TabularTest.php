<?php

/**
 * Qi Console Tabular test class file
 *
 * @package Qi
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for Qi Console Tabular
 *
 * @package Qi
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class TabularTest extends TestCase
{
    public $object;

    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->createObject();
    }

    /**
     * Create object
     *
     * @return void
     */
    protected function createObject()
    {
        $this->object = new Qi_Console_Tabular();
    }

    /**
     * Construct the object
     *
     * @return void
     */
    public function testConstructorNoArguments()
    {
        $this->object = new Qi_Console_Tabular();

        $this->assertTrue(is_object($this->object));

        $this->assertEquals(null, $this->object->display(true));
        $this->assertEquals(array(), $this->object->getData());
    }

    /**
     * Test set data string
     *
     * @return void
     */
    public function testSetDataString()
    {
        $data = 'this is a table';
        $this->object->setData($data);

        $expected = "+-------------------+\n"
            . "|  this is a table  |\n"
            . "+-------------------+\n";

        $result = $this->object->display(true);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test constructor with data as an object
     *
     * @return void
     */
    public function testSetDataObject()
    {
        $this->expectException(\Qi_Console_TabularException::class);
        $data = new StdClass();

        $data->row = array('I tried');

        $this->object->setData($data);

        $result = $this->object->display(true);
    }

    /**
     * Test set data int
     *
     * @return void
     */
    public function testSetDataInt()
    {
        $data = 100;

        $this->object->setData($data);
        $result = $this->object->display(true);

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

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------+\n"
            . "|  heading  |\n"
            . "+-----------+\n"
            . "|  101      |\n"
            . "+-----------+\n";

        $result = $this->object->display(true);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test option border
     *
     * @return void
     */
    public function testNoBorder()
    {
        $data = 101;

        $options = array(
            'border'   => false,
        );

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "101\n";

        $result = $this->object->display(true);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test option margin
     *
     * @return void
     */
    public function testMargin()
    {
        $data = 101;

        $options = array(
            'headers'  => array('heading'),
            'margin'   => 5,
        );

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "     +-----------+\n"
            . "     |  heading  |\n"
            . "     +-----------+\n"
            . "     |  101      |\n"
            . "     +-----------+\n";

        $result = $this->object->display(true);

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

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------+\n"
            . "|  heading  |\n"
            . "+-----------+\n"
            . "|      101  |\n"
            . "+-----------+\n";

        $result = $this->object->display(true);

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

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------+\n"
            . "|  heading  |\n"
            . "+-----------+\n"
            . "|      101  |\n"
            . "+-----------+\n";

        ob_start();
        $this->object->display();
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

        $this->object->setData($data);
        $this->object->setHeaders(array('name', 'email', 'age'));

        $expected = "+-----------------------------------------+\n"
            . "|  name    |  email               |  age  |\n"
            . "+-----------------------------------------+\n"
            . "|  Aartha  |  aartha@example.com  |  42   |\n"
            . "+-----------------------------------------+\n";

        $result = $this->object->display(true);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test option border without columns
     *
     * @return void
     */
    public function testMultipleColumnsNoBorder()
    {
        $data = array(
            array( 'Aartha', 'aartha@example.com', '42'),
            array( 'Bea', 'bea@example.com', '68'),
        );

        $options = array(
            'headers'  => array('name', 'email', 'age'),
            'border'   => false,
        );

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "name    email               age\n"
            . "Aartha  aartha@example.com  42 \n"
            . "Bea     bea@example.com     68 \n";

        $result = $this->object->display(true);
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

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "+-----------------------------------------+\n"
            . "|  name    |  email               |  age  |\n"
            . "+-----------------------------------------+\n"
            . "|  Aartha  |  aartha@example.com  |   42  |\n"
            . "|  James   |               j@c.c  |   28  |\n"
            . "+-----------------------------------------+\n";

        $result = $this->object->display(true);

        $this->assertEquals($expected, $result);
    }

    public function testWithNull()
    {
        $data = [
            [null, 'a', 'b'],
        ];

        $options = [
            'headers' => ['name', 'email', 'age'],
        ];

        $this->object = new Qi_Console_Tabular($data, $options);

        $expected = "+--------------------------+\n"
            . "|  name  |  email  |  age  |\n"
            . "+--------------------------+\n"
            . "|        |  a      |  b    |\n"
            . "+--------------------------+\n";

        $result = $this->object->display(true);

        $this->assertEquals($expected, $result);
    }
}
