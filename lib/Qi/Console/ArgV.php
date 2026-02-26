<?php

/**
 * ArgV class file
 *
 * @package Qi
 * @subpackage Console
 */

/**
 * ArgV provides a way to assign and gather command line arguments
 *
 * It will parse and assign option flags and arguments passed
 * in as command line arguments
 *
 * Examples of script arguments it can potentially parse:
 * - short option (-f) : can be grouped (-fvz)
 * - long option (--flag)
 * - short parameter (-p value)
 * - short parameter shunt (-pvalue)
 * - long parameter (--param value)
 * - long parameter shunt (--param=value)
 * - standalone argument (filename)
 *
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @version 1.3.4
 */
class Qi_Console_ArgV
{
    /**#@+
     * Argument types
     *
     * @var string
     */
    public const TYPE_OPTION    = 'option';
    public const TYPE_PARAMETER = 'parameter';
    /**#@-*/

    /**
     * Store the argument data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Storage for the argument rules (params starting with '-' or '--')
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Map to link short and long argument names (e.g., "-p" and "--param")
     *
     * @var array
     */
    protected $ruleMap = [];

    /**
     * Storage for the standalone arguments
     *
     * @var array
     */
    protected $argumentStack = [];

    /**
     * Keep track of the current index of the argumentStack when setting them
     *
     * @var int
     */
    private $argumentIndex = 1;

    /**
     * Help messages for each argument
     *
     * @var array
     */
    protected $help = [];

    /**
     * Raw Arguments
     *
     * @var array
     */
    protected $rawArguments = [];

    /**
     * Rules for parsing parameters
     *
     * @var array
     */
    protected $rawRules = [];

    /**
     * Flag to indicate whether parsing has been done
     *
     * @var bool
     */
    protected $hasParsed = false;

    /**
     * Create a new instance of an object of this class.
     *
     * $rules is an array that will specify the short or long names
     * of the option flags, parameters, help text
     * and the names of the arguments.
     *
     * e.g., [
     *     'help|h'   => 'Show help',
     *     'delete|d' => 'Enter delete mode',
     *     'f'        => 'Flag with no long param name',
     *     'long'     => 'Flag with no short param name',
     *     'name|n:'  => 'Name to use', // colon means parameter required
     *     'arg:file' => 'Filename to use'
     * ];
     *
     * So, after constructing the object,
     * the following will be available:
     *     $obj->help and $obj->h
     *     $obj->delete and $obj->d
     *     $obj->f
     *     $obj->long
     *     $obj->name
     *     $obj->file
     *
     * @param array $argv A key=>value array of arguments from the command line
     * @param array $rules A list of option flags and param names for arguments
     * @return void
     */
    public function __construct($argv, $rules = [])
    {
        $this->rawRules = $rules;
        $this->parseRules($rules);

        if (is_string($argv)) {
            $argv = self::parseArgumentString($argv);
        } else {
            // assume the script name was the first param if
            // a string was not given as input
            $scriptName = array_shift($argv);
        }

        if (count($argv) <= 0) {
            return;
        }

        $this->rawArguments = $argv;

        $this->parse($argv);
    }

