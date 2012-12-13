<?php
/**
 * Qi_Console_Tabular 
 *
 * @package Qi
 * @subpackage Console
 */

/**
 * Qi_Console_Tabular class
 *
 * An object for display tabular data in the console
 * 
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com> 
 * @version $Id$
 */
class Qi_Console_Tabular
{
    /**
     * Table data
     * 
     * @var array
     */
    protected $data = array();

    /**
     * Headers
     * 
     * @var array
     */
    protected $headers = array();

    /**
     * Columns
     * 
     * @var array
     */
    protected $cols = array();

    /**
     * Options
     * 
     * @var array
     */
    private $_options = array();

    /**
     * Constructor
     * 
     * @param array $data Table data to display
     * @param array $options Additional options to set
     *  Supported options:
     *      'headers'     - an indexed array of each header column
     *      'cellpadding' - how many spaces for the cellpadding
     *      'cellalign'   - an indexed array of the alignment option
     *                      for each column. Possible values are 'L', 'R'
     * @return void
     */
    public function __construct($data = null, $options = array())
    {
        if (null != $data) {
            $this->setData($data);
        }

        $this->_options = array(
            'cellpadding' => '2',
        );

        $this->_parseOptions($options);
    }

    /**
     * Set the data array
     *
     * @param array $data Data to be rendered as a table
     * @return object This object
     */
    public function setData($data)
    {
        if (is_object($data)) {
            throw new Qi_Console_TabularException(
                'Table data must be array of array. Object used.'
            );
        }

        if (!is_array($data)) {
            $data = array(array($data));
        }

        $this->data = $data;
        return $this;
    }

    /**
     * Get the raw data
     *
     * @return void
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Parse options 
     * 
     * @param array $options Options to parse
     * @return bool
     */
    protected function _parseOptions($options)
    {
        if (!count($options)) {
            return false;
        }

        foreach ($options as $key => $value) {
            switch($key) {
            case 'headers':
                $this->setHeaders($value);
                break;
            case 'cellpadding':
                $this->_options['cellpadding'] = $value;
                break;
            case 'cellalign':
                $this->_options['cellalign'] = $value;
                break;
            default:
                break;
            }
        }

        return true;
    }

    /**
     * Set headers
     *
     * @param array $value Headers array
     * @return object This object
     */
    public function setHeaders($value)
    {
        $this->headers = $value;
        return $this;
    }

    /**
     * Render and display the table
     * 
     * @param bool $buffer Whether to buffer and return output
     * @return mixed
     */
    public function display($buffer = false)
    {
        if (!$this->data) {
            return;
        }

        $out = '';

        $this->_determineColumnWidths();

        $padding = str_repeat(" ", $this->_options['cellpadding']);

        $headerContent = '';
        $rowsepString  = '';

        if (count($this->headers)) {
            $headerContent .= "|";
            for ($i = 0; $i < count($this->headers); $i++) {
                $string = $padding
                    . str_pad($this->headers[$i], $this->cols[$i])
                    . $padding
                    . "|";

                $headerContent .= $string;
            }

            $rowsepString = "+"
                . str_repeat("-", strlen($headerContent) - 2) . "+";

            $out .= $rowsepString . "\n" . $headerContent
                . "\n" . $rowsepString . "\n";
        }

        $tableContent = '';
        foreach ($this->data as $row) {
            $content = '|';
            $i       = 0;

            foreach ($row as $col) {
                $padType = $this->getPadTypeForCol($i);
                $string  = $padding
                    . str_pad(trim($col), $this->cols[$i], ' ', $padType)
                    . $padding
                    . "|";

                $content .= $string;
                $i++;
            }

            $tableContent .= $content . "\n";

            if (!$rowsepString) {
                $rowsepString = "+" . str_repeat("-", strlen($content)-2) . "+";
            }
        }

        // If there were no headers, add the top line
        if (!count($this->headers)) {
            $tableContent = $rowsepString . "\n" . $tableContent;
        }

        $out .= $tableContent . $rowsepString . "\n";

        if ($buffer) {
            return $out;
        } else {
            echo $out;
        }
    }

    /**
     * _determineColumnWidths 
     * 
     * @return void
     */
    protected function _determineColumnWidths()
    {
        $headerCount = count($this->headers);
        if ($headerCount) {
            for ($i = 0; $i < $headerCount; $i++) {
                $this->_setColumnWidth($i, strlen(trim($this->headers[$i])));
            }
        }

        $dataCount = count($this->data);

        for ($r = 0; $r < $dataCount; $r++) {
            $columnIndex = 0;
            foreach ($this->data[$r] as $column) {
                $this->_setColumnWidth(
                    $columnIndex, strlen(trim($column))
                );
                $columnIndex++;
            }
        }
    }

    /**
     * Set a column width
     * 
     * @param int $col Column id
     * @param int $width Width
     * @return void
     */
    protected function _setColumnWidth($col, $width)
    {
        $cols = $this->cols;
        if (isset($cols[$col])) {
            if ($width > $cols[$col]) {
                $cols[$col] = $width;
            }
        } else {
            $cols[$col] = $width;
        }

        $this->cols = $cols;
    }

    /**
     * Get the pad type for a column
     * 
     * @param int $index Index of column
     * @return int Constant for strpad() function
     */
    public function getPadTypeForCol($index)
    {
        if (!isset($this->_options['cellalign'])) {
            return STR_PAD_RIGHT;
        }

        // If there is a alignment set for this column, use it
        // otherwise attempt to get the string (all are aligned the same)
        if (is_array($this->_options['cellalign'])
            && isset($this->_options['cellalign'][$index])
        ) {
            return $this->_getPadType($this->_options['cellalign'][$index]);
        } else {
            return $this->_getPadType($this->_options['cellalign']);
        }
    }

    /**
     * Get the correct pad type constants based on a type string
     *
     * @param string $type Pad type (R, L)
     * @return int
     */
    protected function _getPadType($type)
    {
        $type = strtoupper($type);

        switch ($type) {
        case 'R':
        case 'RIGHT':
            $padType = STR_PAD_LEFT;
            break;
        case 'L': //pass through
        case 'LEFT': //pass through
        default:
            $padType = STR_PAD_RIGHT;
            break;
        }

        return $padType;
    }
}

/**
 * Qi_Console_TabularException
 *
 * @uses Exception
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_TabularException extends Exception
{
}
