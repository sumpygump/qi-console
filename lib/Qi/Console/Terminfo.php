<?php

/**
 * Terminfo class file
 *
 * @package    Qi
 * @subpackage Console
 */

/**
 * Terminfo
 * Class for getting values from terminfo
 *
 * @package    Qi
 * @subpackage Console
 * @author     Jansen Price <jansen.price@gmail.com>
 * @license    http://www.opensource.org/licenses/mit-license.php MIT
 * @version    $Id$
 */
class Qi_Console_Terminfo
{
    /**
     * Private vars
     *
     * @var mixed
     */
    private $term;

    /**
     * Terminfo path
     *
     * This is the path where the terminfo files are located
     *
     * @var string
     */
    private $terminfoPath = '/lib/terminfo';

    /**
     * Terminfo filename
     *
     * @var string
     */
    private $terminfoFilename;

    /**
     * Terminfo data
     *
     * @var array
     */
    private $terminfoData;

    /**
     * Terminfo binary data
     *
     * @var mixed
     */
    private $terminfoBindata;

    /**
     * Capabilities
     *
     * An array of capabilities index by cap name
     *
     * @var array
     */
    private $capabilities = [];

    /**
     * Names
     *
     * @var array
     */
    private $names = [];

    /**
     * Cache of terminfo capabilities
     *
     * @var array
     */
    private $cache = [];

    /**
     * Whether this terminal is a cygwin terminal
     *
     * @var bool
     */
    private $isCygwin = false;

    /**
     * #@+
     * Capability type constants
     *
     * @var mixed
     */
    public const CAP_TYPE_FLAG        = 1;
    public const CAP_TYPE_NUMBER      = 2;
    public const CAP_TYPE_STRING      = 3;
    public const CAP_TYPE_NUMBER_CHAR = '#';
    public const CAP_TYPE_STRING_CHAR = '=';
    /**
     * #@-
     */

    /**
     * Capability types
     *
     * @var array
     */
    public static $capTypes = [
        self::CAP_TYPE_NUMBER_CHAR => self::CAP_TYPE_NUMBER,
        self::CAP_TYPE_STRING_CHAR => self::CAP_TYPE_STRING
    ];

    /**
     * Override terminal to force tty
     *
     * @var mixed
     */
    protected $overrideTerminal = null;

    /**
     * __construct
     *
     * @param  bool   $forceBin         Whether to get terminfo data from binary in $TERM
     * @param  string $overrideTerminal Force to use a certain terminal by name
     * @return void
     */
    public function __construct($forceBin = false, $overrideTerminal = null)
    {
        if (PHP_SAPI != 'cli') {
            throw new Exception(
                'PHP SAPI is not cli. Is this not a shell terminal?'
            );
        }

        if (
            isset($_SERVER['TERM'])
            && strpos($_SERVER['TERM'], 'cygwin') !== false
        ) {
            $this->isCygwin = true;
        }

        if ($overrideTerminal != null) {
            $this->overrideTerminal = $overrideTerminal;
        }

        if (!$forceBin) {
            $this->getTerminfoData();
        }

        if (
            (DIRECTORY_SEPARATOR != "\\" && !$this->isCygwin)
            && !$this->terminfoData
        ) {
            $this->getTerminfoBinData();
            echo $this->hexView($this->terminfoBindata);
        }

        $this->parseTerminfoData();
    }

    /**
     * Use 'infocmp' to get the terminfo data.
     *
     * @return string The terminfo data
     */
    public function getTerminfoData()
    {
        if (
            (DIRECTORY_SEPARATOR == "\\" && !$this->isCygwin)
            || !isset($_SERVER['TERM'])
        ) {
            $this->terminfoData = null;
            return null;
        }

        $cmd = 'infocmp';

        if ($this->overrideTerminal) {
            $cmd .= ' ' . $this->overrideTerminal;
        }

        exec($cmd, $output, $return);
        if (!$return) {
            $this->terminfoData = $output;
        }

        return $output;
    }

    /**
     * Get capability by name
     *
     * @param  mixed $capName Capability name
     * @param  mixed $verbose Verbose output
     * @return string|bool
     */
    public function getCapability($capName, $verbose = false)
    {
        if (!isset($this->capabilities[$capName])) {
            return false;
        }

        if ($verbose) {
            return $capName . " : ("
                . self::$capDefs[$capName]['variable_name'] . ") "
                . self::$capDefs[$capName]['description'] . " = '"
                . $this->capabilities[$capName] . "'";
        }

        return $this->capabilities[$capName];
    }

    /**
     * Output the capability for a given cap name
     *
     * @param  mixed $capName The name of the capability
     * @return string A description of the capability
     */
    public function displayCapability($capName)
    {
        $out = $capName . " : ("
            . self::$capDefs[$capName]['variable_name'] . ") "
            . self::$capDefs[$capName]['description'] . " = '";

        if (isset($this->capabilities[$capName])) {
            $out .= $this->capabilities[$capName];
        } else {
            $out .= "NOT CAPABLE";
        }

        $out .= "'";
        return $out;
    }

    /**
     * Whether this terminal has a certain capability
     *
     * @param  mixed $capName The name of the capability
     * @return bool Whether capability is present
     */
    public function hasCapability($capName)
    {
        if (isset($this->capabilities[$capName])) {
            return true;
        }
        return false;
    }

    /**
     * dump
     *
     * @return void
     */
    public function dump()
    {
        $out = '';

        foreach ($this->capabilities as $capName => $capability) {
            if (!array_key_exists($capName, self::$capDefs)) {
                // Ignore non-standard termcaps like meml or memu
                continue;
            }

            $out .= "[" . $capName . "] => "
                . "(" . self::$capDefs[$capName]['description'] . ") "
                . " '" . $capability . "'\n";
        }

        echo $out;
    }

    /**
     * Dump the cache array
     *
     * @return void
     */
    public function dumpCache()
    {
        foreach ($this->cache as $key => $value) {
            echo $key . " => ";

            $len = strlen($value);
            for ($i = 0; $i < $len; $i++) {
                echo sprintf("%02X", ord($value[$i])) . " ";
            }

            echo "\n";
        }
    }

    /**
     * Magic call method
     *
     * Attempt to call a capability
     *
     * @param  mixed $method The capability to call
     * @param  mixed $args   Arguments to pass to the capability
     * @return string|bool
     */
    public function __call($method, $args)
    {
        if (isset($this->capabilities[$method])) {
            $args = array_merge([$method], $args);
            return call_user_func_array([$this, 'doCapability'], $args);
        }

        return false;
    }

    /**
     * getCacheKey
     *
     * @param  mixed $parms Params for cache key
     * @return string
     */
    private function getCacheKey($parms = [])
    {
        return implode("-", $parms);
    }

    /**
     * doCapability
     *
     * @param  mixed $capName Capability name
     * @return string
     */
    public function doCapability($capName)
    {
        $parms    = func_get_args();
        $cacheKey = $this->getCacheKey($parms);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $capString = $this->getCapability($capName, false);
        $parmCount = func_num_args() - 1;

        $reqParmCount = $this->getRequiredParmCount($capString);

        if ($reqParmCount == 0) {
            // handle special ctrl-characters (^J, ^H, etc)
            if (substr($capString, 0, 1) == '^') {
                $char      = substr($capString, 1, 1);
                $capString = chr(ord($char) - 62);

                $this->cache[$cacheKey] = $capString;

                return $capString;
            }
            $out = str_replace('\E', chr(27), $capString);
            $out = str_replace('\017', chr(octdec(17)), $out);

            $this->cache[$cacheKey] = $out;

            return $out;
        }

        if ($parmCount < $reqParmCount) {
            throw new Exception(
                "Too few params for call to '$capName'. "
                . "Received $parmCount, expecting $reqParmCount.\n"
            );
        }

        $parms = func_get_args();
        $out   = $this->processCapabilityParms($capString, $parms);

        $out = str_replace('\E', chr(27), $out);

        $this->cache[$cacheKey] = $out;
        return $out;
    }

