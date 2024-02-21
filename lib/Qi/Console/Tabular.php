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
    private $options = array();

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
     *      'border'      - whether or not to display a border
     *      'margin'      - margin on the left
     * @return void
     */
    public function __construct($data = null, $options = array())
    {
        if (null != $data) {
            $this->setData($data);
        }

        $this->options = array(
            'cellpadding' => '2',
            'border' => true,
            'margin' => 0,
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
            switch ($key) {
                case 'headers':
                    $this->setHeaders($value);
                    break;
                case 'cellpadding':
                    $this->options['cellpadding'] = $value;
                    break;
                case 'cellalign':
                    $this->options['cellalign'] = $value;
                    break;
                case 'border':
                    $this->options['border'] = $value;
                    break;
                case 'margin':
                    $this->options['margin'] = $value;
                    break;
                case 'escapes':
                    $this->options['escapes'] = $value;
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

        $padding = str_repeat(" ", $this->options['cellpadding']);
        $border = $this->options['border'];
        $margin = str_repeat(" ", $this->options['margin']);

        $headerContent = '';
        $rowsepString  = '';

        if (count($this->headers)) {
            $headerContent .= $margin . ($border ? "|" : "");
            for ($i = 0; $i < count($this->headers); $i++) {
                $string = ($border || $i > 0 ? $padding : "")
                    . $this->eb_str_pad(trim($this->headers[$i]), $this->cols[$i])
                    . ($border ? $padding . "|" : "");

                $headerContent .= $string;
            }

            $calc = mb_strlen($headerContent) - mb_strlen($margin) - 2;

            $rowsepString = $border ?
                $margin . "+" . str_repeat("-", $calc) . "+\n" :
                "";

            $out .= $rowsepString . $headerContent
                . "\n" . $rowsepString;
        }

        $tableContent = '';
        foreach ($this->data as $row) {
            $content = $margin . ($border ? '|' : '');
            $i       = 0;

            foreach ($row as $col) {
                if (null === $col) {
                    $col = '';
                }
                $padType = $this->getPadTypeForCol($i);
                $string  = ($border || $i > 0 ? $padding : "")
                    . $this->eb_str_pad(trim($col), $this->cols[$i], ' ', $padType)
                    . ($border ? $padding . "|" : "");

                $content .= $string;
                $i++;
            }

            $tableContent .= $content . "\n";

            if (!$rowsepString) {
                $rowsepString = $border ?
                    $margin . "+" . str_repeat("-", mb_strlen($content) - mb_strlen($margin) - 2) . "+\n" :
                    "";
            }
        }

        // If there were no headers, add the top line
        if (!count($this->headers)) {
            $tableContent = $rowsepString . $tableContent;
        }

        $out .= $tableContent . $rowsepString;

        if ($buffer) {
            return $out;
        } else {
            echo $out;
        }
    }

    protected function eb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        $diff = mb_strlen($input) - mb_strlen($this->_replaceEscapes($input));
        return self::mb_str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
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
                $text = $this->_replaceEscapes(trim($this->headers[$i]));
                $this->_setColumnWidth($i, mb_strlen($text));
            }
        }

        $dataCount = count($this->data);

        for ($r = 0; $r < $dataCount; $r++) {
            $columnIndex = 0;
            foreach ($this->data[$r] as $column) {
                if (null === $column) {
                    $column = '';
                }

                $text = $this->_replaceEscapes(trim($column));
                $this->_setColumnWidth(
                    $columnIndex,
                    mb_strlen($text)
                );
                $columnIndex++;
            }
        }
    }

    /**
     * Replace escape chars in order to determine actual width
     *
     * @param string $text
     * @return string
     */
    protected function _replaceEscapes($text)
    {
        if (!isset($this->options['escapes'])) {
            return $text;
        }

        return str_replace($this->options['escapes'], '', $text);
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
        if (!isset($this->options['cellalign'])) {
            return STR_PAD_RIGHT;
        }

        // If there is a alignment set for this column, use it
        // otherwise attempt to get the string (all are aligned the same)
        if (
            is_array($this->options['cellalign'])
            && isset($this->options['cellalign'][$index])
        ) {
            return $this->_getPadType($this->options['cellalign'][$index]);
        } else {
            return $this->_getPadType($this->options['cellalign']);
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

    /**
     * Pad a string to a certain length with another string (multibyte-string version).
     *
     * @param string $input The input string
     * @param int $pad_length Desired target length of the output string
     * @param string $pad_string The characters to append/prepend in order to reach $pad_length
     * @param int $pad_type Where to pad the string: left, right or both sides.
     *                      Allowed values: `STR_PAD_LEFT`, `STR_PAD_RIGHT`, `STR_PAD_BOTH`.
     *                      Default: `STR_PAD_RIGHT`
     *
     * @return string Input string padded to desired length
     *
     * @see https://secure.php.net/manual/en/function.str-pad.php
     */
    public static function mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        if (!in_array($pad_type, [STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH])) {
            throw new InvalidArgumentException('Invalid value for argument $pad_type');
        }

        // Total number of characters we need to fill
        $gap = $pad_length - mb_strlen($input);

        // Bail early if the input is already at or above the target length
        if ($gap < 1) {
            return $input;
        }

        // Determine the number of characters we need to prepend on the left
        if ($pad_type === STR_PAD_BOTH) {
            $left_gap = (int) $gap / 2;
        } elseif ($pad_type === STR_PAD_LEFT) {
            $left_gap = $gap;
        } else {
            $left_gap = 0;
        }

        // Build the padding string left of the input
        $pad_string_length = mb_strlen($pad_string);
        $left_padding = mb_substr(str_repeat($pad_string, ceil($left_gap / $pad_string_length)), 0, $left_gap);

        // Build the padding string right of the input
        $right_gap = $gap - mb_strlen($left_padding);
        $right_padding = mb_substr(str_repeat($pad_string, ceil($right_gap / $pad_string_length)), 0, $right_gap);

        return $left_padding . $input . $right_padding;
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