    /**
     * Parse arguments
     *
     * @param array $argv Arguments
     * @return void
     */
    public function parse($argv = null)
    {
        if (null === $argv) {
            $argv = $this->rawArguments;
        }

        // If already parsed, then re-parse the rules
        // in order to reset the argument stack
        if ($this->hasParsed === true) {
            $this->argumentIndex = 1;

            $this->data = []; // reset data
            $this->parseRules($this->rawRules);
        }

        $this->hasParsed = true;

        // enforce numeric array
        $argv = array_values($argv);

        for ($i = 0; $i < count($argv); $i++) {
            $val      = $argv[$i];
            $nextVal  = null;
            $shuntVal = '';

            if (substr($val, 0, 2) == "--") {
                $option = substr($val, 2);
                $pos    = false;
                if (strpos($option, '=') !== false) {
                    // Detect long parameter shunt
                    $pos      = strpos($option, '=');
                    $shuntVal = substr($option, $pos + 1);
                    $option   = substr($option, 0, $pos);
                }
                $rule = $this->getRule($option);

                if ($rule && ($rule['type'] == self::TYPE_PARAMETER || $pos !== false)) {
                    if ($shuntVal != '') {
                        $nextVal = $shuntVal;
                    } else {
                        $nextVal = $this->getProperNextVal($argv, $i);
                    }
                    if (null === $nextVal) {
                        // If we didn't find anything, go back to the shuntval
                        if ($shuntVal != '') {
                            $nextVal = $shuntVal;
                        } else {
                            throw new Qi_Console_ArgVException(
                                "Missing required parameter for arg $option"
                            );
                        }
                    }
                } else {
                    $nextVal = true;
                }

                $this->setSingleOption($option, $nextVal);
            } elseif (substr($val, 0, 1) == "-") {
                $optionString = substr($val, 1);

                $optionStringLength = strlen($optionString);

                for ($s = 0; $s < $optionStringLength; $s++) {
                    $option = $optionString[$s];
                    $rule   = $this->getRule($option);

                    if ($rule && $rule['type'] == self::TYPE_PARAMETER) {
                        $nextVal = $this->getProperNextVal($argv, $i);
                        if (null === $nextVal) {
                            // Detect short parameter shunt
                            if (substr($optionString, $s + 1) != '') {
                                $nextVal = substr($optionString, $s + 1);
                                $this->setSingleOption($option, $nextVal);
                                break;
                            }
                            throw new Qi_Console_ArgVException(
                                "Missing required parameter for arg $option"
                            );
                        }
                    } else {
                        $nextVal = true;
                    }

                    $this->setSingleOption($option, $nextVal);
                }
            } else {
                $this->setArgument(array_shift($this->argumentStack), $val);
            }
        }
    }

    /**
     * Return whether this object has data
     *
     * @return bool
     */
    public function hasData()
    {
        return (!empty($this->data));
    }

    /**
     * Retrieve the data as an array
     *
     * @return array
     */
    public function toArray()
    {
        return (array) ($this->data);
    }

    /**
     * Parse rules for configuring this object
     *
     * @param array $rules The rules to parse
     * @return void
     */
    protected function parseRules($rules)
    {
        $this->argumentStack = [];

        foreach ($rules as $name => $value) {
            if (substr($name, 0, 4) == 'arg:') {
                // "arg:<something>" is a way to name standalone arguments
                $name = substr($name, 4);

                $this->argumentStack[] = $name;
                $this->addHelp('<' . $name . '>', $value);
            } else {
                if (is_numeric($name)) {
                    $name  = $value;
                    $value = '';
                } else {
                    $this->addRule($name, $value);
                }
            }
        }
    }

    /**
     * Add a rule
     *
     * @param string $name Name of rule
     * @param string $helpText Help text for this rule
     * @return void
     */
    public function addRule($name, $helpText = null)
    {
        if (substr($name, -1) == ':') {
            // If the name ends in a colon, it requires a parameter
            $type = 'parameter';
            $name = substr($name, 0, -1);
        } else {
            $type = 'option';
        }

        if (strpos($name, '|') !== false) {
            // If the name has a pipe, the first value is the long option
            // name and the second is the short option name
            $parts = explode('|', $name);
            $name  = $parts[0];

            $shortName = substr($parts[1], 0, 1); // force to one char

            $this->addArgRule($shortName, $type);
            $this->addArgRule($name, $type);
            $this->mapRules($shortName, $name);
            $helpName = $shortName . '|' . $name;
        } else {
            $this->addArgRule($name, $type);
            $helpName = $name;
            if (strlen($name) == 1) {
                // provide backwards compatibility
                $longName = $helpText;
                $helpText = '';
                $this->addArgRule($longName, $type);
                $this->mapRules($name, $longName);
                $helpName = $name . '|' . $longName;
            }
        }

        $this->addHelp($helpName, $helpText, $type);
    }

