#!/usr/bin/php
<?php error_reporting(E_ALL);

use Many\Dev\Used;

$base = dirname(__FILE__);
require_once "{$base}/src/Used.php";
$getCnf = require_once "{$base}/GetUsed.config.php";
if (is_file($usrCnf = "{$base}/user.config.php"))
    $getCnf = array_replace_recursive($getCnf, require_once $usrCnf);

/** @var mixed parsed arguments */
parse_str(implode('&', $argv), $argv);

/**
 * @var array check if shell options are called and execute if ['-h', ...]
 */
foreach($getCnf['config']['options'] as $key => $shortOpt)
    if (isset($argv[$shortOpt]) AND isset($getCnf[$key]))
        exit(json_encode([$key => $getCnf[$key]], JSON_PRETTY_PRINT) . PHP_EOL);

/**
 * @var mixed If "file=" is left out in command
 */
if (!isset($argv['file']) AND $pFilename = ($_SERVER['argv'][1] ?? false))
    if (file_exists($pFilename))
        $argv['file'] = $pFilename;

/**
 * @var mixed sets & args
 */
$autoloadFile = '/vendor/autoload.php';
$projectDir = $argv['project_dir'] ?? null;
$argv['include'] = $argv['include'] ?? null;
$argv['file'] = $argv['file'] ?? null ? "{$projectDir}{$argv['file']}" : null;
$argv['comment_out'] = ('false' === ($argv['comment_out'] ?? false)) ? false : true;
$classes = get_declared_classes();
$isIncluded = [];

/**
 * @var array Set config
 */
Used::setConfig(['comment_out_existing' => $argv['comment_out'] ? true : false]);

/**
 * @var mixed Search path to "/vendor/autoload.php" in the file environment iteratively. Sets "/vendor/autoload.php" as first file to include
 */
if ($argv['file'] AND $getAutoloader = Used::getAutoloadPath($argv['file']))
    $argv['include'] = "{$getAutoloader},{$argv['include']},{$argv['file']}";

/**
 * @var mixed Set all files to include
 */
if ($argv['include'] ?? null)
    $argv['include'] = trim($argv['include'], ',') . ",{$argv['file']}";
else $argv['include'] = $argv['file'];

/**
 * @var mixed include all
 */
if ($includeAll = ($argv['include'] ?? null))
    foreach(explode(',', $includeAll) as $inc)
        if ($inc AND !in_array($inc, $isIncluded) AND is_file($inc) AND $isIncluded[] = $inc)
            include_once $inc;

/**
 * @var mixed check included files
 */
if ($isIncluded AND $diff = array_diff(get_declared_classes(), $classes))
    $class = end($diff);
else $diff = false;

/**
 * @var string get Used-Statements for a specific class through the "class"-parameter
 */
$class = $argv['class'] ?? $class ?? null;

/**
 * @var mixed Set the right class to load, if nothing is specified
 */
if ($diff AND !($argv['class'] ?? null)) {
    foreach($diff as $id => $namespace) {
        $namespace = str_replace('\\', '/', $namespace);
        if (str_contains($argv['file'], $namespace)) {
            $class = $diff[$id];
            break;
        }
    }
}

/**
 * @var mixed Fix class if the given class still don't match with available
 */
if ($diff AND !in_array($class, $diff)) {
    foreach($diff as $id => $namespace) {
        $namespace = str_replace('\\', '/', $namespace);
        if ($checkIterate = Used::searchPathLeftToRight($argv['file'], $namespace, '/')) {
            $class = str_replace('/', '\\', $checkIterate);
            break;
        }
    }
}

/**
 * @var string print Used-Statements or Error
 */
if ($class) {
    if ($getUsed = ((new Used)->getClassUseList($class))) {
        $r = [
            'file' => $getUsed['filename'] ?? $argv['file'] ?? null,
            'use_for_class' => $class,
            'start' => $_SERVER['REQUEST_TIME_FLOAT'] ?? null,
            'end' => microtime(true),
            'print' => $getUsed['print'] ?? null,
        ];
        if ('json' === ($argv['return'] ?? null)) {
            $r = json_encode(array_merge($r, [
                'included' => $diff,
                'response' => $getUsed,
            ]), JSON_PRETTY_PRINT);
        } else {
            $rPrint = [];
            foreach($r as $k => $v)
                $rPrint[] = 'print' === $k ? "\n{$v}" : "// {$k} = {$v}";
            $r = PHP_EOL . implode(PHP_EOL, $rPrint) . PHP_EOL . PHP_EOL;
        }
        exit($r);
    } else exit("Error processing the Class: {$class}");
}

// if script somehow ends up here, exit anyway
exit("No file defined, set ?file=/path/to/file.php\n");