    /**
     * Count how many parms are needed for this capability
     *
     * This was ported from tput c library
     * see _nc_tparm_analyze() in ncurses/tinfo/lib_tparm.c
     *
     * @param  mixed $capString Capability string
     * @return int
     */
    private function getRequiredParmCount($capString)
    {
        $popcount = 0; // highest param number
        $strLen  = strlen($capString);

        for ($cp = 0; $cp < $strLen; $cp++) {
            if ($capString[$cp] == '%') {
                switch ($capString[++$cp]) {
                    case '%':
                        $cp++;
                        break;
                    case 'i':
                        if ($popcount < 1) {
                            $popcount = 1;
                        }
                        break;
                    case 'p':
                        $cp++;
                        $i = $capString[$cp];
                        if (
                            is_numeric($i)
                            && $popcount < $i
                        ) {
                            $popcount = $i;
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        return $popcount;
    }

    /**
     * Fold in the parms supplied into the capString
     *
     * This was ported from the tput c library
     * Note: The 0th parm is expected to be the cap_name,
     *  so p1 starts at $parm[1]
     *
     * @param  mixed $capString The capability string
     * @param  mixed $parms     Params to pass to the capability
     * @return string
     */
    private function processCapabilityParms($capString, $parms)
    {
        $strlen    = strlen($capString);
        $parmIndex = 0;
        $out       = '';
        $stack     = [];

        for ($cp = 0; $cp < $strlen; $cp++) {
            if ($capString[$cp] != '%') {
                $out .= $capString[$cp];
            } else {
                $cp++;
                if ($cp >= $strlen) {
                    continue;
                }
                switch ($capString[$cp]) {
                    case '%':
                        $out .= '%';
                        break;
                    case 'd':
                    case 'o':
                    case 'x':
                    case 'X':
                    case 'c':
                        $out .= array_pop($stack);
                        break;
                    case 'p':
                        $cp++;
                        $parmIndex = $capString[$cp];
                        array_push($stack, $parms[$parmIndex]);
                        break;
                    case 'i':
                        if (isset($parms[1])) {
                            $parms[1]++;
                        }
                        if (isset($parms[2])) {
                            $parms[2]++;
                        }
                        break;
                    case '{':     // output literal between braces
                        $cp++;
                        $value = '';
                        while ($capString[$cp] != '}') {
                            $value .= $capString[$cp];
                            $cp++;
                        }
                        array_push($stack, (int)$value);
                        break;
                    case '=':
                        $y = array_pop($stack);
                        $x = array_pop($stack);
                        array_push($stack, ($x == $y));
                        break;
                    case '<':
                        $y = array_pop($stack);
                        $x = array_pop($stack);
                        array_push($stack, ($x < $y));
                        break;
                    case '>':
                        $y = array_pop($stack);
                        $x = array_pop($stack);
                        array_push($stack, ($x > $y));
                        break;
                    case '+':
                        $y = array_pop($stack);
                        $x = array_pop($stack);
                        array_push($stack, ($x + $y));
                        break;
                    case '-':
                        $y = array_pop($stack);
                        $x = array_pop($stack);
                        array_push($stack, ($x - $y));
                        break;
                    case 't':
                        $x = array_pop($stack);
                        if (!$x) {
                            // skip forward to the next %e or %; in level 0
                            $cp++;
                            $level = 0;
                            while ($cp < $strlen) {
                                if ($capString[$cp] == '%') {
                                    $cp++;
                                    if ($capString[$cp] == '?') {
                                        $level++;
                                    } elseif ($capString[$cp] == ';') {
                                        if ($level > 0) {
                                            $level--;
                                        } else {
                                            break;
                                        }
                                    } elseif (
                                        $capString[$cp] == 'e'
                                        && $level == 0
                                    ) {
                                        break;
                                    }
                                }
                                $cp++;
                            }
                        }
                        break;
                    case 'e':
                        // skip forward to the next %; in level 0
                        $cp++;
                        $level = 0;
                        while ($cp < $strlen) {
                            if ($capString[$cp] == '%') {
                                $cp++;
                                if ($capString[$cp] == '?') {
                                    $level++;
                                } elseif ($capString[$cp] == ';') {
                                    if ($level > 0) {
                                        $level--;
                                    } else {
                                        break;
                                    }
                                }
                            }
                            $cp++;
                        }
                        break;
                    case '?':
                        break;
                    case ';':
                        break;
                    default:
                        break;
                }
            }
        }
        return $out;
    }

    /**
     * Parse the terminfo data to populate capabilities
     *
     * @return void
     */
    private function parseTerminfoData()
    {
        if ($this->terminfoData) {
            if (
                isset($this->terminfoData[0])
                && substr(trim($this->terminfoData[0]), 0, 1) == '#'
            ) {
                unset($this->terminfoData[0]);
            }
            $this->terminfoData[1] = trim($this->terminfoData[1], ",\t\r\n ");
            $this->names           = explode("|", $this->terminfoData[1]);
            unset($this->terminfoData[1]);
        } else {
            $this->terminfoData = [];
        }

        $terminfoString = implode('', $this->terminfoData);
        $terminfoCaps   = explode(',', $terminfoString);

        $this->capabilities = [];
        foreach ($terminfoCaps as $capDef) {
            $cap = $this->parseTerminfoCapability($capDef);
            if ($cap !== false) {
                $this->capabilities[$cap[0]] = $cap[2];
            }
        }
    }

    /**
     * Parse a terminfo capability
     *
     * @param  mixed $string Capabiilty string
     * @return array Array with code, type and value
     */
    private function parseTerminfoCapability($string)
    {
        $string = trim($string);
        if ($string === '') {
            return false;
        }

        if (strpos($string, self::CAP_TYPE_NUMBER_CHAR)) {
            // It's a number
            $parts = explode(self::CAP_TYPE_NUMBER_CHAR, $string);

            $capabilityNameCode = $parts[0];
            $capabilityType     = self::CAP_TYPE_NUMBER;
            $capabilityValue    = $parts[1];
        } elseif (strpos($string, self::CAP_TYPE_STRING_CHAR)) {
            // It's a string
            $splitpoint = strpos($string, self::CAP_TYPE_STRING_CHAR);

            $parts = [];

            $parts[0] = substr($string, 0, $splitpoint);
            $parts[1] = substr($string, $splitpoint + 1);

            $capabilityNameCode = $parts[0];
            $capabilityType     = self::CAP_TYPE_STRING;
            $capabilityValue    = $parts[1];
        } else {
            $capabilityNameCode = $string;
            $capabilityType     = self::CAP_TYPE_FLAG;
            $capabilityValue    = true;
        }

        return [
            $capabilityNameCode, $capabilityType, $capabilityValue
        ];
    }

    /**
     * Use the $TERM information to lookup and get the binary file terminfo data
     *
     * @return void
     */
    public function getTerminfoBinData()
    {
        if (!isset($_SERVER['TERM'])) {
            $this->terminfoData = null;
            $this->term          = '';
            return false;
        }

        $this->term = $_SERVER['TERM'];

        $this->terminfoFilename = $this->getTerminfoFilename();

        if (file_exists($this->terminfoFilename)) {
            $this->terminfoBindata = file_get_contents(
                $this->terminfoFilename
            );
        }

        return $this->terminfoBindata;
    }

    /**
     * Get the filename with terminfo data
     *
     * @return string
     */
    public function getTerminfoFilename()
    {
        $path = $this->terminfoPath;

        $path .= DIRECTORY_SEPARATOR
            . substr($this->term, 0, 1) . DIRECTORY_SEPARATOR;

        $path .= $this->term;
        return $path;
    }

    /**
     * View hex chars of string
     *
     * Outputs a listing of hexidecimal values in 16 byte rows
     *
     * @param  mixed $text Input text
     * @return string
     */
    private function hexView($text)
    {
        $num            = 16;
        $outStr         = '';
        $printableChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
            . 'abcdefghijklmnopqrstuvwxyz'
            . '0123456789~!@#$%^&*()_+-={}|[]\:";\'<>?,./';

        if (null === $text) {
            return $outStr;
        }

        $charCount = strlen($text);
        for ($i = 0; $i < $charCount; $i += $num) {
            $printStr = '';
            for ($j = 0; $j < $num; $j++) {
                $char = substr($text, $i + $j, 1);

                $outStr .= sprintf("%02X", ord($char)) . " ";

                if (ord($char) >= 32 && ord($char) < 127) {
                    $printStr .= $char;
                } else {
                    $printStr .= ".";
                }
            }

            $outStr .= " | " . $printStr . "\n";
        }

        return $outStr;
    }

    /**
     * An array of all the capabilities defined by terminfo
     *
     * Format: Variable, Cap Name, Termcap Code, Description
     *
     * @var array
     */
    public static $capDefs = [
        //@codingStandardsIgnoreStart
        'bw' => [
            'variable_name' => 'auto_left_margin',
            'cap_name'      => 'bw',
            'tcap_code'     => 'bw',
            'description'   => 'cub1 wraps from column 0 to last column',
        ],
        'am' => [
            'variable_name' => 'auto_right_margin',
            'cap_name'      => 'am',
            'tcap_code'     => 'am',
            'description'   => 'terminal has automatic margins',
        ],
        'bce' => [
            'variable_name' => 'back_color_erase',
            'cap_name'      => 'bce',
            'tcap_code'     => 'ut',
            'description'   => 'screen erased with background color',
        ],
        'ccc' => [
            'variable_name' => 'can_change',
            'cap_name'      => 'ccc',
            'tcap_code'     => 'cc',
            'description'   => 'terminal can re-define existing colors',
        ],
        'xhp' => [
            'variable_name' => 'ceol_standout_glitch',
            'cap_name'      => 'xhp',
            'tcap_code'     => 'xs',
            'description'   => 'standout not erased by overwriting (hp)',
        ],
        'xhpa' => [
            'variable_name' => 'col_addr_glitch',
            'cap_name'      => 'xhpa',
            'tcap_code'     => 'YA',
            'description'   => 'only positive motion for hpa/mhpa caps',
        ],
        'cpix' => [
            'variable_name' => 'cpi_changes_res',
            'cap_name'      => 'cpix',
            'tcap_code'     => 'YF',
            'description'   => 'changing character pitch changes resolution',
        ],
        'crxm' => [
            'variable_name' => 'cr_cancels_micro_mode',
            'cap_name'      => 'crxm',
            'tcap_code'     => 'YB',
            'description'   => 'using cr turns off micro mode',
        ],
        'xt' => [
            'variable_name' => 'dest_tabs_magic_smso',
            'cap_name'      => 'xt',
            'tcap_code'     => 'xt',
            'description'   => 'tabs destructive, magic so char (t1061)',
        ],
        'xenl' => [
            'variable_name' => 'eat_newline_glitch',
            'cap_name'      => 'xenl',
            'tcap_code'     => 'xn',
            'description'   => 'newline ignored after 80 cols (concept)',
        ],
        'eo' => [
            'variable_name' => 'erase_overstrike',
            'cap_name'      => 'eo',
            'tcap_code'     => 'eo',
            'description'   => 'can erase overstrikes with a blank',
        ],
        'gn' => [
            'variable_name' => 'generic_type',
            'cap_name'      => 'gn',
            'tcap_code'     => 'gn',
            'description'   => 'generic line type',
        ],
        'hc' => [
            'variable_name' => 'hard_copy',
            'cap_name'      => 'hc',
            'tcap_code'     => 'hc',
            'description'   => 'hardcopy terminal',
        ],
        'chts' => [
            'variable_name' => 'hard_cursor',
            'cap_name'      => 'chts',
            'tcap_code'     => 'HC',
            'description'   => 'cursor is hard to see',
        ],
        'km' => [
            'variable_name' => 'has_meta_key',
            'cap_name'      => 'km',
            'tcap_code'     => 'km',
            'description'   => 'Has a meta key (i.e., sets 8th-bit)',
        ],
        'daisy' => [
            'variable_name' => 'has_print_wheel',
            'cap_name'      => 'daisy',
            'tcap_code'     => 'YC',
            'description'   => 'printer needs operator to change character set',
        ],
        'hs' => [
            'variable_name' => 'has_status_line',
            'cap_name'      => 'hs',
            'tcap_code'     => 'hs',
            'description'   => 'has extra status line',
        ],
        'hls' => [
            'variable_name' => 'hue_lightness_saturation',
            'cap_name'      => 'hls',
            'tcap_code'     => 'hl',
            'description'   => 'terminal uses only HLS color notation (Tektronix)',
        ],
        'in' => [
            'variable_name' => 'insert_null_glitch',
            'cap_name'      => 'in',
            'tcap_code'     => 'in',
            'description'   => 'insert mode distinguishes nulls',
        ],
        'lpix' => [
            'variable_name' => 'lpi_changes_res',
            'cap_name'      => 'lpix',
            'tcap_code'     => 'YG',
            'description'   => 'changing line pitch changes resolution',
        ],
        'da' => [
            'variable_name' => 'memory_above',
            'cap_name'      => 'da',
            'tcap_code'     => 'da',
            'description'   => 'display may be retained above the screen',
        ],
        'db' => [
            'variable_name' => 'memory_below',
            'cap_name'      => 'db',
            'tcap_code'     => 'db',
            'description'   => 'display may be retained below the screen',
        ],
        'mir' => [
            'variable_name' => 'move_insert_mode',
            'cap_name'      => 'mir',
            'tcap_code'     => 'mi',
            'description'   => 'safe to move while in insert mode',
        ],
        'msgr' => [
            'variable_name' => 'move_standout_mode',
            'cap_name'      => 'msgr',
            'tcap_code'     => 'ms',
            'description'   => 'safe to move while in standout mode',
        ],
        'nxon' => [
            'variable_name' => 'needs_xon_xoff',
            'cap_name'      => 'nxon',
            'tcap_code'     => 'nx',
            'description'   => 'padding will not work, xon/xoff required',
        ],
        'xsb' => [
            'variable_name' => 'no_esc_ctlc',
            'cap_name'      => 'xsb',
            'tcap_code'     => 'xb',
            'description'   => 'beehive (f1=escape, f2=ctrl C)',
        ],
        'npc' => [
            'variable_name' => 'no_pad_char',
            'cap_name'      => 'npc',
            'tcap_code'     => 'NP',
            'description'   => 'pad character does not exist',
        ],
        'ndscr' => [
            'variable_name' => 'non_dest_scroll_region',
            'cap_name'      => 'ndscr',
            'tcap_code'     => 'ND',
            'description'   => 'scrolling region is non-destructive',
        ],
        'nrrmc' => [
            'variable_name' => 'non_rev_rmcup',
            'cap_name'      => 'nrrmc',
            'tcap_code'     => 'NR',
            'description'   => 'smcup does not reverse rmcup',
        ],
        'os' => [
            'variable_name' => 'over_strike',
            'cap_name'      => 'os',
            'tcap_code'     => 'os',
            'description'   => 'terminal can overstrike',
        ],
        'mc5i' => [
            'variable_name' => 'prtr_silent',
            'cap_name'      => 'mc5i',
            'tcap_code'     => '5i',
            'description'   => 'printer will not echo on screen',
        ],
        'xvpa' => [
            'variable_name' => 'row_addr_glitch',
            'cap_name'      => 'xvpa',
            'tcap_code'     => 'YD',
            'description'   => 'only positive motion for vpa/mvpa caps',
        ],
        'sam' => [
            'variable_name' => 'semi_auto_right_margin',
            'cap_name'      => 'sam',
            'tcap_code'     => 'YE',
            'description'   => 'printing in last column causes cr',
        ],
        'eslok' => [
            'variable_name' => 'status_line_esc_ok',
            'cap_name'      => 'eslok',
            'tcap_code'     => 'es',
            'description'   => 'escape can be used on the status line',
        ],
        'hz' => [
            'variable_name' => 'tilde_glitch',
            'cap_name'      => 'hz',
            'tcap_code'     => 'hz',
            'description'   => 'cannot print ~\'s (hazeltine)',
        ],
        'ul' => [
            'variable_name' => 'transparent_underline',
            'cap_name'      => 'ul',
            'tcap_code'     => 'ul',
            'description'   => 'underline character overstrikes',
        ],
        'xon' => [
            'variable_name' => 'xon_xoff',
            'cap_name'      => 'xon',
            'tcap_code'     => 'xo',
            'description'   => 'terminal uses xon/xoff handshaking',
        ],
        'cols' => [
            'variable_name' => 'columns',
            'cap_name'      => 'cols',
            'tcap_code'     => 'co',
            'description'   => 'number of columns in a line',
        ],
        'it' => [
            'variable_name' => 'init_tabs',
            'cap_name'      => 'it',
            'tcap_code'     => 'it',
            'description'   => 'tabs initially every # spaces',
        ],
        'lh' => [
            'variable_name' => 'label_height',
            'cap_name'      => 'lh',
            'tcap_code'     => 'lh',
            'description'   => 'rows in each label',
        ],
        'lw' => [
            'variable_name' => 'label_width',
            'cap_name'      => 'lw',
            'tcap_code'     => 'lw',
            'description'   => 'columns in each label',
        ],
        'lines' => [
            'variable_name' => 'lines',
            'cap_name'      => 'lines',
            'tcap_code'     => 'li',
            'description'   => 'number of lines on screen or page',
        ],
        'lm' => [
            'variable_name' => 'lines_of_memory',
            'cap_name'      => 'lm',
            'tcap_code'     => 'lm',
            'description'   => 'lines of memory if > line. 0 means varies',
        ],
        'xmc' => [
            'variable_name' => 'magic_cookie_glitch',
            'cap_name'      => 'xmc',
            'tcap_code'     => 'sg',
            'description'   => 'number of blank characters left by smso or rmso',
        ],
        'ma' => [
            'variable_name' => 'max_attributes',
            'cap_name'      => 'ma',
            'tcap_code'     => 'ma',
            'description'   => 'maximum combined attributes terminal can handle',
        ],
        'colors' => [
            'variable_name' => 'max_colors',
            'cap_name'      => 'colors',
            'tcap_code'     => 'Co',
            'description'   => 'maximum number of colors on screen',
        ],
        'pairs' => [
            'variable_name' => 'max_pairs',
            'cap_name'      => 'pairs',
            'tcap_code'     => 'pa',
            'description'   => 'maximum number of color-pairs on the screen',
        ],
        'wnum' => [
            'variable_name' => 'maximum_windows',
            'cap_name'      => 'wnum',
            'tcap_code'     => 'MW',
            'description'   => 'maximum number of defineable windows',
        ],
        'ncv' => [
            'variable_name' => 'no_color_video',
            'cap_name'      => 'ncv',
            'tcap_code'     => 'NC',
            'description'   => 'video attributes that cannot be used with colors',
        ],
        'nlab' => [
            'variable_name' => 'num_labels',
            'cap_name'      => 'nlab',
            'tcap_code'     => 'Nl',
            'description'   => 'number of labels on screen',
        ],
        'pb' => [
            'variable_name' => 'padding_baud_rate',
            'cap_name'      => 'pb',
            'tcap_code'     => 'pb',
            'description'   => 'lowest baud rate where padding needed',
        ],
        'vt' => [
            'variable_name' => 'virtual_terminal',
            'cap_name'      => 'vt',
            'tcap_code'     => 'vt',
            'description'   => 'virtual terminal number (CB/unix)',
        ],
        'wsl' => [
            'variable_name' => 'width_status_line',
            'cap_name'      => 'wsl',
            'tcap_code'     => 'ws',
            'description'   => 'number of columns in status line',
        ],
        'bitwin' => [
            'variable_name' => 'bit_image_entwining',
            'cap_name'      => 'bitwin',
            'tcap_code'     => 'Yo',
            'description'   => 'number of passes for each bit-image row',
        ],
        'bitype' => [
            'variable_name' => 'bit_image_type',
            'cap_name'      => 'bitype',
            'tcap_code'     => 'Yp',
            'description'   => 'type of bit-image device',
        ],
        'bufsz' => [
            'variable_name' => 'buffer_capacity',
            'cap_name'      => 'bufsz',
            'tcap_code'     => 'Ya',
            'description'   => 'numbers of bytes buffered before printing',
        ],
        'btns' => [
            'variable_name' => 'buttons',
            'cap_name'      => 'btns',
            'tcap_code'     => 'BT',
            'description'   => 'number of buttons on mouse',
        ],
        'spinh' => [
            'variable_name' => 'dot_horz_spacing',
            'cap_name'      => 'spinh',
            'tcap_code'     => 'Yc',
            'description'   => 'spacing of dots horizontally in dots per inch',
        ],
        'spinv' => [
            'variable_name' => 'dot_vert_spacing',
            'cap_name'      => 'spinv',
            'tcap_code'     => 'Yb',
            'description'   => 'spacing of pins vertically in pins per inch',
        ],
        'maddr' => [
            'variable_name' => 'max_micro_address',
            'cap_name'      => 'maddr',
            'tcap_code'     => 'Yd',
            'description'   => 'maximum value in micro_..._address',
        ],
        'mjump' => [
            'variable_name' => 'max_micro_jump',
            'cap_name'      => 'mjump',
            'tcap_code'     => 'Ye',
            'description'   => 'maximum value in parm_..._micro',
        ],
        'mcs' => [
            'variable_name' => 'micro_col_size',
            'cap_name'      => 'mcs',
            'tcap_code'     => 'Yf',
            'description'   => 'character step size when in micro mode',
        ],
        'mls' => [
            'variable_name' => 'micro_line_size',
            'cap_name'      => 'mls',
            'tcap_code'     => 'Yg',
            'description'   => 'line step size when in micro mode',
        ],
        'npins' => [
            'variable_name' => 'number_of_pins',
            'cap_name'      => 'npins',
            'tcap_code'     => 'Yh',
            'description'   => 'numbers of pins in print-head',
        ],
        'orc' => [
            'variable_name' => 'output_res_char',
            'cap_name'      => 'orc',
            'tcap_code'     => 'Yi',
            'description'   => 'horizontal resolution in units per line',
        ],
        'orhi' => [
            'variable_name' => 'output_res_horz_inch',
            'cap_name'      => 'orhi',
            'tcap_code'     => 'Yk',
            'description'   => 'horizontal resolution in units per inch',
        ],
        'orl' => [
            'variable_name' => 'output_res_line',
            'cap_name'      => 'orl',
            'tcap_code'     => 'Yj',
            'description'   => 'vertical resolution in units per line',
        ],
        'orvi' => [
            'variable_name' => 'output_res_vert_inch',
            'cap_name'      => 'orvi',
            'tcap_code'     => 'Yl',
            'description'   => 'vertical resolution in units per inch',
        ],
        'cps' => [
            'variable_name' => 'print_rate',
            'cap_name'      => 'cps',
            'tcap_code'     => 'Ym',
            'description'   => 'print rate in characters per second',
        ],
        'widcs' => [
            'variable_name' => 'wide_char_size',
            'cap_name'      => 'widcs',
            'tcap_code'     => 'Yn',
            'description'   => 'character step size when in double wide mode',
        ],
        'acsc' => [
            'variable_name' => 'acs_chars',
            'cap_name'      => 'acsc',
            'tcap_code'     => 'ac',
            'description'   => 'graphics charset pairs, based on vt100',
        ],
        'cbt' => [
            'variable_name' => 'back_tab',
            'cap_name'      => 'cbt',
            'tcap_code'     => 'bt',
            'description'   => 'back tab (P)',
        ],
        'bel' => [
            'variable_name' => 'bell',
            'cap_name'      => 'bel',
            'tcap_code'     => 'bl',
            'description'   => 'audible signal (bell) (P)',
        ],
        'cr' => [
            'variable_name' => 'carriage_return',
            'cap_name'      => 'cr',
            'tcap_code'     => 'cr',
            'description'   => 'carriage return (P*) (P*)',
        ],
        'cpi' => [
            'variable_name' => 'change_char_pitch',
            'cap_name'      => 'cpi',
            'tcap_code'     => 'ZA',
            'description'   => 'Change number of characters per inch to #1',
        ],
        'lpi' => [
            'variable_name' => 'change_line_pitch',
            'cap_name'      => 'lpi',
            'tcap_code'     => 'ZB',
            'description'   => 'Change number of lines per inch to #1',
        ],
        'chr' => [
            'variable_name' => 'change_res_horz',
            'cap_name'      => 'chr',
            'tcap_code'     => 'ZC',
            'description'   => 'Change horizontal resolution to #1',
        ],
        'cvr' => [
            'variable_name' => 'change_res_vert',
            'cap_name'      => 'cvr',
            'tcap_code'     => 'ZD',
            'description'   => 'Change vertical resolution to #1',
        ],
        'csr' => [
            'variable_name' => 'change_scroll_region',
            'cap_name'      => 'csr',
            'tcap_code'     => 'cs',
            'description'   => 'change region to line #1 to line #2 (P)',
        ],
        'rmp' => [
            'variable_name' => 'char_padding',
            'cap_name'      => 'rmp',
            'tcap_code'     => 'rP',
            'description'   => 'like ip but when in insert mode',
        ],
        'tbc' => [
            'variable_name' => 'clear_all_tabs',
            'cap_name'      => 'tbc',
            'tcap_code'     => 'ct',
            'description'   => 'clear all tab stops (P)',
        ],
        'mgc' => [
            'variable_name' => 'clear_margins',
            'cap_name'      => 'mgc',
            'tcap_code'     => 'MC',
            'description'   => 'clear right and left soft margins',
        ],
        'clear' => [
            'variable_name' => 'clear_screen',
            'cap_name'      => 'clear',
            'tcap_code'     => 'cl',
            'description'   => 'clear screen and home cursor (P*)',
        ],
        'el1' => [
            'variable_name' => 'clr_bol',
            'cap_name'      => 'el1',
            'tcap_code'     => 'cb',
            'description'   => 'Clear to beginning of line',
        ],
        'el' => [
            'variable_name' => 'clr_eol',
            'cap_name'      => 'el',
            'tcap_code'     => 'ce',
            'description'   => 'clear to end of line (P)',
        ],
        'ed' => [
            'variable_name' => 'clr_eos',
            'cap_name'      => 'ed',
            'tcap_code'     => 'cd',
            'description'   => 'clear to end of screen (P*)',
        ],
        'hpa' => [
            'variable_name' => 'column_address',
            'cap_name'      => 'hpa',
            'tcap_code'     => 'ch',
            'description'   => 'horizontal position #1, absolute (P)',
        ],
        'cmdch' => [
            'variable_name' => 'command_character',
            'cap_name'      => 'cmdch',
            'tcap_code'     => 'CC',
            'description'   => 'terminal settable cmd character in prototype !?',
        ],
        'cwin' => [
            'variable_name' => 'create_window',
            'cap_name'      => 'cwin',
            'tcap_code'     => 'CW',
            'description'   => 'define a window #1 from #2,#3 to #4,#5',
        ],
        'cup' => [
            'variable_name' => 'cursor_address',
            'cap_name'      => 'cup',
            'tcap_code'     => 'cm',
            'description'   => 'move to row #1 columns #2',
        ],
        'cud1' => [
            'variable_name' => 'cursor_down',
            'cap_name'      => 'cud1',
            'tcap_code'     => 'do',
            'description'   => 'down one line',
        ],
        'home' => [
            'variable_name' => 'cursor_home',
            'cap_name'      => 'home',
            'tcap_code'     => 'ho',
            'description'   => 'home cursor (if no cup)',
        ],
        'civis' => [
            'variable_name' => 'cursor_invisible',
            'cap_name'      => 'civis',
            'tcap_code'     => 'vi',
            'description'   => 'make cursor invisible',
        ],
        'cub1' => [
            'variable_name' => 'cursor_left',
            'cap_name'      => 'cub1',
            'tcap_code'     => 'le',
            'description'   => 'move left one space',
        ],
        'mrcup' => [
            'variable_name' => 'cursor_mem_address',
            'cap_name'      => 'mrcup',
            'tcap_code'     => 'CM',
            'description'   => 'memory relative cursor addressing, move to row #1 columns #2',
        ],
        'cnorm' => [
            'variable_name' => 'cursor_normal',
            'cap_name'      => 'cnorm',
            'tcap_code'     => 've',
            'description'   => 'make cursor appear normal (undo civis/cvvis)',
        ],
        'cuf1' => [
            'variable_name' => 'cursor_right',
            'cap_name'      => 'cuf1',
            'tcap_code'     => 'nd',
            'description'   => 'non-destructive space (move right one space)',
        ],
        'll' => [
            'variable_name' => 'cursor_to_ll',
            'cap_name'      => 'll',
            'tcap_code'     => 'll',
            'description'   => 'last line, first column (if no cup)',
        ],
        'cuu1' => [
            'variable_name' => 'cursor_up',
            'cap_name'      => 'cuu1',
            'tcap_code'     => 'up',
            'description'   => 'up one line',
        ],
        'cvvis' => [
            'variable_name' => 'cursor_visible',
            'cap_name'      => 'cvvis',
            'tcap_code'     => 'vs',
            'description'   => 'make cursor very visible',
        ],
        'defc' => [
            'variable_name' => 'define_char',
            'cap_name'      => 'defc',
            'tcap_code'     => 'ZE',
            'description'   => 'Define a character #1, #2 dots wide, descender #3',
        ],
        'dch1' => [
            'variable_name' => 'delete_character',
            'cap_name'      => 'dch1',
            'tcap_code'     => 'dc',
            'description'   => 'delete character (P*)',
        ],
        'dl1' => [
            'variable_name' => 'delete_line',
            'cap_name'      => 'dl1',
            'tcap_code'     => 'dl',
            'description'   => 'delete line (P*)',
        ],
        'dial' => [
            'variable_name' => 'dial_phone',
            'cap_name'      => 'dial',
            'tcap_code'     => 'DI',
            'description'   => 'dial number #1',
        ],
        'dsl' => [
            'variable_name' => 'dis_status_line',
            'cap_name'      => 'dsl',
            'tcap_code'     => 'ds',
            'description'   => 'disable status line',
        ],
        'dclk' => [
            'variable_name' => 'display_clock',
            'cap_name'      => 'dclk',
            'tcap_code'     => 'DK',
            'description'   => 'display clock',
        ],
        'hd' => [
            'variable_name' => 'down_half_line',
            'cap_name'      => 'hd',
            'tcap_code'     => 'hd',
            'description'   => 'half a line down',
        ],
        'enacs' => [
            'variable_name' => 'ena_acs',
            'cap_name'      => 'enacs',
            'tcap_code'     => 'eA',
            'description'   => 'enable alternate char set',
        ],
        'smacs' => [
            'variable_name' => 'enter_alt_charset_mode',
            'cap_name'      => 'smacs',
            'tcap_code'     => 'as',
            'description'   => 'start alternate character set (P)',
        ],
        'smam' => [
            'variable_name' => 'enter_am_mode',
            'cap_name'      => 'smam',
            'tcap_code'     => 'SA',
            'description'   => 'turn on automatic margins',
        ],
        'blink' => [
            'variable_name' => 'enter_blink_mode',
            'cap_name'      => 'blink',
            'tcap_code'     => 'mb',
            'description'   => 'turn on blinking',
        ],
        'bold' => [
            'variable_name' => 'enter_bold_mode',
            'cap_name'      => 'bold',
            'tcap_code'     => 'md',
            'description'   => 'turn on bold (extra bright) mode',
        ],
        'smcup' => [
            'variable_name' => 'enter_ca_mode',
            'cap_name'      => 'smcup',
            'tcap_code'     => 'ti',
            'description'   => 'string to start programs using cup',
        ],
        'smdc' => [
            'variable_name' => 'enter_delete_mode',
            'cap_name'      => 'smdc',
            'tcap_code'     => 'dm',
            'description'   => 'enter delete mode',
        ],
        'dim' => [
            'variable_name' => 'enter_dim_mode',
            'cap_name'      => 'dim',
            'tcap_code'     => 'mh',
            'description'   => 'turn on half-bright mode',
        ],
        'swidm' => [
            'variable_name' => 'enter_doublewide_mode',
            'cap_name'      => 'swidm',
            'tcap_code'     => 'ZF',
            'description'   => 'Enter double-wide mode',
        ],
        'sdrfq' => [
            'variable_name' => 'enter_draft_quality',
            'cap_name'      => 'sdrfq',
            'tcap_code'     => 'ZG',
            'description'   => 'Enter draft-quality mode',
        ],
        'smir' => [
            'variable_name' => 'enter_insert_mode',
            'cap_name'      => 'smir',
            'tcap_code'     => 'im',
            'description'   => 'enter insert mode',
        ],
        'sitm' => [
            'variable_name' => 'enter_italics_mode',
            'cap_name'      => 'sitm',
            'tcap_code'     => 'ZH',
            'description'   => 'Enter italic mode',
        ],
        'slm' => [
            'variable_name' => 'enter_leftward_mode',
            'cap_name'      => 'slm',
            'tcap_code'     => 'ZI',
            'description'   => 'Start leftward carriage motion',
        ],
        'smicm' => [
            'variable_name' => 'enter_micro_mode',
            'cap_name'      => 'smicm',
            'tcap_code'     => 'ZJ',
            'description'   => 'Start micro-motion mode',
        ],
        'snlq' => [
            'variable_name' => 'enter_near_letter_quality',
            'cap_name'      => 'snlq',
            'tcap_code'     => 'ZK',
            'description'   => 'Enter NLQ mode',
        ],
        'snrmq' => [
            'variable_name' => 'enter_normal_quality',
            'cap_name'      => 'snrmq',
            'tcap_code'     => 'ZL',
            'description'   => 'Enter normal-quality mode',
        ],
        'prot' => [
            'variable_name' => 'enter_protected_mode',
            'cap_name'      => 'prot',
            'tcap_code'     => 'mp',
            'description'   => 'turn on protected mode',
        ],
        'rev' => [
            'variable_name' => 'enter_reverse_mode',
            'cap_name'      => 'rev',
            'tcap_code'     => 'mr',
            'description'   => 'turn on reverse video mode',
        ],
        'invis' => [
            'variable_name' => 'enter_secure_mode',
            'cap_name'      => 'invis',
            'tcap_code'     => 'mk',
            'description'   => 'turn on blank mode (characters invisible)',
        ],
        'sshm' => [
            'variable_name' => 'enter_shadow_mode',
            'cap_name'      => 'sshm',
            'tcap_code'     => 'ZM',
            'description'   => 'Enter shadow-print mode',
        ],
        'smso' => [
            'variable_name' => 'enter_standout_mode',
            'cap_name'      => 'smso',
            'tcap_code'     => 'so',
            'description'   => 'begin standout mode',
        ],
        'ssubm' => [
            'variable_name' => 'enter_subscript_mode',
            'cap_name'      => 'ssubm',
            'tcap_code'     => 'ZN',
            'description'   => 'Enter subscript mode',
        ],
        'ssupm' => [
            'variable_name' => 'enter_superscript_mode',
            'cap_name'      => 'ssupm',
            'tcap_code'     => 'ZO',
            'description'   => 'Enter superscript mode',
        ],
        'smul' => [
            'variable_name' => 'enter_underline_mode',
            'cap_name'      => 'smul',
            'tcap_code'     => 'us',
            'description'   => 'begin underline mode',
        ],
        'sum' => [
            'variable_name' => 'enter_upward_mode',
            'cap_name'      => 'sum',
            'tcap_code'     => 'ZP',
            'description'   => 'Start upward carriage motion',
        ],
        'smxon' => [
            'variable_name' => 'enter_xon_mode',
            'cap_name'      => 'smxon',
            'tcap_code'     => 'SX',
            'description'   => 'turn on xon/xoff handshaking',
        ],
        'ech' => [
            'variable_name' => 'erase_chars',
            'cap_name'      => 'ech',
            'tcap_code'     => 'ec',
            'description'   => 'erase #1 characters (P)',
        ],
        'rmacs' => [
            'variable_name' => 'exit_alt_charset_mode',
            'cap_name'      => 'rmacs',
            'tcap_code'     => 'ae',
            'description'   => 'end alternate character set (P)',
        ],
        'rmam' => [
            'variable_name' => 'exit_am_mode',
            'cap_name'      => 'rmam',
            'tcap_code'     => 'RA',
            'description'   => 'turn off automatic margins',
        ],
        'sgr0' => [
            'variable_name' => 'exit_attribute_mode',
            'cap_name'      => 'sgr0',
            'tcap_code'     => 'me',
            'description'   => 'turn off all attributes',
        ],
        'rmcup' => [
            'variable_name' => 'exit_ca_mode',
            'cap_name'      => 'rmcup',
            'tcap_code'     => 'te',
            'description'   => 'strings to end programs using cup',
        ],
        'rmdc' => [
            'variable_name' => 'exit_delete_mode',
            'cap_name'      => 'rmdc',
            'tcap_code'     => 'ed',
            'description'   => 'end delete mode',
        ],
        'rwidm' => [
            'variable_name' => 'exit_doublewide_mode',
            'cap_name'      => 'rwidm',
            'tcap_code'     => 'ZQ',
            'description'   => 'End double-wide mode',
        ],
        'rmir' => [
            'variable_name' => 'exit_insert_mode',
            'cap_name'      => 'rmir',
            'tcap_code'     => 'ei',
            'description'   => 'exit insert mode',
        ],
        'ritm' => [
            'variable_name' => 'exit_italics_mode',
            'cap_name'      => 'ritm',
            'tcap_code'     => 'ZR',
            'description'   => 'End italic mode',
        ],
        'rlm' => [
            'variable_name' => 'exit_leftward_mode',
            'cap_name'      => 'rlm',
            'tcap_code'     => 'ZS',
            'description'   => 'End left-motion mode',
        ],
        'rmicm' => [
            'variable_name' => 'exit_micro_mode',
            'cap_name'      => 'rmicm',
            'tcap_code'     => 'ZT',
            'description'   => 'End micro-motion mode',
        ],
        'rshm' => [
            'variable_name' => 'exit_shadow_mode',
            'cap_name'      => 'rshm',
            'tcap_code'     => 'ZU',
            'description'   => 'End shadow-print mode',
        ],
        'rmso' => [
            'variable_name' => 'exit_standout_mode',
            'cap_name'      => 'rmso',
            'tcap_code'     => 'se',
            'description'   => 'exit standout mode',
        ],
        'rsubm' => [
            'variable_name' => 'exit_subscript_mode',
            'cap_name'      => 'rsubm',
            'tcap_code'     => 'ZV',
            'description'   => 'End subscript mode',
        ],
        'rsupm' => [
            'variable_name' => 'exit_superscript_mode',
            'cap_name'      => 'rsupm',
            'tcap_code'     => 'ZW',
            'description'   => 'End superscript mode',
        ],
        'rmul' => [
            'variable_name' => 'exit_underline_mode',
            'cap_name'      => 'rmul',
            'tcap_code'     => 'ue',
            'description'   => 'exit underline mode',
        ],
        'rum' => [
            'variable_name' => 'exit_upward_mode',
            'cap_name'      => 'rum',
            'tcap_code'     => 'ZX',
            'description'   => 'End reverse character motion',
        ],
        'rmxon' => [
            'variable_name' => 'exit_xon_mode',
            'cap_name'      => 'rmxon',
            'tcap_code'     => 'RX',
            'description'   => 'turn off xon/xoff handshaking',
        ],
        'pause' => [
            'variable_name' => 'fixed_pause',
            'cap_name'      => 'pause',
            'tcap_code'     => 'PA',
            'description'   => 'pause for 2-3 seconds',
        ],
        'hook' => [
            'variable_name' => 'flash_hook',
            'cap_name'      => 'hook',
            'tcap_code'     => 'fh',
            'description'   => 'flash switch hook',
        ],
        'flash' => [
            'variable_name' => 'flash_screen',
            'cap_name'      => 'flash',
            'tcap_code'     => 'vb',
            'description'   => 'visible bell (may not move cursor)',
        ],
        'ff' => [
            'variable_name' => 'form_feed',
            'cap_name'      => 'ff',
            'tcap_code'     => 'ff',
            'description'   => 'hardcopy terminal page eject (P*)',
        ],
        'fsl' => [
            'variable_name' => 'from_status_line',
            'cap_name'      => 'fsl',
            'tcap_code'     => 'fs',
            'description'   => 'return from status line',
        ],
        'wingo' => [
            'variable_name' => 'goto_window',
            'cap_name'      => 'wingo',
            'tcap_code'     => 'WG',
            'description'   => 'go to window #1',
        ],
        'hup' => [
            'variable_name' => 'hangup',
            'cap_name'      => 'hup',
            'tcap_code'     => 'HU',
            'description'   => 'hang-up phone',
        ],
        'is1' => [
            'variable_name' => 'init_1string',
            'cap_name'      => 'is1',
            'tcap_code'     => 'i1',
            'description'   => 'initialization string',
        ],
        'is2' => [
            'variable_name' => 'init_2string',
            'cap_name'      => 'is2',
            'tcap_code'     => 'is',
            'description'   => 'initialization string',
        ],
        'is3' => [
            'variable_name' => 'init_3string',
            'cap_name'      => 'is3',
            'tcap_code'     => 'i3',
            'description'   => 'initialization string',
        ],
        'if' => [
            'variable_name' => 'init_file',
            'cap_name'      => 'if',
            'tcap_code'     => 'if',
            'description'   => 'name of initialization file',
        ],
        'iprog' => [
            'variable_name' => 'init_prog',
            'cap_name'      => 'iprog',
            'tcap_code'     => 'iP',
            'description'   => 'path name of program for initialization',
        ],
        'initc' => [
            'variable_name' => 'initialize_color',
            'cap_name'      => 'initc',
            'tcap_code'     => 'Ic',
            'description'   => 'initialize color #1 to (#2,#3,#4)',
        ],
        'initp' => [
            'variable_name' => 'initialize_pair',
            'cap_name'      => 'initp',
            'tcap_code'     => 'Ip',
            'description'   => 'Initialize color pair #1 to fg=(#2,#3,#4], bg=(#5,#6,#7)',
        ],
        'ich1' => [
            'variable_name' => 'insert_character',
            'cap_name'      => 'ich1',
            'tcap_code'     => 'ic',
            'description'   => 'insert character (P)',
        ],
        'il1' => [
            'variable_name' => 'insert_line',
            'cap_name'      => 'il1',
            'tcap_code'     => 'al',
            'description'   => 'insert line (P*)',
        ],
        'ip' => [
            'variable_name' => 'insert_padding',
            'cap_name'      => 'ip',
            'tcap_code'     => 'ip',
            'description'   => 'insert padding after inserted character',
        ],
        'ka1' => [
            'variable_name' => 'key_a1',
            'cap_name'      => 'ka1',
            'tcap_code'     => 'K1',
            'description'   => 'upper left of keypad',
        ],
        'ka3' => [
            'variable_name' => 'key_a3',
            'cap_name'      => 'ka3',
            'tcap_code'     => 'K3',
            'description'   => 'upper right of keypad',
        ],
        'kb2' => [
            'variable_name' => 'key_b2',
            'cap_name'      => 'kb2',
            'tcap_code'     => 'K2',
            'description'   => 'center of keypad',
        ],
        'kbs' => [
            'variable_name' => 'key_backspace',
            'cap_name'      => 'kbs',
            'tcap_code'     => 'kb',
            'description'   => 'backspace key',
        ],
        'kbeg' => [
            'variable_name' => 'key_beg',
            'cap_name'      => 'kbeg',
            'tcap_code'     => '@1',
            'description'   => 'begin key',
        ],
        'kcbt' => [
            'variable_name' => 'key_btab',
            'cap_name'      => 'kcbt',
            'tcap_code'     => 'kB',
            'description'   => 'back-tab key',
        ],
        'kc1' => [
            'variable_name' => 'key_c1',
            'cap_name'      => 'kc1',
            'tcap_code'     => 'K4',
            'description'   => 'lower left of keypad',
        ],
        'kc3' => [
            'variable_name' => 'key_c3',
            'cap_name'      => 'kc3',
            'tcap_code'     => 'K5',
            'description'   => 'lower right of keypad',
        ],
        'kcan' => [
            'variable_name' => 'key_cancel',
            'cap_name'      => 'kcan',
            'tcap_code'     => '@2',
            'description'   => 'cancel key',
        ],
        'ktbc' => [
            'variable_name' => 'key_catab',
            'cap_name'      => 'ktbc',
            'tcap_code'     => 'ka',
            'description'   => 'clear-all-tabs key',
        ],
        'kclr' => [
            'variable_name' => 'key_clear',
            'cap_name'      => 'kclr',
            'tcap_code'     => 'kC',
            'description'   => 'clear-screen or erase key',
        ],
        'kclo' => [
            'variable_name' => 'key_close',
            'cap_name'      => 'kclo',
            'tcap_code'     => '@3',
            'description'   => 'close key',
        ],
        'kcmd' => [
            'variable_name' => 'key_command',
            'cap_name'      => 'kcmd',
            'tcap_code'     => '@4',
            'description'   => 'command key',
        ],
        'kcpy' => [
            'variable_name' => 'key_copy',
            'cap_name'      => 'kcpy',
            'tcap_code'     => '@5',
            'description'   => 'copy key',
        ],
        'kcrt' => [
            'variable_name' => 'key_create',
            'cap_name'      => 'kcrt',
            'tcap_code'     => '@6',
            'description'   => 'create key',
        ],
        'kctab' => [
            'variable_name' => 'key_ctab',
            'cap_name'      => 'kctab',
            'tcap_code'     => 'kt',
            'description'   => 'clear-tab key',
        ],
        'kdch1' => [
            'variable_name' => 'key_dc',
            'cap_name'      => 'kdch1',
            'tcap_code'     => 'kD',
            'description'   => 'delete-character key',
        ],
        'kdl1' => [
            'variable_name' => 'key_dl',
            'cap_name'      => 'kdl1',
            'tcap_code'     => 'kL',
            'description'   => 'delete-line key',
        ],
        'kcud1' => [
            'variable_name' => 'key_down',
            'cap_name'      => 'kcud1',
            'tcap_code'     => 'kd',
            'description'   => 'down-arrow key',
        ],
        'krmir' => [
            'variable_name' => 'key_eic',
            'cap_name'      => 'krmir',
            'tcap_code'     => 'kM',
            'description'   => 'sent by rmir or smir in insert mode',
        ],
        'kend' => [
            'variable_name' => 'key_end',
            'cap_name'      => 'kend',
            'tcap_code'     => '@7',
            'description'   => 'end key',
        ],
        'kent' => [
            'variable_name' => 'key_enter',
            'cap_name'      => 'kent',
            'tcap_code'     => '@8',
            'description'   => 'enter/send key',
        ],
        'kel' => [
            'variable_name' => 'key_eol',
            'cap_name'      => 'kel',
            'tcap_code'     => 'kE',
            'description'   => 'clear-to-end-of-line key',
        ],
        'ked' => [
            'variable_name' => 'key_eos',
            'cap_name'      => 'ked',
            'tcap_code'     => 'kS',
            'description'   => 'clear-to-end-of-screen key',
        ],
        'kext' => [
            'variable_name' => 'key_exit',
            'cap_name'      => 'kext',
            'tcap_code'     => '@9',
            'description'   => 'exit key',
        ],
        'kf0' => [
            'variable_name' => 'key_f0',
            'cap_name'      => 'kf0',
            'tcap_code'     => 'k0',
            'description'   => 'F0 function key',
        ],
        'kf1' => [
            'variable_name' => 'key_f1',
            'cap_name'      => 'kf1',
            'tcap_code'     => 'k1',
            'description'   => 'F1 function key',
        ],
        'kf10' => [
            'variable_name' => 'key_f10',
            'cap_name'      => 'kf10',
            'tcap_code'     => 'k;',
            'description'   => 'F10 function key',
        ],
        'kf11' => [
            'variable_name' => 'key_f11',
            'cap_name'      => 'kf11',
            'tcap_code'     => 'F1',
            'description'   => 'F11 function key',
        ],
        'kf12' => [
            'variable_name' => 'key_f12',
            'cap_name'      => 'kf12',
            'tcap_code'     => 'F2',
            'description'   => 'F12 function key',
        ],
        'kf13' => [
            'variable_name' => 'key_f13',
            'cap_name'      => 'kf13',
            'tcap_code'     => 'F3',
            'description'   => 'F13 function key',
        ],
        'kf14' => [
            'variable_name' => 'key_f14',
            'cap_name'      => 'kf14',
            'tcap_code'     => 'F4',
            'description'   => 'F14 function key',
        ],
        'kf15' => [
            'variable_name' => 'key_f15',
            'cap_name'      => 'kf15',
            'tcap_code'     => 'F5',
            'description'   => 'F15 function key',
        ],
        'kf16' => [
            'variable_name' => 'key_f16',
            'cap_name'      => 'kf16',
            'tcap_code'     => 'F6',
            'description'   => 'F16 function key',
        ],
        'kf17' => [
            'variable_name' => 'key_f17',
            'cap_name'      => 'kf17',
            'tcap_code'     => 'F7',
            'description'   => 'F17 function key',
        ],
        'kf18' => [
            'variable_name' => 'key_f18',
            'cap_name'      => 'kf18',
            'tcap_code'     => 'F8',
            'description'   => 'F18 function key',
        ],
        'kf19' => [
            'variable_name' => 'key_f19',
            'cap_name'      => 'kf19',
            'tcap_code'     => 'F9',
            'description'   => 'F19 function key',
        ],
        'kf2' => [
            'variable_name' => 'key_f2',
            'cap_name'      => 'kf2',
            'tcap_code'     => 'k2',
            'description'   => 'F2 function key',
        ],
        'kf20' => [
            'variable_name' => 'key_f20',
            'cap_name'      => 'kf20',
            'tcap_code'     => 'FA',
            'description'   => 'F20 function key',
        ],
        'kf21' => [
            'variable_name' => 'key_f21',
            'cap_name'      => 'kf21',
            'tcap_code'     => 'FB',
            'description'   => 'F21 function key',
        ],
        'kf22' => [
            'variable_name' => 'key_f22',
            'cap_name'      => 'kf22',
            'tcap_code'     => 'FC',
            'description'   => 'F22 function key',
        ],
        'kf23' => [
            'variable_name' => 'key_f23',
            'cap_name'      => 'kf23',
            'tcap_code'     => 'FD',
            'description'   => 'F23 function key',
        ],
        'kf24' => [
            'variable_name' => 'key_f24',
            'cap_name'      => 'kf24',
            'tcap_code'     => 'FE',
            'description'   => 'F24 function key',
        ],
        'kf25' => [
            'variable_name' => 'key_f25',
            'cap_name'      => 'kf25',
            'tcap_code'     => 'FF',
            'description'   => 'F25 function key',
        ],
        'kf26' => [
            'variable_name' => 'key_f26',
            'cap_name'      => 'kf26',
            'tcap_code'     => 'FG',
            'description'   => 'F26 function key',
        ],
        'kf27' => [
            'variable_name' => 'key_f27',
            'cap_name'      => 'kf27',
            'tcap_code'     => 'FH',
            'description'   => 'F27 function key',
        ],
        'kf28' => [
            'variable_name' => 'key_f28',
            'cap_name'      => 'kf28',
            'tcap_code'     => 'FI',
            'description'   => 'F28 function key',
        ],
        'kf29' => [
            'variable_name' => 'key_f29',
            'cap_name'      => 'kf29',
            'tcap_code'     => 'FJ',
            'description'   => 'F29 function key',
        ],
        'kf3' => [
            'variable_name' => 'key_f3',
            'cap_name'      => 'kf3',
            'tcap_code'     => 'k3',
            'description'   => 'F3 function key',
        ],
        'kf30' => [
            'variable_name' => 'key_f30',
            'cap_name'      => 'kf30',
            'tcap_code'     => 'FK',
            'description'   => 'F30 function key',
        ],
        'kf31' => [
            'variable_name' => 'key_f31',
            'cap_name'      => 'kf31',
            'tcap_code'     => 'FL',
            'description'   => 'F31 function key',
        ],
        'kf32' => [
            'variable_name' => 'key_f32',
            'cap_name'      => 'kf32',
            'tcap_code'     => 'FM',
            'description'   => 'F32 function key',
        ],
        'kf33' => [
            'variable_name' => 'key_f33',
            'cap_name'      => 'kf33',
            'tcap_code'     => 'FN',
            'description'   => 'F33 function key',
        ],
        'kf34' => [
            'variable_name' => 'key_f34',
            'cap_name'      => 'kf34',
            'tcap_code'     => 'FO',
            'description'   => 'F34 function key',
        ],
        'kf35' => [
            'variable_name' => 'key_f35',
            'cap_name'      => 'kf35',
            'tcap_code'     => 'FP',
            'description'   => 'F35 function key',
        ],
        'kf36' => [
            'variable_name' => 'key_f36',
            'cap_name'      => 'kf36',
            'tcap_code'     => 'FQ',
            'description'   => 'F36 function key',
        ],
        'kf37' => [
            'variable_name' => 'key_f37',
            'cap_name'      => 'kf37',
            'tcap_code'     => 'FR',
            'description'   => 'F37 function key',
        ],
        'kf38' => [
            'variable_name' => 'key_f38',
            'cap_name'      => 'kf38',
            'tcap_code'     => 'FS',
            'description'   => 'F38 function key',
        ],
        'kf39' => [
            'variable_name' => 'key_f39',
            'cap_name'      => 'kf39',
            'tcap_code'     => 'FT',
            'description'   => 'F39 function key',
        ],
        'kf4' => [
            'variable_name' => 'key_f4',
            'cap_name'      => 'kf4',
            'tcap_code'     => 'k4',
            'description'   => 'F4 function key',
        ],
        'kf40' => [
            'variable_name' => 'key_f40',
            'cap_name'      => 'kf40',
            'tcap_code'     => 'FU',
            'description'   => 'F40 function key',
        ],
        'kf41' => [
            'variable_name' => 'key_f41',
            'cap_name'      => 'kf41',
            'tcap_code'     => 'FV',
            'description'   => 'F41 function key',
        ],
        'kf42' => [
            'variable_name' => 'key_f42',
            'cap_name'      => 'kf42',
            'tcap_code'     => 'FW',
            'description'   => 'F42 function key',
        ],
        'kf43' => [
            'variable_name' => 'key_f43',
            'cap_name'      => 'kf43',
            'tcap_code'     => 'FX',
            'description'   => 'F43 function key',
        ],
        'kf44' => [
            'variable_name' => 'key_f44',
            'cap_name'      => 'kf44',
            'tcap_code'     => 'FY',
            'description'   => 'F44 function key',
        ],
        'kf45' => [
            'variable_name' => 'key_f45',
            'cap_name'      => 'kf45',
            'tcap_code'     => 'FZ',
            'description'   => 'F45 function key',
        ],
        'kf46' => [
            'variable_name' => 'key_f46',
            'cap_name'      => 'kf46',
            'tcap_code'     => 'Fa',
            'description'   => 'F46 function key',
        ],
        'kf47' => [
            'variable_name' => 'key_f47',
            'cap_name'      => 'kf47',
            'tcap_code'     => 'Fb',
            'description'   => 'F47 function key',
        ],
        'kf48' => [
            'variable_name' => 'key_f48',
            'cap_name'      => 'kf48',
            'tcap_code'     => 'Fc',
            'description'   => 'F48 function key',
        ],
        'kf49' => [
            'variable_name' => 'key_f49',
            'cap_name'      => 'kf49',
            'tcap_code'     => 'Fd',
            'description'   => 'F49 function key',
        ],
        'kf5' => [
            'variable_name' => 'key_f5',
            'cap_name'      => 'kf5',
            'tcap_code'     => 'k5',
            'description'   => 'F5 function key',
        ],
        'kf50' => [
            'variable_name' => 'key_f50',
            'cap_name'      => 'kf50',
            'tcap_code'     => 'Fe',
            'description'   => 'F50 function key',
        ],
        'kf51' => [
            'variable_name' => 'key_f51',
            'cap_name'      => 'kf51',
            'tcap_code'     => 'Ff',
            'description'   => 'F51 function key',
        ],
        'kf52' => [
            'variable_name' => 'key_f52',
            'cap_name'      => 'kf52',
            'tcap_code'     => 'Fg',
            'description'   => 'F52 function key',
        ],
        'kf53' => [
            'variable_name' => 'key_f53',
            'cap_name'      => 'kf53',
            'tcap_code'     => 'Fh',
            'description'   => 'F53 function key',
        ],
        'kf54' => [
            'variable_name' => 'key_f54',
            'cap_name'      => 'kf54',
            'tcap_code'     => 'Fi',
            'description'   => 'F54 function key',
        ],
        'kf55' => [
            'variable_name' => 'key_f55',
            'cap_name'      => 'kf55',
            'tcap_code'     => 'Fj',
            'description'   => 'F55 function key',
        ],
        'kf56' => [
            'variable_name' => 'key_f56',
            'cap_name'      => 'kf56',
            'tcap_code'     => 'Fk',
            'description'   => 'F56 function key',
        ],
        'kf57' => [
            'variable_name' => 'key_f57',
            'cap_name'      => 'kf57',
            'tcap_code'     => 'Fl',
            'description'   => 'F57 function key',
        ],
        'kf58' => [
            'variable_name' => 'key_f58',
            'cap_name'      => 'kf58',
            'tcap_code'     => 'Fm',
            'description'   => 'F58 function key',
        ],
        'kf59' => [
            'variable_name' => 'key_f59',
            'cap_name'      => 'kf59',
            'tcap_code'     => 'Fn',
            'description'   => 'F59 function key',
        ],
        'kf6' => [
            'variable_name' => 'key_f6',
            'cap_name'      => 'kf6',
            'tcap_code'     => 'k6',
            'description'   => 'F6 function key',
        ],
        'kf60' => [
            'variable_name' => 'key_f60',
            'cap_name'      => 'kf60',
            'tcap_code'     => 'Fo',
            'description'   => 'F60 function key',
        ],
        'kf61' => [
            'variable_name' => 'key_f61',
            'cap_name'      => 'kf61',
            'tcap_code'     => 'Fp',
            'description'   => 'F61 function key',
        ],
        'kf62' => [
            'variable_name' => 'key_f62',
            'cap_name'      => 'kf62',
            'tcap_code'     => 'Fq',
            'description'   => 'F62 function key',
        ],
        'kf63' => [
            'variable_name' => 'key_f63',
            'cap_name'      => 'kf63',
            'tcap_code'     => 'Fr',
            'description'   => 'F63 function key',
        ],
        'kf7' => [
            'variable_name' => 'key_f7',
            'cap_name'      => 'kf7',
            'tcap_code'     => 'k7',
            'description'   => 'F7 function key',
        ],
        'kf8' => [
            'variable_name' => 'key_f8',
            'cap_name'      => 'kf8',
            'tcap_code'     => 'k8',
            'description'   => 'F8 function key',
        ],
        'kf9' => [
            'variable_name' => 'key_f9',
            'cap_name'      => 'kf9',
            'tcap_code'     => 'k9',
            'description'   => 'F9 function key',
        ],
        'kfnd' => [
            'variable_name' => 'key_find',
            'cap_name'      => 'kfnd',
            'tcap_code'     => '@0',
            'description'   => 'find key',
        ],
        'khlp' => [
            'variable_name' => 'key_help',
            'cap_name'      => 'khlp',
            'tcap_code'     => '%1',
            'description'   => 'help key',
        ],
        'khome' => [
            'variable_name' => 'key_home',
            'cap_name'      => 'khome',
            'tcap_code'     => 'kh',
            'description'   => 'home key',
        ],
        'kich1' => [
            'variable_name' => 'key_ic',
            'cap_name'      => 'kich1',
            'tcap_code'     => 'kI',
            'description'   => 'insert-character key',
        ],
        'kil1' => [
            'variable_name' => 'key_il',
            'cap_name'      => 'kil1',
            'tcap_code'     => 'kA',
            'description'   => 'insert-line key',
        ],
        'kcub1' => [
            'variable_name' => 'key_left',
            'cap_name'      => 'kcub1',
            'tcap_code'     => 'kl',
            'description'   => 'left-arrow key',
        ],
        'kll' => [
            'variable_name' => 'key_ll',
            'cap_name'      => 'kll',
            'tcap_code'     => 'kH',
            'description'   => 'lower-left key (home down)',
        ],
        'kmrk' => [
            'variable_name' => 'key_mark',
            'cap_name'      => 'kmrk',
            'tcap_code'     => '%2',
            'description'   => 'mark key',
        ],
        'kmsg' => [
            'variable_name' => 'key_message',
            'cap_name'      => 'kmsg',
            'tcap_code'     => '%3',
            'description'   => 'message key',
        ],
        'kmov' => [
            'variable_name' => 'key_move',
            'cap_name'      => 'kmov',
            'tcap_code'     => '%4',
            'description'   => 'move key',
        ],
        'knxt' => [
            'variable_name' => 'key_next',
            'cap_name'      => 'knxt',
            'tcap_code'     => '%5',
            'description'   => 'next key',
        ],
        'knp' => [
            'variable_name' => 'key_npage',
            'cap_name'      => 'knp',
            'tcap_code'     => 'kN',
            'description'   => 'next-page key',
        ],
        'kopn' => [
            'variable_name' => 'key_open',
            'cap_name'      => 'kopn',
            'tcap_code'     => '%6',
            'description'   => 'open key',
        ],
        'kopt' => [
            'variable_name' => 'key_options',
            'cap_name'      => 'kopt',
            'tcap_code'     => '%7',
            'description'   => 'options key',
        ],
        'kpp' => [
            'variable_name' => 'key_ppage',
            'cap_name'      => 'kpp',
            'tcap_code'     => 'kP',
            'description'   => 'previous-page key',
        ],
        'kprv' => [
            'variable_name' => 'key_previous',
            'cap_name'      => 'kprv',
            'tcap_code'     => '%8',
            'description'   => 'previous key',
        ],
        'kprt' => [
            'variable_name' => 'key_print',
            'cap_name'      => 'kprt',
            'tcap_code'     => '%9',
            'description'   => 'print key',
        ],
        'krdo' => [
            'variable_name' => 'key_redo',
            'cap_name'      => 'krdo',
            'tcap_code'     => '%0',
            'description'   => 'redo key',
        ],
        'kref' => [
            'variable_name' => 'key_reference',
            'cap_name'      => 'kref',
            'tcap_code'     => '&1',
            'description'   => 'reference key',
        ],
        'krfr' => [
            'variable_name' => 'key_refresh',
            'cap_name'      => 'krfr',
            'tcap_code'     => '&2',
            'description'   => 'refresh key',
        ],
        'krpl' => [
            'variable_name' => 'key_replace',
            'cap_name'      => 'krpl',
            'tcap_code'     => '&3',
            'description'   => 'replace key',
        ],
        'krst' => [
            'variable_name' => 'key_restart',
            'cap_name'      => 'krst',
            'tcap_code'     => '&4',
            'description'   => 'restart key',
        ],
        'kres' => [
            'variable_name' => 'key_resume',
            'cap_name'      => 'kres',
            'tcap_code'     => '&5',
            'description'   => 'resume key',
        ],
        'kcuf1' => [
            'variable_name' => 'key_right',
            'cap_name'      => 'kcuf1',
            'tcap_code'     => 'kr',
            'description'   => 'right-arrow key',
        ],
        'ksav' => [
            'variable_name' => 'key_save',
            'cap_name'      => 'ksav',
            'tcap_code'     => '&6',
            'description'   => 'save key',
        ],
        'kBEG' => [
            'variable_name' => 'key_sbeg',
            'cap_name'      => 'kBEG',
            'tcap_code'     => '&9',
            'description'   => 'shifted begin key',
        ],
        'kCAN' => [
            'variable_name' => 'key_scancel',
            'cap_name'      => 'kCAN',
            'tcap_code'     => '&0',
            'description'   => 'shifted cancel key',
        ],
        'kCMD' => [
            'variable_name' => 'key_scommand',
            'cap_name'      => 'kCMD',
            'tcap_code'     => '*1',
            'description'   => 'shifted command key',
        ],
        'kCPY' => [
            'variable_name' => 'key_scopy',
            'cap_name'      => 'kCPY',
            'tcap_code'     => '*2',
            'description'   => 'shifted copy key',
        ],
        'kCRT' => [
            'variable_name' => 'key_screate',
            'cap_name'      => 'kCRT',
            'tcap_code'     => '*3',
            'description'   => 'shifted create key',
        ],
        'kDC' => [
            'variable_name' => 'key_sdc',
            'cap_name'      => 'kDC',
            'tcap_code'     => '*4',
            'description'   => 'shifted delete-character key',
        ],
        'kDL' => [
            'variable_name' => 'key_sdl',
            'cap_name'      => 'kDL',
            'tcap_code'     => '*5',
            'description'   => 'shifted delete-line key',
        ],
        'kslt' => [
            'variable_name' => 'key_select',
            'cap_name'      => 'kslt',
            'tcap_code'     => '*6',
            'description'   => 'select key',
        ],
        'kEND' => [
            'variable_name' => 'key_send',
            'cap_name'      => 'kEND',
            'tcap_code'     => '*7',
            'description'   => 'shifted end key',
        ],
        'kEOL' => [
            'variable_name' => 'key_seol',
            'cap_name'      => 'kEOL',
            'tcap_code'     => '*8',
            'description'   => 'shifted clear-to-end-of-line key',
        ],
        'kEXT' => [
            'variable_name' => 'key_sexit',
            'cap_name'      => 'kEXT',
            'tcap_code'     => '*9',
            'description'   => 'shifted exit key',
        ],
        'kind' => [
            'variable_name' => 'key_sf',
            'cap_name'      => 'kind',
            'tcap_code'     => 'kF',
            'description'   => 'scroll-forward key',
        ],
        'kFND' => [
            'variable_name' => 'key_sfind',
            'cap_name'      => 'kFND',
            'tcap_code'     => '*0',
            'description'   => 'shifted find key',
        ],
        'kHLP' => [
            'variable_name' => 'key_shelp',
            'cap_name'      => 'kHLP',
            'tcap_code'     => '#1',
            'description'   => 'shifted help key',
        ],
        'kHOM' => [
            'variable_name' => 'key_shome',
            'cap_name'      => 'kHOM',
            'tcap_code'     => '#2',
            'description'   => 'shifted home key',
        ],
        'kIC' => [
            'variable_name' => 'key_sic',
            'cap_name'      => 'kIC',
            'tcap_code'     => '#3',
            'description'   => 'shifted insert-character key',
        ],
        'kLFT' => [
            'variable_name' => 'key_sleft',
            'cap_name'      => 'kLFT',
            'tcap_code'     => '#4',
            'description'   => 'shifted left-arrow key',
        ],
        'kMSG' => [
            'variable_name' => 'key_smessage',
            'cap_name'      => 'kMSG',
            'tcap_code'     => '%a',
            'description'   => 'shifted message key',
        ],
        'kMOV' => [
            'variable_name' => 'key_smove',
            'cap_name'      => 'kMOV',
            'tcap_code'     => '%b',
            'description'   => 'shifted move key',
        ],
        'kNXT' => [
            'variable_name' => 'key_snext',
            'cap_name'      => 'kNXT',
            'tcap_code'     => '%c',
            'description'   => 'shifted next key',
        ],
        'kOPT' => [
            'variable_name' => 'key_soptions',
            'cap_name'      => 'kOPT',
            'tcap_code'     => '%d',
            'description'   => 'shifted options key',
        ],
        'kPRV' => [
            'variable_name' => 'key_sprevious',
            'cap_name'      => 'kPRV',
            'tcap_code'     => '%e',
            'description'   => 'shifted previous key',
        ],
        'kPRT' => [
            'variable_name' => 'key_sprint',
            'cap_name'      => 'kPRT',
            'tcap_code'     => '%f',
            'description'   => 'shifted print key',
        ],
        'kri' => [
            'variable_name' => 'key_sr',
            'cap_name'      => 'kri',
            'tcap_code'     => 'kR',
            'description'   => 'scroll-backward key',
        ],
        'kRDO' => [
            'variable_name' => 'key_sredo',
            'cap_name'      => 'kRDO',
            'tcap_code'     => '%g',
            'description'   => 'shifted redo key',
        ],
        'kRPL' => [
            'variable_name' => 'key_sreplace',
            'cap_name'      => 'kRPL',
            'tcap_code'     => '%h',
            'description'   => 'shifted replace key',
        ],
        'kRIT' => [
            'variable_name' => 'key_sright',
            'cap_name'      => 'kRIT',
            'tcap_code'     => '%i',
            'description'   => 'shifted right-arrow key',
        ],
        'kRES' => [
            'variable_name' => 'key_srsume',
            'cap_name'      => 'kRES',
            'tcap_code'     => '%j',
            'description'   => 'shifted resume key',
        ],
        'kSAV' => [
            'variable_name' => 'key_ssave',
            'cap_name'      => 'kSAV',
            'tcap_code'     => '!1',
            'description'   => 'shifted save key',
        ],
        'kSPD' => [
            'variable_name' => 'key_ssuspend',
            'cap_name'      => 'kSPD',
            'tcap_code'     => '!2',
            'description'   => 'shifted suspend key',
        ],
        'khts' => [
            'variable_name' => 'key_stab',
            'cap_name'      => 'khts',
            'tcap_code'     => 'kT',
            'description'   => 'set-tab key',
        ],
        'kUND' => [
            'variable_name' => 'key_sundo',
            'cap_name'      => 'kUND',
            'tcap_code'     => '!3',
            'description'   => 'shifted undo key',
        ],
        'kspd' => [
            'variable_name' => 'key_suspend',
            'cap_name'      => 'kspd',
            'tcap_code'     => '&7',
            'description'   => 'suspend key',
        ],
        'kund' => [
            'variable_name' => 'key_undo',
            'cap_name'      => 'kund',
            'tcap_code'     => '&8',
            'description'   => 'undo key',
        ],
        'kcuu1' => [
            'variable_name' => 'key_up',
            'cap_name'      => 'kcuu1',
            'tcap_code'     => 'ku',
            'description'   => 'up-arrow key',
        ],
        'rmkx' => [
            'variable_name' => 'keypad_local',
            'cap_name'      => 'rmkx',
            'tcap_code'     => 'ke',
            'description'   => 'leave \'keyboard_transmit\' mode',
        ],
        'smkx' => [
            'variable_name' => 'keypad_xmit',
            'cap_name'      => 'smkx',
            'tcap_code'     => 'ks',
            'description'   => 'enter \'keyboard_transmit\' mode',
        ],
        'lf0' => [
            'variable_name' => 'lab_f0',
            'cap_name'      => 'lf0',
            'tcap_code'     => 'l0',
            'description'   => 'label on function key f0 if not f0',
        ],
        'lf1' => [
            'variable_name' => 'lab_f1',
            'cap_name'      => 'lf1',
            'tcap_code'     => 'l1',
            'description'   => 'label on function key f1 if not f1',
        ],
        'lf10' => [
            'variable_name' => 'lab_f10',
            'cap_name'      => 'lf10',
            'tcap_code'     => 'la',
            'description'   => 'label on function key f10 if not f10',
        ],
        'lf2' => [
            'variable_name' => 'lab_f2',
            'cap_name'      => 'lf2',
            'tcap_code'     => 'l2',
            'description'   => 'label on function key f2 if not f2',
        ],
        'lf3' => [
            'variable_name' => 'lab_f3',
            'cap_name'      => 'lf3',
            'tcap_code'     => 'l3',
            'description'   => 'label on function key f3 if not f3',
        ],
        'lf4' => [
            'variable_name' => 'lab_f4',
            'cap_name'      => 'lf4',
            'tcap_code'     => 'l4',
            'description'   => 'label on function key f4 if not f4',
        ],
        'lf5' => [
            'variable_name' => 'lab_f5',
            'cap_name'      => 'lf5',
            'tcap_code'     => 'l5',
            'description'   => 'label on function key f5 if not f5',
        ],
        'lf6' => [
            'variable_name' => 'lab_f6',
            'cap_name'      => 'lf6',
            'tcap_code'     => 'l6',
            'description'   => 'label on function key f6 if not f6',
        ],
        'lf7' => [
            'variable_name' => 'lab_f7',
            'cap_name'      => 'lf7',
            'tcap_code'     => 'l7',
            'description'   => 'label on function key f7 if not f7',
        ],
        'lf8' => [
            'variable_name' => 'lab_f8',
            'cap_name'      => 'lf8',
            'tcap_code'     => 'l8',
            'description'   => 'label on function key f8 if not f8',
        ],
        'lf9' => [
            'variable_name' => 'lab_f9',
            'cap_name'      => 'lf9',
            'tcap_code'     => 'l9',
            'description'   => 'label on function key f9 if not f9',
        ],
        'fln' => [
            'variable_name' => 'label_format',
            'cap_name'      => 'fln',
            'tcap_code'     => 'Lf',
            'description'   => 'label format',
        ],
        'rmln' => [
            'variable_name' => 'label_off',
            'cap_name'      => 'rmln',
            'tcap_code'     => 'LF',
            'description'   => 'turn off soft labels',
        ],
        'smln' => [
            'variable_name' => 'label_on',
            'cap_name'      => 'smln',
            'tcap_code'     => 'LO',
            'description'   => 'turn on soft labels',
        ],
        'rmm' => [
            'variable_name' => 'meta_off',
            'cap_name'      => 'rmm',
            'tcap_code'     => 'mo',
            'description'   => 'turn off meta mode',
        ],
        'smm' => [
            'variable_name' => 'meta_on',
            'cap_name'      => 'smm',
            'tcap_code'     => 'mm',
            'description'   => 'turn on meta mode (8th-bit on)',
        ],
        'mhpa' => [
            'variable_name' => 'micro_column_address',
            'cap_name'      => 'mhpa',
            'tcap_code'     => 'ZY',
            'description'   => 'Like column_address in micro mode',
        ],
        'mcud1' => [
            'variable_name' => 'micro_down',
            'cap_name'      => 'mcud1',
            'tcap_code'     => 'ZZ',
            'description'   => 'Like cursor_down in micro mode',
        ],
        'mcub1' => [
            'variable_name' => 'micro_left',
            'cap_name'      => 'mcub1',
            'tcap_code'     => 'Za',
            'description'   => 'Like cursor_left in micro mode',
        ],
        'mcuf1' => [
            'variable_name' => 'micro_right',
            'cap_name'      => 'mcuf1',
            'tcap_code'     => 'Zb',
            'description'   => 'Like cursor_right in micro mode',
        ],
        'mvpa' => [
            'variable_name' => 'micro_row_address',
            'cap_name'      => 'mvpa',
            'tcap_code'     => 'Zc',
            'description'   => 'Like row_address #1 in micro mode',
        ],
        'mcuu1' => [
            'variable_name' => 'micro_up',
            'cap_name'      => 'mcuu1',
            'tcap_code'     => 'Zd',
            'description'   => 'Like cursor_up in micro mode',
        ],
        'nel' => [
            'variable_name' => 'newline',
            'cap_name'      => 'nel',
            'tcap_code'     => 'nw',
            'description'   => 'newline (behave like cr followed by lf)',
        ],
        'porder' => [
            'variable_name' => 'order_of_pins',
            'cap_name'      => 'porder',
            'tcap_code'     => 'Ze',
            'description'   => 'Match software bits to print-head pins',
        ],
        'oc' => [
            'variable_name' => 'orig_colors',
            'cap_name'      => 'oc',
            'tcap_code'     => 'oc',
            'description'   => 'Set all color pairs to the original ones',
        ],
        'op' => [
            'variable_name' => 'orig_pair',
            'cap_name'      => 'op',
            'tcap_code'     => 'op',
            'description'   => 'Set default pair to its original value',
        ],
        'pad' => [
            'variable_name' => 'pad_char',
            'cap_name'      => 'pad',
            'tcap_code'     => 'pc',
            'description'   => 'padding char (instead of null)',
        ],
        'dch' => [
            'variable_name' => 'parm_dch',
            'cap_name'      => 'dch',
            'tcap_code'     => 'DC',
            'description'   => 'delete #1 characters (P*)',
        ],
        'dl' => [
            'variable_name' => 'parm_delete_line',
            'cap_name'      => 'dl',
            'tcap_code'     => 'DL',
            'description'   => 'delete #1 lines (P*)',
        ],
        'cud' => [
            'variable_name' => 'parm_down_cursor',
            'cap_name'      => 'cud',
            'tcap_code'     => 'DO',
            'description'   => 'down #1 lines (P*)',
        ],
        'mcud' => [
            'variable_name' => 'parm_down_micro',
            'cap_name'      => 'mcud',
            'tcap_code'     => 'Zf',
            'description'   => 'Like parm_down_cursor in micro mode',
        ],
        'ich' => [
            'variable_name' => 'parm_ich',
            'cap_name'      => 'ich',
            'tcap_code'     => 'IC',
            'description'   => 'insert #1 characters (P*)',
        ],
        'indn' => [
            'variable_name' => 'parm_index',
            'cap_name'      => 'indn',
            'tcap_code'     => 'SF',
            'description'   => 'scroll forward #1 lines (P)',
        ],
        'il' => [
            'variable_name' => 'parm_insert_line',
            'cap_name'      => 'il',
            'tcap_code'     => 'AL',
            'description'   => 'insert #1 lines (P*)',
        ],
        'cub' => [
            'variable_name' => 'parm_left_cursor',
            'cap_name'      => 'cub',
            'tcap_code'     => 'LE',
            'description'   => 'move #1 characters to the left (P)',
        ],
        'mcub' => [
            'variable_name' => 'parm_left_micro',
            'cap_name'      => 'mcub',
            'tcap_code'     => 'Zg',
            'description'   => 'Like parm_left_cursor in micro mode',
        ],
        'cuf' => [
            'variable_name' => 'parm_right_cursor',
            'cap_name'      => 'cuf',
            'tcap_code'     => 'RI',
            'description'   => 'move #1 characters to the right (P*)',
        ],
        'mcuf' => [
            'variable_name' => 'parm_right_micro',
            'cap_name'      => 'mcuf',
            'tcap_code'     => 'Zh',
            'description'   => 'Like parm_right_cursor in micro mode',
        ],
        'rin' => [
            'variable_name' => 'parm_rindex',
            'cap_name'      => 'rin',
            'tcap_code'     => 'SR',
            'description'   => 'scroll back #1 lines (P)',
        ],
        'cuu' => [
            'variable_name' => 'parm_up_cursor',
            'cap_name'      => 'cuu',
            'tcap_code'     => 'UP',
            'description'   => 'up #1 lines (P*)',
        ],
        'mcuu' => [
            'variable_name' => 'parm_up_micro',
            'cap_name'      => 'mcuu',
            'tcap_code'     => 'Zi',
            'description'   => 'Like parm_up_cursor in micro mode',
        ],
        'pfkey' => [
            'variable_name' => 'pkey_key',
            'cap_name'      => 'pfkey',
            'tcap_code'     => 'pk',
            'description'   => 'program function key #1 to type string #2',
        ],
        'pfloc' => [
            'variable_name' => 'pkey_local',
            'cap_name'      => 'pfloc',
            'tcap_code'     => 'pl',
            'description'   => 'program function key #1 to execute string #2',
        ],
        'pfx' => [
            'variable_name' => 'pkey_xmit',
            'cap_name'      => 'pfx',
            'tcap_code'     => 'px',
            'description'   => 'program function key #1 to transmit string #2',
        ],
        'pln' => [
            'variable_name' => 'plab_norm',
            'cap_name'      => 'pln',
            'tcap_code'     => 'pn',
            'description'   => 'program label #1 to show string #2',
        ],
        'mc0' => [
            'variable_name' => 'print_screen',
            'cap_name'      => 'mc0',
            'tcap_code'     => 'ps',
            'description'   => 'print contents of screen',
        ],
        'mc5p' => [
            'variable_name' => 'prtr_non',
            'cap_name'      => 'mc5p',
            'tcap_code'     => 'pO',
            'description'   => 'turn on printer for #1 bytes',
        ],
        'mc4' => [
            'variable_name' => 'prtr_off',
            'cap_name'      => 'mc4',
            'tcap_code'     => 'pf',
            'description'   => 'turn off printer',
        ],
        'mc5' => [
            'variable_name' => 'prtr_on',
            'cap_name'      => 'mc5',
            'tcap_code'     => 'po',
            'description'   => 'turn on printer',
        ],
        'pulse' => [
            'variable_name' => 'pulse',
            'cap_name'      => 'pulse',
            'tcap_code'     => 'PU',
            'description'   => 'select pulse dialing',
        ],
        'qdial' => [
            'variable_name' => 'quick_dial',
            'cap_name'      => 'qdial',
            'tcap_code'     => 'QD',
            'description'   => 'dial number #1 without checking',
        ],
        'rmclk' => [
            'variable_name' => 'remove_clock',
            'cap_name'      => 'rmclk',
            'tcap_code'     => 'RC',
            'description'   => 'remove clock',
        ],
        'rep' => [
            'variable_name' => 'repeat_char',
            'cap_name'      => 'rep',
            'tcap_code'     => 'rp',
            'description'   => 'repeat char #1 #2 times (P*)',
        ],
        'rfi' => [
            'variable_name' => 'req_for_input',
            'cap_name'      => 'rfi',
            'tcap_code'     => 'RF',
            'description'   => 'send next input char (for ptys)',
        ],
        'rs1' => [
            'variable_name' => 'reset_1string',
            'cap_name'      => 'rs1',
            'tcap_code'     => 'r1',
            'description'   => 'reset string',
        ],
        'rs2' => [
            'variable_name' => 'reset_2string',
            'cap_name'      => 'rs2',
            'tcap_code'     => 'r2',
            'description'   => 'reset string',
        ],
        'rs3' => [
            'variable_name' => 'reset_3string',
            'cap_name'      => 'rs3',
            'tcap_code'     => 'r3',
            'description'   => 'reset string',
        ],
        'rf' => [
            'variable_name' => 'reset_file',
            'cap_name'      => 'rf',
            'tcap_code'     => 'rf',
            'description'   => 'name of reset file',
        ],
        'rc' => [
            'variable_name' => 'restore_cursor',
            'cap_name'      => 'rc',
            'tcap_code'     => 'rc',
            'description'   => 'restore cursor to position of last save_cursor',
        ],
        'vpa' => [
            'variable_name' => 'row_address',
            'cap_name'      => 'vpa',
            'tcap_code'     => 'cv',
            'description'   => 'vertical position #1 absolute (P)',
        ],
        'sc' => [
            'variable_name' => 'save_cursor',
            'cap_name'      => 'sc',
            'tcap_code'     => 'sc',
            'description'   => 'save current cursor position (P)',
        ],
        'ind' => [
            'variable_name' => 'scroll_forward',
            'cap_name'      => 'ind',
            'tcap_code'     => 'sf',
            'description'   => 'scroll text up (P)',
        ],
        'ri' => [
            'variable_name' => 'scroll_reverse',
            'cap_name'      => 'ri',
            'tcap_code'     => 'sr',
            'description'   => 'scroll text down (P)',
        ],
        'scs' => [
            'variable_name' => 'select_char_set',
            'cap_name'      => 'scs',
            'tcap_code'     => 'Zj',
            'description'   => 'Select character set, #1',
        ],
        'sgr' => [
            'variable_name' => 'set_attributes',
            'cap_name'      => 'sgr',
            'tcap_code'     => 'sa',
            'description'   => 'define video attributes #1-#9 (PG9)',
        ],
        'setb' => [
            'variable_name' => 'set_background',
            'cap_name'      => 'setb',
            'tcap_code'     => 'Sb',
            'description'   => 'Set background color #1',
        ],
        'smgb' => [
            'variable_name' => 'set_bottom_margin',
            'cap_name'      => 'smgb',
            'tcap_code'     => 'Zk',
            'description'   => 'Set bottom margin at current line',
        ],
        'smgbp' => [
            'variable_name' => 'set_bottom_margin_parm',
            'cap_name'      => 'smgbp',
            'tcap_code'     => 'Zl',
            'description'   => 'Set bottom margin at line #1 or (if smgtp is not given) #2 lines from bottom',
        ],
        'sclk' => [
            'variable_name' => 'set_clock',
            'cap_name'      => 'sclk',
            'tcap_code'     => 'SC',
            'description'   => 'set clock, #1 hrs #2 mins #3 secs',
        ],
        'scp' => [
            'variable_name' => 'set_color_pair',
            'cap_name'      => 'scp',
            'tcap_code'     => 'sp',
            'description'   => 'Set current color pair to #1',
        ],
        'setf' => [
            'variable_name' => 'set_foreground',
            'cap_name'      => 'setf',
            'tcap_code'     => 'Sf',
            'description'   => 'Set foreground color #1',
        ],
        'smgl' => [
            'variable_name' => 'set_left_margin',
            'cap_name'      => 'smgl',
            'tcap_code'     => 'ML',
            'description'   => 'set left soft margin at current column. See smgl. (ML is not in BSD termcap).',
        ],
        'smglp' => [
            'variable_name' => 'set_left_margin_parm',
            'cap_name'      => 'smglp',
            'tcap_code'     => 'Zm',
            'description'   => 'Set left (right) margin at column #1',
        ],
        'smgr' => [
            'variable_name' => 'set_right_margin',
            'cap_name'      => 'smgr',
            'tcap_code'     => 'MR',
            'description'   => 'set right soft margin at current column',
        ],
        'smgrp' => [
            'variable_name' => 'set_right_margin_parm',
            'cap_name'      => 'smgrp',
            'tcap_code'     => 'Zn',
            'description'   => 'Set right margin at column #1',
        ],
        'hts' => [
            'variable_name' => 'set_tab',
            'cap_name'      => 'hts',
            'tcap_code'     => 'st',
            'description'   => 'set a tab in every row, current columns',
        ],
        'smgt' => [
            'variable_name' => 'set_top_margin',
            'cap_name'      => 'smgt',
            'tcap_code'     => 'Zo',
            'description'   => 'Set top margin at current line',
        ],
        'smgtp' => [
            'variable_name' => 'set_top_margin_parm',
            'cap_name'      => 'smgtp',
            'tcap_code'     => 'Zp',
            'description'   => 'Set top (bottom) margin at row #1',
        ],
        'wind' => [
            'variable_name' => 'set_window',
            'cap_name'      => 'wind',
            'tcap_code'     => 'wi',
            'description'   => 'current window is lines #1-#2 cols #3-#4',
        ],
        'sbim' => [
            'variable_name' => 'start_bit_image',
            'cap_name'      => 'sbim',
            'tcap_code'     => 'Zq',
            'description'   => 'Start printing bit image graphics',
        ],
        'scsd' => [
            'variable_name' => 'start_char_set_def',
            'cap_name'      => 'scsd',
            'tcap_code'     => 'Zr',
            'description'   => 'Start character set definition #1, with #2 characters in the set',
        ],
        'rbim' => [
            'variable_name' => 'stop_bit_image',
            'cap_name'      => 'rbim',
            'tcap_code'     => 'Zs',
            'description'   => 'Stop printing bit image graphics',
        ],
        'rcsd' => [
            'variable_name' => 'stop_char_set_def',
            'cap_name'      => 'rcsd',
            'tcap_code'     => 'Zt',
            'description'   => 'End definition of character set #1',
        ],
        'subcs' => [
            'variable_name' => 'subscript_characters',
            'cap_name'      => 'subcs',
            'tcap_code'     => 'Zu',
            'description'   => 'List of subscriptable characters',
        ],
        'supcs' => [
            'variable_name' => 'superscript_characters',
            'cap_name'      => 'supcs',
            'tcap_code'     => 'Zv',
            'description'   => 'List of superscriptable characters',
        ],
        'ht' => [
            'variable_name' => 'tab',
            'cap_name'      => 'ht',
            'tcap_code'     => 'ta',
            'description'   => 'tab to next 8-space hardware tab stop',
        ],
        'docr' => [
            'variable_name' => 'these_cause_cr',
            'cap_name'      => 'docr',
            'tcap_code'     => 'Zw',
            'description'   => 'Printing any of these characters causes CR',
        ],
        'tsl' => [
            'variable_name' => 'to_status_line',
            'cap_name'      => 'tsl',
            'tcap_code'     => 'ts',
            'description'   => 'move to status line, column #1',
        ],
        'tone' => [
            'variable_name' => 'tone',
            'cap_name'      => 'tone',
            'tcap_code'     => 'TO',
            'description'   => 'select touch tone dialing',
        ],
        'uc' => [
            'variable_name' => 'underline_char',
            'cap_name'      => 'uc',
            'tcap_code'     => 'uc',
            'description'   => 'underline char and move past it',
        ],
        'hu' => [
            'variable_name' => 'up_half_line',
            'cap_name'      => 'hu',
            'tcap_code'     => 'hu',
            'description'   => 'half a line up',
        ],
        'u0' => [
            'variable_name' => 'user0',
            'cap_name'      => 'u0',
            'tcap_code'     => 'u0',
            'description'   => 'User string #0',
        ],
        'u1' => [
            'variable_name' => 'user1',
            'cap_name'      => 'u1',
            'tcap_code'     => 'u1',
            'description'   => 'User string #1',
        ],
        'u2' => [
            'variable_name' => 'user2',
            'cap_name'      => 'u2',
            'tcap_code'     => 'u2',
            'description'   => 'User string #2',
        ],
        'u3' => [
            'variable_name' => 'user3',
            'cap_name'      => 'u3',
            'tcap_code'     => 'u3',
            'description'   => 'User string #3',
        ],
        'u4' => [
            'variable_name' => 'user4',
            'cap_name'      => 'u4',
            'tcap_code'     => 'u4',
            'description'   => 'User string #4',
        ],
        'u5' => [
            'variable_name' => 'user5',
            'cap_name'      => 'u5',
            'tcap_code'     => 'u5',
            'description'   => 'User string #5',
        ],
        'u6' => [
            'variable_name' => 'user6',
            'cap_name'      => 'u6',
            'tcap_code'     => 'u6',
            'description'   => 'User string #6',
        ],
        'u7' => [
            'variable_name' => 'user7',
            'cap_name'      => 'u7',
            'tcap_code'     => 'u7',
            'description'   => 'User string #7',
        ],
        'u8' => [
            'variable_name' => 'user8',
            'cap_name'      => 'u8',
            'tcap_code'     => 'u8',
            'description'   => 'User string #8',
        ],
        'u9' => [
            'variable_name' => 'user9',
            'cap_name'      => 'u9',
            'tcap_code'     => 'u9',
            'description'   => 'User string #9',
        ],
        'wait' => [
            'variable_name' => 'wait_tone',
            'cap_name'      => 'wait',
            'tcap_code'     => 'WA',
            'description'   => 'wait for dial-tone',
        ],
        'xoffc' => [
            'variable_name' => 'xoff_character',
            'cap_name'      => 'xoffc',
            'tcap_code'     => 'XF',
            'description'   => 'XOFF character',
        ],
        'xonc' => [
            'variable_name' => 'xon_character',
            'cap_name'      => 'xonc',
            'tcap_code'     => 'XN',
            'description'   => 'XON character',
        ],
        'zerom' => [
            'variable_name' => 'zero_motion',
            'cap_name'      => 'zerom',
            'tcap_code'     => 'Zx',
            'description'   => 'No motion for subsequent character',
        ],
        'scesa' => [
            'variable_name' => 'alt_scancode_esc',
            'cap_name'      => 'scesa',
            'tcap_code'     => 'S8',
            'description'   => 'Alternate escape for scancode emulation',
        ],
        'bicr' => [
            'variable_name' => 'bit_image_carriage_return',
            'cap_name'      => 'bicr',
            'tcap_code'     => 'Yv',
            'description'   => 'Move to beginning of same row',
        ],
        'binel' => [
            'variable_name' => 'bit_image_newline',
            'cap_name'      => 'binel',
            'tcap_code'     => 'Zz',
            'description'   => 'Move to next row of the bit image',
        ],
        'birep' => [
            'variable_name' => 'bit_image_repeat',
            'cap_name'      => 'birep',
            'tcap_code'     => 'Xy',
            'description'   => 'Repeat bit image cell #1 #2 times',
        ],
        'csnm' => [
            'variable_name' => 'char_set_names',
            'cap_name'      => 'csnm',
            'tcap_code'     => 'Zy',
            'description'   => 'Produce #1\'th item from list of character set names',
        ],
        'csin' => [
            'variable_name' => 'code_set_init',
            'cap_name'      => 'csin',
            'tcap_code'     => 'ci',
            'description'   => 'Init sequence for multiple codesets',
        ],
        'colornm' => [
            'variable_name' => 'color_names',
            'cap_name'      => 'colornm',
            'tcap_code'     => 'Yw',
            'description'   => 'Give name for color #1',
        ],
        'defbi' => [
            'variable_name' => 'define_bit_image_region',
            'cap_name'      => 'defbi',
            'tcap_code'     => 'Yx',
            'description'   => 'Define rectangualar bit image region',
        ],
        'devt' => [
            'variable_name' => 'device_type',
            'cap_name'      => 'devt',
            'tcap_code'     => 'dv',
            'description'   => 'Indicate language/codeset support',
        ],
        'dispc' => [
            'variable_name' => 'display_pc_char',
            'cap_name'      => 'dispc',
            'tcap_code'     => 'S1',
            'description'   => 'Display PC character #1',
        ],
        'endbi' => [
            'variable_name' => 'end_bit_image_region',
            'cap_name'      => 'endbi',
            'tcap_code'     => 'Yy',
            'description'   => 'End a bit-image region',
        ],
        'smpch' => [
            'variable_name' => 'enter_pc_charset_mode',
            'cap_name'      => 'smpch',
            'tcap_code'     => 'S2',
            'description'   => 'Enter PC character display mode',
        ],
        'smsc' => [
            'variable_name' => 'enter_scancode_mode',
            'cap_name'      => 'smsc',
            'tcap_code'     => 'S4',
            'description'   => 'Enter PC scancode mode',
        ],
        'rmpch' => [
            'variable_name' => 'exit_pc_charset_mode',
            'cap_name'      => 'rmpch',
            'tcap_code'     => 'S3',
            'description'   => 'Exit PC character display mode',
        ],
        'rmsc' => [
            'variable_name' => 'exit_scancode_mode',
            'cap_name'      => 'rmsc',
            'tcap_code'     => 'S5',
            'description'   => 'Exit PC scancode mode',
        ],
        'getm' => [
            'variable_name' => 'get_mouse',
            'cap_name'      => 'getm',
            'tcap_code'     => 'Gm',
            'description'   => 'Curses should get button events, parameter #1 not documented.',
        ],
        'kmous' => [
            'variable_name' => 'key_mouse',
            'cap_name'      => 'kmous',
            'tcap_code'     => 'Km',
            'description'   => 'Mouse event has occurred',
        ],
        'minfo' => [
            'variable_name' => 'mouse_info',
            'cap_name'      => 'minfo',
            'tcap_code'     => 'Mi',
            'description'   => 'Mouse status information',
        ],
        'pctrm' => [
            'variable_name' => 'pc_term_options',
            'cap_name'      => 'pctrm',
            'tcap_code'     => 'S6',
            'description'   => 'PC terminal options',
        ],
        'pfxl' => [
            'variable_name' => 'pkey_plab',
            'cap_name'      => 'pfxl',
            'tcap_code'     => 'xl',
            'description'   => 'Program function key #1 to type string #2 and show string #3',
        ],
        'reqmp' => [
            'variable_name' => 'req_mouse_pos',
            'cap_name'      => 'reqmp',
            'tcap_code'     => 'RQ',
            'description'   => 'Request mouse position',
        ],
        'scesc' => [
            'variable_name' => 'scancode_escape',
            'cap_name'      => 'scesc',
            'tcap_code'     => 'S7',
            'description'   => 'Escape for scancode emulation',
        ],
        's0ds' => [
            'variable_name' => 'set0_des_seq',
            'cap_name'      => 's0ds',
            'tcap_code'     => 's0',
            'description'   => 'Shift to codeset 0 (EUC set 0, ASCII)',
        ],
        's1ds' => [
            'variable_name' => 'set1_des_seq',
            'cap_name'      => 's1ds',
            'tcap_code'     => 's1',
            'description'   => 'Shift to codeset 1',
        ],
        's2ds' => [
            'variable_name' => 'set2_des_seq',
            'cap_name'      => 's2ds',
            'tcap_code'     => 's2',
            'description'   => 'Shift to codeset 2',
        ],
        's3ds' => [
            'variable_name' => 'set3_des_seq',
            'cap_name'      => 's3ds',
            'tcap_code'     => 's3',
            'description'   => 'Shift to codeset 3',
        ],
        'setab' => [
            'variable_name' => 'set_a_background',
            'cap_name'      => 'setab',
            'tcap_code'     => 'AB',
            'description'   => 'Set background color to #1, using ANSI escape',
        ],
        'setaf' => [
            'variable_name' => 'set_a_foreground',
            'cap_name'      => 'setaf',
            'tcap_code'     => 'AF',
            'description'   => 'Set foreground color to #1, using ANSI escape',
        ],
        'setcolor' => [
            'variable_name' => 'set_color_band',
            'cap_name'      => 'setcolor',
            'tcap_code'     => 'Yz',
            'description'   => 'Change to ribbon color #1',
        ],
        'smglr' => [
            'variable_name' => 'set_lr_margin',
            'cap_name'      => 'smglr',
            'tcap_code'     => 'ML',
            'description'   => 'Set both left and right margins to #1, #2. (ML is not in BSD termcap).',
        ],
        'slines' => [
            'variable_name' => 'set_page_length',
            'cap_name'      => 'slines',
            'tcap_code'     => 'YZ',
            'description'   => 'Set page length to #1 lines',
        ],
        'smgtb' => [
            'variable_name' => 'set_tb_margin',
            'cap_name'      => 'smgtb',
            'tcap_code'     => 'MT',
            'description'   => 'Sets both top and bottom margins to #1, #2',
        ],
        'ehhlm' => [
            'variable_name' => 'enter_horizontal_hl_mode',
            'cap_name'      => 'ehhlm',
            'tcap_code'     => 'Xh',
            'description'   => 'Enter horizontal highlight mode',
        ],
        'elhlm' => [
            'variable_name' => 'enter_left_hl_mode',
            'cap_name'      => 'elhlm',
            'tcap_code'     => 'Xl',
            'description'   => 'Enter left highlight mode',
        ],
        'elohlm' => [
            'variable_name' => 'enter_low_hl_mode',
            'cap_name'      => 'elohlm',
            'tcap_code'     => 'Xo',
            'description'   => 'Enter low highlight mode',
        ],
        'erhlm' => [
            'variable_name' => 'enter_right_hl_mode',
            'cap_name'      => 'erhlm',
            'tcap_code'     => 'Xr',
            'description'   => 'Enter right highlight mode',
        ],
        'ethlm' => [
            'variable_name' => 'enter_top_hl_mode',
            'cap_name'      => 'ethlm',
            'tcap_code'     => 'Xt',
            'description'   => 'Enter top highlight mode',
        ],
        'evhlm' => [
            'variable_name' => 'enter_vertical_hl_mode',
            'cap_name'      => 'evhlm',
            'tcap_code'     => 'Xv',
            'description'   => 'Enter vertical highlight mode',
        ],
        'sgr1' => [
            'variable_name' => 'set_a_attributes',
            'cap_name'      => 'sgr1',
            'tcap_code'     => 'sA',
            'description'   => 'Define second set of video attributes #1-#6',
        ],
        'slength' => [
            'variable_name' => 'set_pglen_inch',
            'cap_name'      => 'slength',
            'tcap_code'     => 'sL',
            'description'   => 'YI Set page length to #1 hundredth of an inch',
        ],
        //@codingStandardsIgnoreEnd
    ];
}