    /**
     * Add a rule (low level)
     *
     * @param string $name Name of argument
     * @param string $type Argument type
     * @return void
     */
    protected function addArgRule($name, $type)
    {
        $this->rules[$name] = [
            'type' => $type,
        ];
    }

    /**
     * Add a rule map between two argument names
     *
     * @param string $name Name
     * @param string $alias Alias
     * @return void
     */
    protected function mapRules($name, $alias)
    {
        $this->ruleMap[$name]  = $alias;
        $this->ruleMap[$alias] = $name;
    }

    /**
     * Get a rule by it's name
     *
     * @param string $name Rule name
     * @return mixed
     */
    public function getRule($name)
    {
        if (!isset($this->rules[$name])) {
            return false;
        }

        return $this->rules[$name];
    }

    /**
     * Add a help message for an argument
     *
     * @param string $name Argument name
     * @param string $helpText Help message
     * @param string $type Type
     * @return mixed
     */
    public function addHelp($name, $helpText, $type = 'option')
    {
        if (!is_string($name)) {
            return false;
        }

        if ($type == 'parameter') {
            $name .= ":";
        }

        $this->help[$name] = $helpText;
    }

    /**
     * Get help messages
     *
     * @return array
     */
    public function getHelp()
    {
        return $this->help;
    }

    /**
     * Set a single option (one that starts with --)
     *
     * @param string $name Option name
     * @param string $value Option value
     * @return void
     */
    protected function setSingleOption($name, $value = true)
    {
        $this->data[$name] = $value;

        if (isset($this->ruleMap[$name])) {
            $this->data[$this->ruleMap[$name]] = $value;
        }
    }

    /**
     * Set an argument
     *
     * @param mixed $argument An argument name
     * @param mixed $value A value
     * @return void
     */
    protected function setArgument($argument, $value)
    {
        // This is in case the name of the argument
        // was not set during construction
        $this->data['__arg' . $this->argumentIndex++] = $value;

        if ($argument) {
            $this->data[$argument] = $value;
        }
    }

    /**
     * Get a proper next val from the arg list
     *
     * Used to fetch a parameter for arguments that require one
     *
     * @param array $args List of arguments
     * @param int &$i Current index
     * @return mixed
     */
    protected function getProperNextVal($args, &$i)
    {
        if (!isset($args[$i + 1])) {
            return null;
        }

        $value = $args[$i + 1];

        if (substr($value, 0, 1) == '-') {
            return null;
        }

        $i++; // increment counter so next argument doesn't count as standalone
        return $value;
    }

    /**
     * Getter
     *
     * @param mixed $option The name of the option to get
     * @return mixed The data for the request option or null
     */
    public function __get($option)
    {
        return $this->get($option);
    }

    /**
     * Get an argument by name
     *
     * @param mixed $option The name of the option to get
     * @return mixed The value
     */
    public function get($option)
    {
        if (isset($this->data[$option])) {
            return $this->data[$option];
        }
        return null;
    }

    /**
     * Set a variable
     *
     * @param string $var Variable name
     * @param string $value Value
     * @return void
     */
    public function set($var, $value)
    {
        $this->data[$var] = $value;
    }

    /**
     * Get all (just) the arguments as an array
     *
     * @return array
     */
    public function getArgs()
    {
        $out = [];
        foreach ($this->data as $arg => $value) {
            if (substr($arg, 0, 5) == '__arg') {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * Parse a string and return an argument array
     *
     * This mimics the way the argv array is created from the
     * parameters given when a program is invoked from the command line
     *
     * @param mixed $input Input as argument string
     * @return void
     */
    public static function parseArgumentString($input)
    {
        $args = preg_split(
            "/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|"
            . "[\s,]*'([^']+)'[\s,]*|" . "[\s,]+/",
            trim($input),
            0,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
        );
        return $args;
    }
}

/**
 * Qi_Console_ArgVException
 *
 * @uses Exception
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @version 1.3.4
 */
class Qi_Console_ArgVException extends Exception
{
}
