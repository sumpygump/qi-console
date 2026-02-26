<?php

/**
 * Menu class file
 *
 * @package Qi
 * @subpackage Console
 */

/**
 * Create menus from the cli
 *
 * @package Qi
 * @subpackage Console
 * @author Jansen Price <jansen.price@gmail.com>
 * @version $Id$
 */
class Qi_Console_Menu
{
    /**
     * The highest choice in the menu
     *
     * @var int
     */
    public $highest_choice;

    /**
     * The title of the menu
     *
     * @var string
     */
    protected $title;

    /**
     * An array of the menu choices
     *
     * @var array
     */
    protected $menu_items = [];

    /**
     * The column count of the terminal window
     *
     * @var int
     */
    protected $maxlen;

    /*
     * @var int count
     */
    protected $count;

    /**
     * Number of columns to display the menu items
     *
     * @var int
     */
    protected $columns = 3;

    /**
     * Terminfo The Terminfo object
     *
     * @var object
     */
    protected $terminfo;

    /**
     * Create a new menu object
     *
     * @param mixed $title The title of the menu
     * @param array $menu_items An array of menu choices
     * @param array $options Options for configuration of the menu
     * @return void
     */
    public function __construct($title, $menu_items, $options = [])
    {
        $this->menu_items = $menu_items;
        $this->title      = $title;
        $this->maxlen     = 0;
        $this->count      = count($this->menu_items);
        if (isset($menu_items[0])) {
            $this->highest_choice = $this->count - 1;
        } else {
            $this->highest_choice = $this->count;
        }

        if ($options) {
            // Set columns for menu
            if (isset($options['columns'])) {
                $this->columns = $options['columns'];
            }

            // Pass in terminfo object
            if (isset($options['terminfo'])) {
                $this->terminfo = $options['terminfo'];
            }
        }

        if (!$this->terminfo) {
            include_once 'Terminfo.php';
            $this->terminfo = new Qi_Console_Terminfo();
        }

        $this->getLongestMenuItem();
    }

    /**
     * Output the menu items to stdout
     *
     * @return void
     */
    public function displayMenuItems()
    {
        Qi_Console_Std::out($this->terminfo->doCapability('clear'));
        Qi_Console_Std::out("\n" . $this->doTitle($this->title));

        $entries_per_column = ceil($this->count / $this->columns);

        if (isset($this->menu_items[0])) {
            $first = 0;
            $last  = $entries_per_column;
        } else {
            $first = 1;
            $last  = $entries_per_column + 1;
        }

        // Loop through each menu_item
        for ($i = $first; $i < $last; $i++) {
            Qi_Console_Std::out("\n");

            // Make each column
            for ($c = 0; $c < $this->columns; $c++) {
                $index = $i + ($c * $entries_per_column);

                if (!isset($this->menu_items[$index])) {
                    continue;
                }

                if ($this->columns > 1) {
                    // Pad each entry so they line up correctly.
                    Qi_Console_Std::out(
                        sprintf(
                            "%2s. %-" . $this->maxlen . "s  ",
                            $index,
                            $this->menu_items[$index]
                        )
                    );
                } else {
                    Qi_Console_Std::out(
                        sprintf(
                            "%2s. %s",
                            $index,
                            $this->menu_items[$index]
                        )
                    );
                }
            }
        }

        Qi_Console_Std::out("\n");
    }

    /**
     * Prompt the user and gather the input
     *
     * @param string $prompt The text of the prompt
     * @param mixed $default The default prompt string
     * @return string The input from user
     */
    public function prompt($prompt, $default = null)
    {
        Qi_Console_Std::out("\n" . $prompt);
        return Qi_Console_Std::in('fgets', $default);
    }

    /**
     * Find the longest menu item in character length
     *
     * @return void
     */
    private function getLongestMenuItem()
    {
        foreach ($this->menu_items as $menu_item) {
            $length = strlen($menu_item);
            if ($length > $this->maxlen) {
                $this->maxlen = $length;
            }
        }
    }

    /**
     * Format the title of the menu
     *
     * @param string $text The title text
     * @return string
     */
    private function doTitle($text)
    {
        $out = '';
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $out .= $text[$i] . " ";
        }
        $out .= "\n" . str_repeat("=", $len * 2) . "\n";
        return $out;
    }
}
