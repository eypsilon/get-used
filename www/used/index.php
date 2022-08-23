<?php error_reporting(E_ALL);

/**
 * Use Many\Dev\Used temporary as a Webservice
 * using PHPs dev-server
 *
 * ~$ cd ~/bin/many/get-used/www/used
 * ~$ php -S localhost:8000
 * ~$ firefox http://localhost:8000
 *
 * # Or, set an Alias in ~/.bash_aliases
 *
 * ~$ alias phpserver='firefox http://localhost:8000; php -S localhost:8000'
 * ~$ cd ~/bin/many/get-used/www/used
 * ~$ phpserver
 */

// shell_exec() extension is required
if (!function_exists('shell_exec'))
    exit(sprintf('PHP shell_exec() extension is required, enable it in your php.ini<hr /><b>%s</b>', php_ini_loaded_file()));

/** @return string valid html shorty */
function h() {return htmlspecialchars(...func_get_args());}

/**
 * @var string $executable path, Aliases don't work here out of the box, so
 * @var string $commentOut Comment existing use Keywords out
 * @var array $getConfig Used config
 * @var array $exec collects commandos ain an array ['php', '-S', 'localhost'] = 'php -S localhost'
 * @var array $getable available $_GET-keys
 * @var array $usedOptions arg options
 */
$executable    = '~/bin/many/get-used/GetUsed.php';
$commentOut    = 'false' !== ($_GET['comment_out'] ?? false) ? null : 'false';
$getConfig     = json_decode(shell_exec("{$executable} -c"), true)['config'] ?? [];
$exec          = [$executable];
$getable       = $getConfig['args'] ?? [];
$usedOptions   = $getConfig['options'] ?? [];

/**
 * @var mixed Link to File for VSCode
 */
$linkToFile = $_GET['file'] ?? null;
$explLink = $linkToFile ? explode(' ', $linkToFile) : $linkToFile;
if (($explLink[0] ?? null) AND !is_file($explLink[0]))
    $linkToFile = null;

/**
 * @var string|null Set output content type, default: json
 */
$returnType = false;
if ('terminal' === ($_GET['return'] ?? false) OR (($explLink[1] ?? false) AND 'return=terminal' === $explLink[1])) {
    $returnType = 'terminal';
    unset($_GET['return']);
}

/**
 * @var array Standard return content type
 */
$exec[0] .= ' return=' . ($returnType ? $returnType : 'json');

/**
 * @var array prepare shell command and exec
 */
if ($_GET) {
    // parameters to args
    foreach($getable as $key) {
        if ($getCmd = ($_GET[$key] ?? null) AND is_string($getCmd)) {
            if (in_array($getCmd, $usedOptions))
                $exec[] = $getCmd;
            else $exec[] = sprintf('%s=%s', $key, $getCmd);
        }
    }

    // run cmd
    $out = json_decode($execRun = shell_exec(implode(' ', $exec)), true);
    if ($execRun AND (!$out OR $returnType))
        $out['print'] = ($returnType ? null : "unexpected content\n\n") . print_r($execRun, true);

    // check if -option
    foreach(array_keys($usedOptions) as $opt)
        if ($out[$opt] ?? false)
            $out['print'] = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}


/**
 * @var mixed Template Engin © 1999 eypsilon
 */
?><!DOCTYPE html>
<html><head><meta charset="utf-8" />
<title>Many\Dev\Used | local-dev-many-title</title>
<meta name="description" content="Many\Dev\Used - use Keywords generator" />
<link rel="icon" sizes="16x16" href="/assets/favicon.ico" />
<style><?= file_get_contents(dirname(__FILE__) . '/assets/style.css') ?></style>
</head><body>

<header>
    <form action="" method="get">
        <div>
            <span>file</span>
            <input type="text" name="file" value="<?= h($_GET['file'] ?? '') ?>" required />
            <label title="Remove comments from use Keywords - '// '">
                nc <input type="checkbox" name="comment_out" value="false" <?= 'false' === $commentOut ? 'checked' : null ?> />
            </label>
            <label title="Return Terminal Response">
                trmnl <input type="checkbox" name="return" value="terminal" <?= $returnType ? 'checked' : null ?> />
            </label>
        </div>
        <div>
            <button type="submit">Get</button>
            <?= $_GET ? sprintf('<a href="/" title="reset">✕</a>', $out['file'] ?? null) : null ?>
            <?= $linkToFile ? sprintf('<a href="vscode://file//%1$s" title="vscode %1$s">%1$s</a>', $linkToFile) : null ?>
        </div>
    </form>
</header>

<main>
    <pre><?= ($out['print'] ?? null) ? trim(h($out['print'])) : "\n/**\n * Nothing to use\n */" ?></pre>
    <pre><b>shell_exec(</b><?= "\n  " . h(implode("\n  ", explode(' ', implode(' ', $exec)))) . "\n" ?><b>)</b></pre>
    <?= ($execRun ?? null) ? '<pre>' . h(implode(' ', $exec)) . '</pre>' : null ?>
</main>

<footer>
    <h1>Many\Dev\Used</h1>
    <nav><?php foreach($usedOptions as $key => $short)
        $navs[] = sprintf($short === ($_GET['file'] ?? null) ? '<a>%1$s</a>' : '<a href="?file=%2$s">%1$s</a>', $key, $short);
    ?><?= implode(' / ', $navs ?? []) ?></nav>
</footer>

</body></html>