<?php
/**
 * Base Test Case class file
 *
 * @package Apricot
 */

/**
 * @see PHPUnit/Framework/TestCase.php
 */
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * Base Test Case
 * 
 * @uses PHPUnit_Framework_TestCase
 * @package Apricot
 * @subpackage Tests
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class BaseTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Storage of object being tested
     *
     * @var object
     */
    protected $_object;
}
