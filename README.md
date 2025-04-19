# GetUsed - PHP Namespace Import Generator

GetUsed is a powerful tool that automatically generates PHP `use` statements for your code. It scans your PHP files to detect all classes, functions, and constants being used, then creates properly formatted import statements ready to paste at the top of your file.

## Why Use This Tool?

### 1. Clean, Readable Code
Instead of using fully qualified names with backslashes (`\Exception`, `\json_encode()`), proper imports make your code cleaner and more readable.

### 2. Prevent Errors
Missing backslashes in namespaced code can cause hard-to-debug errors. Using proper imports eliminates this risk entirely.

### 3. Save Time
Manually tracking and writing imports is tedious. GetUsed generates them all in seconds with a single command.

### 4. IDE Integration
With the included VSCode integration, you can generate imports with a keyboard shortcut (Ctrl+Shift+T).

### 5. Maintainability
If a class moves to a different namespace, you only need to update the import statement, not every occurrence in your code.

## How It Works

GetUsed analyzes your PHP code to:
- Detect classes, interfaces, and traits being used
- Find function calls that could benefit from imports
- Identify constants referenced in your code
- Generate properly formatted `use` statements
- Comment out imports that already exist in your file

__VSCode Screenshot__

![Visual Studio Code Example Response](/www/used/assets/screenshot-vscode.png)

## Technical Background

The `use` statement in PHP allows you to import classes, functions, and constants from other namespaces. For example, if you call `json_encode()` within a namespaced class, PHP first searches in the current namespace before falling back to the global namespace.

You can use backslashes like `\json_encode()` to reference the global namespace directly, but this makes code less readable. A better approach is to import what you need at the top of your file:

```php
// Import a class
use DateTime;

// Import a function
use function json_encode;

// Import a constant
use const PHP_EOL;

// Import with an alias
use function MyNamespace\myJsonEncoder as json_encode;
```

GetUsed automates this process by analyzing your code and generating all necessary import statements.

## Installation

```sh
# create directory if not exist
mkdir -p ~/bin/many

# enter directory
cd ~/bin/many

# clone GetUsed
git clone https://github.com/eypsilon/get-used.git

# make it executable
chmod -v 770 ~/bin/many/get-used/GetUsed.php
```

## Usage

### From Terminal

```sh
~/bin/many/get-used/GetUsed.php /path/to/src/AnyClass.php
```

### Via Web Interface

```sh
cd ~/bin/many/get-used/www/used
php -S localhost:8000
```

Then open [localhost:8000](http://localhost:8000) in your browser.

### Set an Alias (recommended)

```sh
# Edit your bash aliases
~$ sudo gedit ~/.bash_aliases

# Add this line
alias GetUsed='~/bin/many/get-used/GetUsed.php'

# Refresh aliases
~$ source ~/.bash_aliases
```

Now you can simply use:
```sh
GetUsed /path/to/src/AnyClass.php

# Get help
GetUsed -h
```

### VSCode Integration

Add this to your `~/.config/Code/User/keybindings.json`:

```json
{
    "key": "ctrl+shift+t",
    "command": "workbench.action.terminal.sendSequence",
    "args": { "text": "GetUsed ${file}\u000D" }
}
```

Now you can press Ctrl+Shift+T on any open PHP file to generate import statements instantly.

## Example Output

```php
// file = /path/to/src/Http/Curler.php
// start = 1661266197.6779
// end = 1661266197.7762

/** defined(0), taken(0), constant(2), class(2), function(2), total(6) */

use DateTime;
use DateTimeZone;
use function array_keys;
use function array_merge;
use const PHP_EOL;
use const JSON_UNESCAPED_SLASHES;
```

If imports already exist in your file, GetUsed will comment them out to avoid duplicates.

## Screenshots

__Web Interface__

![Web Interface Example Response](/www/used/assets/screenshot-sw.png)

__Terminal Output__

![Terminal Example Response](/www/used/assets/screenshot-sw-terminal-out.png)

---

## Authors

- Original tool by [Engin Ypsilon](https://github.com/eypsilon)
- README by [Claude](https://www.anthropic.com/claude), Anthropic's AI assistant
