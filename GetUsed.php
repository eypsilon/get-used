#!/usr/bin/php
<?php error_reporting(E_ALL);

use Many\Dev\Used;

$base = dirname(__FILE__);
require_once "{$base}/src/Used.php";
$getCnf = require_once "{$base}/GetUsed.config.php";

/**
 * @var mixed parsed arguments
 */
parse_str(implode('&', $argv), $argv);

/**
 * @var mixed If "file=" is left out in command
 */
if (!isset($argv['file']) AND $pFilename = ($_SERVER['argv'][1] ?? false) AND is_file($pFilename))
    $argv['file'] = $pFilename;

/**
 * @var mixed set args
 */
$argv['file'] = $argv['file'] ?? null;
$argv['comment_out'] = ('false' === ($argv['comment_out'] ?? false)) ? false : true;

/**
 * @var array check if shell options are called and execute if ['-c', ...]
 */
foreach($getCnf['config']['options'] as $key => $shortOpt)
    if (isset($argv[$shortOpt]) AND isset($getCnf[$key]))
        exit(json_encode([$key => $getCnf[$key]], JSON_PRETTY_PRINT) . PHP_EOL);

/**
 * @var array get use Statements
 */
try {
    $getUsed = (new Used)->get($argv['file'], $argv);
} catch(Exception $e) {
    $getUsed['print'] = $e->getMessage();
}

/**
 * @var string response
 */
if ($getUsed['print'] ?? null) {
    $r = [
        'file' => trim($argv['file']),
        'start' => $_SERVER['REQUEST_TIME_FLOAT'] ?? null,
        'end' => microtime(true),
        'print' => $getUsed['print'] ?? null,
    ];
    if ('json' === ($argv['return'] ?? null)) {
        $r = json_encode($r, JSON_PRETTY_PRINT);
    } else {
        $rPrint = [];
        foreach($r as $k => $v)
            if (!in_array($k, ['class', 'function', 'constant']))
                $rPrint[] = 'print' === $k ? "\n{$v}" : "// {$k} = {$v}";
        $r = sprintf('%1$s%2$s%1$s%1$s', PHP_EOL, implode(PHP_EOL, $rPrint));
    }
    exit($r);
} exit(sprintf('Error processing the file: %s', $argv['file']));
