# Namespace helper - use Namespace\Keywords;

This package can parse PHP scripts to extract the names of PHPs internal Classes, Functions and Constants used within a Class. The parsed Names can then be used to define `use Namespace\Keywords;` in namespaced Classes.


__VSCode Screenshot__

It's pretty hard to talk about this Topic, because it's just called "Namespace" ...

![Visual Studio Code Example Response](/www/used/assets/screenshot-vscode.png)

The `use` keyword can be used within Classes to tell PHP, which Classes, Functions and Constants to use internally for Functions used in your Class. It also can boost your scripts by pointing PHP to the right Namespace to use. For example, if you call `json_encode()` within a Class, PHP searches in the calling Class for a Function with the name `json_encode()` first, before searching it in the global Namespace (if at all). You can speed up the process with a Backslash before the function name, like: `\json_encode()`, but IDK, it looks awful. An alternate is to define any used Function, Class and Constant at the very top of your Class with the 'use'-keyword. And that's for what this Package is meant to be.

The `use` keyword also allows you to Alias and change your scripts quick and easy.

```php
use function myOwnJsonEcode as json_encode;
# if you now call "json_encode()" in your class,
# PHP will use "myOwnJsonEcode()" to execute it.
```

## Install Many\Dev\Used

```sh
# create directory if not exist
mkdir -p ~/bin/many

# enter directory
cd ~/bin/many

# clone Many\Dev\Used
git clone https://github.com/eypsilon/get-used.git

# make it executable (user+group = rwx)
chmod -v 770 ~/bin/many/get-used/GetUsed.php
```

__Usage from Terminal__

```sh
~/bin/many/get-used/GetUsed.php  file="/path/to/src/AnyClass.php"
```

__Via [Web interface](./www/used/) using PHPs dev-server__

```sh
cd ~/bin/many/get-used/www/used
php -S localhost:8000
```

and open [localhost:8000](http://localhost:8000)

---

## Set an Alias (optional)

Feel free to set one you feel comfortable with.

```sh
~$ sudo gedit ~/.bash_aliases
# put
alias GetUsed='~/bin/many/get-used/GetUsed.php'
# refresh aliases
~$ source ~/.bash_aliases
```
```sh
GetUsed file="/path/to/src/AnyClass.php"
# or
GetUsed /path/to/src/AnyClass.php
# Get help -h | info -i | config -c
GetUsed -h
```
---

### Used Keywords in Visual Studio Code

You can use this Package also in VSCode. Set a key combination in `~/.config/Code/User/keybindings.json`

```json
{
    "key": "ctrl+shift+t",
    "command": "workbench.action.terminal.sendSequence",
    "args": { "text": "GetUsed ${file}\u000D" }
}
```

and hit the combo on open Files to get `use Keywords;` on the fly.

---

#### Example output

If the generated `use Keywords;` are already defined in the target Class, the generated ones will get commented out.

```sh
GetUsed /path/to/src/Http/Curler.php
```

```php
// file = /path/to/src/Http/Curler.php
// start = 1661266197.6779
// end = 1661266197.7762

/** lines(-), defined(0), taken(0), constant(2), class(2), function(2), total(6) */

use DateTime;
use DateTimeZone;
use function array_keys;
use function array_merge;
use const PHP_EOL;
use const JSON_UNESCAPED_SLASHES;
```

#### Screenshots

__Web Interface for `GetUsed`__


![Web Interface Example Response](/www/used/assets/screenshot-sw.png)

__Terminal__
The screenshot is taken from the web interface, but it looks identical in Terminal.

![Terminal Example Response](/www/used/assets/screenshot-sw-terminal-out.png)
