# Many\Dev\Used | Namespace helper - get use Namespace\Keywords;

This package can create `use Keywords;` lists with all names of Classes, Functions and Constants used within a Class. It can be used from the Terminal or alternatively from a simple [Web interface](./www/used/).

The `use` keyword can be used within Classes to tell PHP, which Classes, Functions and Constants to use internally for methods used in your Class. It also can boost your scripts by pointing PHP to the right Namespace to use. For example, if you call `json_encode()` within a Class, PHP searches in the calling Class for a Function with the name `json_encode()` first, before searching it in the global Namespace (if at all). You can speed up the process with a Backslash before the function name, like: `\json_encode()`, but IDK, it looks awful. To make it a bit prettier, you can define any used Functions, Classes and Constants at the very top of your Class with the 'use'-keyword and continue programming like a Boss.

It also allows you to Alias your scripts quick and easy.

```php
use function needToChangeHtmlspecialchars as htmlspecialchars;
# if you call "htmlspecialchars()" in your class, PHP will use
# "needToChangeHtmlspecialchars()" to execute it.
```

This Package searches in the parent directory of the given file for `./vendor/autoload.php` iteratively and includes it, if found. It's required, if the Environment is namespaced and Classes are depending on other Classes within the Project.

## Install Many\Dev\Used

```sh
# create directory
mkdir -p ~/bin/many/get-used

# enter directory
cd ~/bin/many/get-used

# clone Many\Dev\Used
git clone https://github.com/eypsilon/get-used.git

# make it executable (user+group = rwx)
chmod -v 770 ~/bin/many/get-used/GetUsed.php
```

Set an Alias (feel free to set one you're comfortable with)

```sh
~$ sudo gedit ~/.bash_aliases
# put
alias GetUsed='~/bin/many/get-used/GetUsed.php'
# refresh aliases
~$ source ~/.bash_aliases
```

and use it from the Terminal

```sh
GetUsed file="/path/to/src/AnyClass.php"
# or
GetUsed /path/to/src/AnyClass.php
# Get help -h | info -i | config -c
GetUsed -h
```

---

## Via Browser as a Web Service

Use PHPs dev-server for temporary usage or configure a vhost and point the __DocumentRoot__ to `~/bin/many/get-used/www/used`.

```sh
# using PHPs dev-server
cd ~/bin/many/get-used/www/used
php -S localhost:8000
```

and open [localhost:8000](http://localhost:8000)

Or, set an Alias to start the dev-server and open the URL in a Browser with a single command. Feel free to set an Alias you feel comfortable with, always.

```sh
# set alias in ~/.bash_aliases
alias phpserver='firefox http://localhost:8000; php -S localhost:8000'

# and whenever you need a dev-server
cd ~/any/dir/app/public
phpserver
```

---

#### Get Used in VSCode

You can use this Lib also in VSCode. Set a Key combination you feel comfortable with in `~/.config/Code/User/keybindings.json`

```json
{
    "key": "ctrl+shift+t",
    "command": "workbench.action.terminal.sendSequence",
    "args": { "text": "GetUsed ${file}\u000D" }
}
```

and hit the Combo on open Files to get all use Keywords, ready for Copy&Paste.

---

### Example output

```sh
GetUsed /path/to/src/Http/Curler.php
```

```php
// file = /path/to/src/Http/Curler.php
// use_for_class = Many\Http\Curler

/** class(2), function(2), constant(2) */

use DateTime;
use DateTimeZone;

use function array_keys;
use function array_merge;

use const PHP_EOL;
use const JSON_UNESCAPED_SLASHES;
```

If the Use-Keywords are already defined in the target Class, the generated ones gets commented out.

```php
/**
 * @var array Set config
 */
Used::setConfig([
    'exclude' => [
        'class' => [],
        'function' => [],
        'constant' => [],
        'method' => [],
    ],
]);
```