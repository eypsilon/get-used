<?php error_reporting(E_ALL);

/**
 * Use Many\Dev\Used as a Webservice, temporary via PHPs
 * dev-server or as a configured vhost
 *
 * cd ~/bin/many/get-used/www/used
 * php -S localhost:8000
 * firefox http://localhost:8000
 */

// shell_exec() extension is required
if (!function_exists('shell_exec')) {
    exit('PHP shell_exec() extension is required, enable it in your php.ini<hr />' . php_ini_loaded_file());
}

/**
 * @var string $executable path to executable, Aliases don't work here out of the box, so
 * @var string $commentOut Comment existing use Keywords out
 * @var array $getConfig Used config
 * @var array $getInfo Get info
 * @var array $exec
 * @var array $getable available $_GET-keys
 * @var array $usedOptions arg options
 * @var array $usedOptSwitch switch [info | help | config ...]
 */
$executable    = '~/bin/many/get-used/GetUsed.php';
$commentOut    = 'false' !== ($_GET['comment_out'] ?? false) ? null : 'false';
$getConfig     = json_decode(shell_exec("{$executable} -c"), true)['config'] ?? [];
$getInfo       = json_decode(shell_exec("{$executable} -i"), true)['info'] ?? [];
$exec          = ["{$executable} return=json"];
$getable       = $getConfig['args'] ?? [];
$usedOptions   = $getConfig['options'] ?? [];
$usedOptSwitch = [];

/**
 * @var array prepare shell script and execute
 */
if ($_GET) {
    if ($_GET['file'] ?? null)
        $_GET['file'] = trim(preg_replace('/\s+/', ' ', $_GET['file']));
    if (!$commentOut AND isset($_GET['comment_out']))
        unset($_GET['comment_out']);
    foreach($getable as $key) {
        if ($getCmd = ($_GET[$key] ?? null) AND is_string($getCmd)) {
            if (in_array($getCmd, $usedOptions)) {
                $exec[] = $getCmd;
            } else {
                if ('class' === $key)
                    $getCmd = '"' . $getCmd . '"';
                $exec[] = sprintf('%s=%s', $key, $getCmd);
            }
        }
    }
    $execRun = shell_exec(implode(' ', $exec));
    $out = json_decode($execRun, true);
    if (!$out AND $execRun)
        $out['print'] = print_r($execRun, true);
    foreach(array_keys($usedOptions) as $opt)
        if ($out[$opt] ?? false)
            $out['print'] = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * @var array misc
 */
$printUsedClass = $out['use_for_class'] ?? null;
$printUseList = $out['print'] ?? null;
$printIncluded = $out['included'] ?? null;
$processedRequestedClass = null;

if (isset($out['response']['filename']) AND ($out['response']['filename'] ?? null) !== ($_GET['file'] ?? ''))
    $processedRequestedClass = $out['response']['filename'];
if (empty($_GET['class']))
    unset($_GET['class']);


/**
 * @var mixed Template Engin © 1999 eypsilon
 */
?><!DOCTYPE html>
<html><head>
<meta charset="utf-8" />
<title><?= $printUsedClass ?? $getInfo['lib'] ?? 'Many' ?> | local-dev-many-title</title>
<meta name="description" content="<?= $getInfo['lib'] ?? 'Many' ?> - use Keywords generator" />
<link rel="icon" sizes="16x16" href="/assets/favicon.ico" />
<style><?= file_get_contents(dirname(__FILE__) . '/assets/style.css') ?></style>
</head><body>

<header>
    <form action="" method="get">
        <div>
            <span>file</span>
            <input type="text" name="file" value="<?= htmlspecialchars($_GET['file'] ?? '') ?>" required />
            <label title="Don't comment out existing use Keywords">
                <input type="checkbox" name="comment_out" value="false" <?= 'false' === $commentOut ? 'checked' : null ?> />
            </label>
        </div>
        <?php if (!($out['file'] ?? null) AND ($_GET['class'] ?? null)) { ?>
            <div>
                <span>class</span>
                <input type="text" name="class" value="<?= htmlspecialchars($_GET['class']) ?>" />
            </div>
        <?php } ?>
        <?php if (is_array($printIncluded)) { ?>
            <div>
                <span>select</span>
                <select name="class" onchange="this.form.submit()">
                    <option value>Class</option>
                    <?php foreach($printIncluded as $select) {
                        printf('<option value="%1$s"%2$s>%1$s</option>'
                            , htmlspecialchars($select)
                            , ($select === ($_GET['class'] ?? $printUsedClass ?? null)) ? ' selected' : null
                        );
                    } ?>
                </select>
            </div>
        <?php } ?>
        <div>
            <button type="submit">Get</button>
            <?= ($out['file'] ?? false) ? sprintf('<a href="vscode://file//%1$s" title="%1$s">%1$s</a>', $out['file']) : null ?>
            <a href="/">reset</a>
        </div>
    </form>
</header>

<main>
    <pre><?= $printUseList ? trim(htmlspecialchars($printUseList)) : "\n/**\n * Nothing to use\n */" ?></pre>
    <pre><b>shell_exec(</b><?= "\n  " . htmlspecialchars(implode("\n  ", explode(' ', implode(' ', $exec)))) . "\n" ?><b>)</b></pre>
    <?= ($execRun ?? null) ? '<pre>' . htmlspecialchars(implode(" ", explode(' ', implode(' ', $exec)) )) . '</pre>' : null ?>
</main>

<footer>
    <h1><?= $getInfo['lib'] ?? 'Many' ?></h1>
    <nav><?php foreach($usedOptions as $key => $short)
        $navs[] = sprintf($short === ($_GET['file'] ?? null) ? '<a>%1$s</a>' : '<a href="?file=%2$s">%1$s</a>', $key, $short);
        print implode(' / ', $navs);
    ?></nav>
</footer>

</body></html>