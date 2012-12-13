Qi Console
==========

Qi Console provides PHP library classes for dealing with the console or terminal.

## Installation

Use composer to include the `Qi_Console` library in a project.

Add the following composer.json file to your project:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "http://github.com/sumpygump/qi-console"
        }
    ],
    "require": {
        "sumpygump/qi-console": "dev-master"
    }
}
```
    
Then run composer install to fetch.

    $ composer.phar install

You can also download the files and place them in a library folder. Be sure
to update your autoloader to handle the `Qi_Console_*` classes.

# Documentation

## ArgV

ArgV provides a way to assign and gather command line arguments

It will parse and assign option flags and arguments passed
in as command line arguments

Examples of script arguments it can potentially parse:

 - short option (-f) : can be grouped (-fvz)
 - long option (--flag)
 - short parameter (-p value)
 - short parameter shunt (-pvalue)
 - long parameter (--param value)
 - long parameter shunt (--param=value)
 - standalone argument (filename)

### Usage 

The constructor takes two arguments: `$argv` and `$rules`

The argument `$argv` is an array of arguments from the command line that is parsed by PHP
when the script is invoked. For example, when you invoke a PHP script with the
following:

    php myscript.php -v --flag --param=value okay

Then in your script PHP will provide a variable `$argv` representative of the
elements of the arguments passed in as an array like this:

```php
array(
    'myscript.php',
    '-v',
    '--flag',
    '--param=value',
    'okay',
);
```

The argument `$rules` is a definition of options and help messages. The format
is a key value array where the key is the option definition (possible
parameters) and the value is the help message for that option.

Here are some examples illustrating the possible rules keys:

 - `v` - a single letter defines a single short option flag, so this
   corresponds to the option `-v`
 - `flag` - a single word defines a long option flag (`--flag`)
 - `help|h` - a word, then vertical bar, then a single letter will define a
   long and short option (`--help` and `-h`)
 - `name|n:` - a key that ends with a colon character (`:`) indicates a
   required parameter. This allows the following: `--name=value`, `-n=value`,
   `--name value`, `-n value` and `-nvalue`. If the arguments for this option
   don't contain a parameter this will throw a `Qi_Console_ArgVException`.
 - `arg:filename` - a key that begin with the string `arg:` defines a
   non-option argument (ones that don't begin with a `-`). The word after the
   colon is the name of the argument as it will appear in ArgV. The `arg:`
   parameters in `$argv` will be assigned in the order they appear in the rules
   array. So the first non-option argument in `$argv` will be assigned to the
   first `arg:` key name, etc.

Here is some example code that illustrates the above:

```php
// Example rules with some help messages
$rules = array(
    'v' => 'Use verbose messaging',
    'o' => 'Another random option',
    'flag' => 'Flag something as special',
    'name|n:' => 'Provide a name to use',
    'arg:filename' => 'Filename to parse',
);

// Our example input
// php scriptname -v --flag --name "a name" filename.txt
$argv = array(
    'scriptname', // The first argument is always ignored by ArgV
    '-v',
    '--flag',
    '--name',
    '"a name"',
    'filename.txt',
);

$arguments = new Qi_Console_ArgV($argv, $rules);

// Now we can reference the following:
$arguments->v; // true
$arguments->o; // false
$arguments->flag; // true
$arguments->name; // equal to 'a name'
$arguments->filename; // equal to 'filename.txt'
```

Note that any additional options that were not defined in the rules array, but
were passed in as input will result in sensible defaults, so options
will default to true and named options (`--anothername=value`) will result in
the values passed in (`$arguments->anothername == 'value'`). Additional
non-option arguments will be available as `$arguments->__arg2`,
`$arguments->__arg3`, etc.

## Client

The `Qi_Console_Client` class provides a base console client that can be used
to create command line clients. It takes input as `$argv` and a Terminal object
which can be used to output messages back to the terminal.

Methods:

    __construct()
    _displayError()
    _displayMessage()
    _displayWarning()
    _halt()
    _resetTty()
    _safeExit()
    init()

### Basic Usage

```php
class MyClient extends Qi_Console_Client
{
    public function init()
    {
        // Initialization logic
    }

    public function execute()
    {
        // Execute the main logic of this client
        // Use $this->_args (ArgV object) to handle input and react
        // Use $this->_displayWarning() to output warning message to user
        // etc.
    }
}

$arguments = new Qi_Console_ArgV($argv, $rules);
$terminal = new Qi_Console_Terminal();

$myClient = new MyClient($arguments, $terminal);
$myClient->execute();
```

## ExceptionHandler

The exception handler provides a way to handle exceptions from your console
application. It uses the Terminal object to output pretty messages.

```php
$terminal = new Qi_Console_Terminal();
$exceptionHandler = new Qi_Console_ExceptionHandler($terminal);
$exceptionHandler->bindHandlers();

// Now any time an exception is thrown, it will output a pretty message
// with colors and exit.
```
    
## Menu

`Qi_Console_Menu` provides a way to prompt a user with a menu and receive
input. Please see the code for documentation.

## ProgressBar

`Qi_Console_ProgressBar` provides the ability to display a progress bar in the
terminal. Please see the code for documentation.

## Std

`Qi_Console_Std` is a wrapper for stdin, stdout and stderr.
 
This class provides methods for sending and receiving input from stdin and
output to stdout and stderr.

```php
// Receive input from STDIN
$input = Qi_Console_Std::in();

// Output to STDOUT
Qi_Console_Std::out('Some text');

// Output to STDERR
Qi_Console_Std::err('Error output');
```

## Tabular

`Qi_Console_Tabular` generates ascii tables for displaying tabular data.

```php
// Define the table data
$tableData = array(
    array('John', '28', 'Green'),
    array('Hannah', '7', 'Violet'),
    array('Michael', '43', 'Red'),
);

// Define the headers for the columns
$headers = array(
    'Name',
    'Age',
    'Favorite Color',
);

// Define optional alignment for columns
$alignment = array(
    'L',
    'R',
    'L'
);

$tabular = new Qi_Console_Tabular(
    $tableData,
    array(
        'headers'   => $headers,
        'cellalign' => $alignment,
    )
);

$tabular->display();
```

This will output the following table:

    +--------------------------------------+
    |  Name     |  Age  |  Favorite Color  |
    +--------------------------------------+
    |  John     |   28  |  Green           |
    |  Hannah   |    7  |  Violet          |
    |  Michael  |   43  |  Red             |
    +--------------------------------------+

## Terminal and Terminfo

`Qi_Console_Terminal` is a wrapper for `Qi_Console_Terminfo` which uses the
UNIX terminfo mapping database to provide functionality for outputting escape
sequences to the terminal. It provides a low-level robust way using terminal
features such as outputting colors.

For more information about terminfo, check out these resources:

 - [Man page for terminfo](http://invisible-island.net/ncurses/man/terminfo.5.html)
 - [Terminal Capabilities](https://en.wikipedia.org/wiki/Terminal_capabilities)

### Basic Usage

```php
$terminal = new Qi_Console_Terminal();

// Clear the screen
$terminal->clear();

$terminal->set_fgcolor(1);
echo "Text is now red.\n";

$terminal->set_fgcolor(2);
echo "Text is now green.\n";

$terminal->bold_type();
echo "Text is now bold.\n";

$terminal->center_text("This text is centered.");

// This is using the terminfo capability "Original Pair" to set the colors
// back to default.
$terminal->op();
echo "Now text is back to default color.\n";

// This is using the terminfo capability sgr0 which turns off all text
// attributes, meaning it is not bold anymore.
$terminal->sgr0();
echo "Now text is not bold anymore.\n";
```
