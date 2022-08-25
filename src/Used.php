<?php declare(strict_types=1);

namespace Many\Dev;

use Exception;
use Generator;
use const PHP_EOL;
use function array_keys;
use function array_merge;
use function array_merge_recursive;
use function count;
use function explode;
use function file_get_contents;
use function get_declared_classes;
use function get_declared_interfaces;
use function get_declared_traits;
use function get_defined_constants;
use function get_defined_functions;
use function implode;
use function in_array;
use function is_array;
use function is_countable;
use function is_file;
use function is_string;
use function natsort;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function trim;

/**
 * Create use statements with names of Classes, Functions and Constants used in a Class
 *
 * @author Engin Ypsilon <engin.ypsilon@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 */
class Used
{
    /**
     * @var string Info line, if all use statements are defined in script
     */
    const USE_IS_COMPLETE = 'USE_IS_COMPLETE';

    /**
     * @var array Class config
     */
    private static $config = [
        'comment_out' => true, // existing use statements
        'remove_doc_blocks' => true,
    ];

    /**
     * @var array exclude functions, classes, methods, etc.
     */
    private static $excludeNames = [
        'class'    => [],
        'function' => [],
        'constant' => [],
    ];

    /**
     * @var array templates for final "use Namespace;" Statements
     */
    private $useTemplate = [
        'class'    => 'use %1$s;%2$s',
        'function' => 'use function %1$s;%2$s',
        'constant' => 'use const %1$s;%2$s',
    ];

    /**
     * @var array Detect when class contents starts
     */
    private $classIndicatorNames = [
        'class', 'final', 'abstract', 'interface', 'trait',
    ];

    /**
     * @var string|null Filepath to parse
     */
    private $file = null;

    /**
     * @var string|null Filecontent
     */
    private $fileContent = null;


    /**
     * Get used Statements
     *
     * @param string $f filepath
     * @param array $c config
     * @return array|null
     */
    function get(string $f, array $c=[]): ?array
    {
        $this->file = $f;
        if ($c)
            $this->setConfig($c);
        return $this->buildUsed();
    }

    /**
     * Set/overwrite default configs
     *
     * @param array $c config
     * @return void
     */
    function setConfig(array $c)
    {
        if ($c['exclude'] ?? null)
            self::$excludeNames = array_merge_recursive(self::$excludeNames, $c['exclude']);
        return self::$config = array_merge(self::$config, $c);
    }

    /**
     * Get file content
     *
     * @return string|null
     */
    protected function getContent(): ?string
    {
        if (is_file($this->file) AND $c = file_get_contents($this->file))
            return $c;
        return null;
    }

    /**
     * Build Used
     *
     * @return array|null
     * @throws Exception
     */
    protected function buildUsed(): ?array
    {
        if ($c = $this->getContent()) {
            $this->fileContent = self::$config['remove_doc_blocks']
                ? $this->rmDocBlocks($c)
                : $c ;
            $this->fileContentArray = explode(PHP_EOL, $this->fileContent);
            return ['print' => $this->filterExistingUses($this->useToString([
                'class' => $this->usedClasses(),
                'constant' => $this->usedConstants(),
                'function' => $this->usedFunctions()
            ]))];
        } throw new Exception("Failed to get content\n{$this->file}");
    }

    /**
     * Comment duplicates out in print list
     *
     * @param string $n needles [class: [log,print_r], constant: [...], function: [...]]
     * @param array $c count existing
     * @param array $r temp var
     * @return string|null
     */
    protected function filterExistingUses(string $n, $count=0, array $r=[]): ?string
    {
        $doc = self::$config['comment_out'] ? '// ' : null;
        // $n contains classes, constants and functions found in haystack
        foreach($this->useGenerator($stmts = explode(PHP_EOL, $n)) as $i => $v) {
            if ($i > 0 AND str_contains($this->fileContent, $v)) {
                if ($v AND ++$count)
                    $r[] = "{$doc}{$v}";
                else $r[] = null;
            } else $r[] = $v;
        }
        if (isset($r[0]) AND is_string($r[0]))
            $r[0] = str_replace('{{DEFINED}}', (string) $count, $r[0]);
        if (count($stmts)-2 === $count)
            $r = array_merge([self::USE_IS_COMPLETE . PHP_EOL], $r);
        return $r ? implode(PHP_EOL, $r) : null;
    }

    /**
     * Printed result
     *
     * @param array $contents expects ['class': [], 'constant': [], 'function': []]
     * @param int $countUse count temp var
     * @param array $useList temp var
     * @return string
     */
    protected function useToString(array $contents, int $countUse=0, array $useList=[]): string
    {
        $labels = [0];
        foreach($this->useGenerator($contents) as $name => $arr) {
            if ($this->useTemplate[$name] ?? false) {
                $count = is_countable($arr) ? count($arr) : null;
                if ($count) {
                    $countUse += $count;
                    $labels[] = sprintf('%s(%s)', $name, $count);
                    foreach($arr as $var)
                        $useList[] = sprintf($this->useTemplate[$name], $var, null);
                }
            }
        }
        natsort($useList);
        $useKeys = implode(PHP_EOL, $useList);
        $checkExisting = $this->checkExisting($useList, $useKeys);
        $labels[0] = $checkExisting['label'] ?? null;
        return trim(sprintf('/** %2$s, total(%4$s) */%1$s%1$s%5$s%3$s'
            , PHP_EOL
            , implode(', ', $labels)
            , $checkExisting['keys'] ?? $useKeys
            , $countUse + ($checkExisting['count'] ?? 0)
            , $checkExisting['error'] ? implode(PHP_EOL, $checkExisting['error']) . PHP_EOL . PHP_EOL : null
        ));
    }

    /**
     * Check if there are duplicates in generated statements
     *
     * @param array $list generaated use statements
     * @param string $keys string representation of list
     * @param integer $count
     * @param array $error
     * @return array
     */
    protected function checkExisting(array $list, string $keys, int $count=0, array $error=[]): array
    {
        $getExisting = $this->getMissedExisting((string) $keys);
        if ($getExisting['missed'] ?? false) {
            $count = count($getExisting['missed']);
            foreach($this->useGenerator($list) as $i => $useStmt) {
                $useStmt = str_replace('use ', '', $useStmt);
                foreach($getExisting['missed'] as $existing) {
                    if (str_ends_with($existing, '\\' . $useStmt)) {
                        if (isset($list[$i])) {
                            $error[] = "// ## possible duplicate detected\n// ## {$list[$i]}";
                            unset($list[$i]);
                        }
                    }
                }
            }
            if ($error)
                $keys = implode(PHP_EOL, $list);
            $keys = implode(PHP_EOL, $getExisting['missed']) . "\n{$keys}";
        }
        return [
            'count' => $count,
            'list' => $list,
            'keys' => $keys,
            'error' => $error,
            'error_total' => $error ? count($error) : 0,
            'label' => "defined({{DEFINED}}), taken({$count})",
        ];
    }

    /**
     * Get existing use statements, that are missing in the generated ones
     *
     * @param string $checkIn generated use Statements;
     * @param array temp var
     * @return array
     */
    protected function getMissedExisting(string $checkIn, array $r=[]): array
    {
        foreach($this->useGenerator($this->fileContentArray) as $l) {
            $l = trim((string) $l);
            if (in_array(explode(' ', $l)[0], $this->classIndicatorNames))
                break;
            elseif (str_starts_with($l, 'use ')
                AND str_ends_with($l, ';')
                AND !str_contains($checkIn, $l)
            ) $r['missed'][$l] = $l;
        }
        return $r;
    }

    /**
     * Yielder
     *
     * @param array $a array to iterate through
     * @return Generator
     */
    protected function useGenerator(array $a): Generator
    {
        foreach($a as $i => $v)
            yield $i => $v;
    }

    /**
     * Get declared classes, including traits and interfaces
     *
     * @return array
     */
    protected function getDeclaredClasses(): array
    {
        return array_merge(
            get_declared_traits(),
            get_declared_interfaces(),
            get_declared_classes()
        );
    }

    /**
     * Get defined Functions
     *
     * @return array
     */
    protected function getDefinedFunctions(): array
    {
        $f = get_defined_functions(true);
        return array_merge($f['internal'] ?? [], $f['user'] ?? []);
    }

    /**
     * Get Used Classes
     *
     * @param array $r temp var
     * @return array
     */
    protected function usedClasses(array $r=[]): array
    {
        foreach($this->useGenerator($this->getDeclaredClasses()) as $n)
            if ($n = (string) $n
                AND $s = str_replace('\\', '\\\\', $n)
                AND $this->isNotExcluded($n, 'class')
                AND $this->patternMatch("[\({$s}\s|\[{$s}|!{$s}|new\s{$s}\(|\s\\\\{$s}\(|\s{$s}::]")
            ) $r[$n] = $n;
        return $r;
    }

    /**
     * Get used Constants
     *
     * @param array $r temp var
     * @return array
     */
    protected function usedConstants(array $r=[]): array
    {
        foreach($this->useGenerator(array_keys(get_defined_constants())) as $n)
            if ($n = (string) $n
                AND $this->isNotExcluded($n, 'constant')
                AND $this->patternMatch("[\s{$n}|\[{$n}|\({$n}|\/{$n}|\\\\{$n};]")
            ) $r[$n] = $n;
        return $r;
    }

    /**
     * Get Used Functions
     *
     * @param array $r temp var
     * @return array
     */
    protected function usedFunctions(array $r=[]): array
    {
        foreach($this->useGenerator($this->getDefinedFunctions()) as $n)
            if ($n = (string) $n
                AND $this->isNotExcluded($n, 'function')
                AND $this->patternMatch("[\s{$n}\(|\({$n}\(|\[{$n}\(|!{$n}\(|@{$n}\(|@\\\\{$n}\(|\\\\{$n}\(|\.\.\.{$n}\(]")
                AND !$this->patternMatch("[function\s{$n}\(|->{$n}\(]")
            ) $r[$n] = $n;
        return $r;
    }

    /**
     * Check if needle is excluded
     *
     * @param string $n needle
     * @param string $k Key name [class, constant, function]
     * @return boolean
     */
    protected function isNotExcluded(string $n, string $k): bool
    {
        return !in_array($n, self::$excludeNames[$k] ?? []);
    }

    /**
     * Check if haystack matches pattern
     *
     * @param string $p pattern
     * @return bool
     */
    protected function patternMatch(string $p): bool
    {
        return 1 === preg_match($p, $this->fileContent);
    }

    /**
     * Remove docBlocks from content before parsing it
     *
     * @param string $c content
     * @return string|null
     */
    protected function rmDocBlocks(string $c): ?string
    {
        preg_match_all("!/\*?\*.*?\*/!s", $c, $docs);
        if (($docs[0] ?? false) AND is_array($docs[0]))
            $c = str_replace($docs[0],  '', $c);
        return $this->rmSlashDocs($c);
    }

    /**
     * Remove slash comments (// ...). Not precisely, but will do the job
     *
     * @param string $c content
     * @param array $fix remove temp var
     * @return string
     */
    protected function rmSlashDocs(string $c, array $fix=[]): string
    {
        foreach($this->useGenerator(explode(PHP_EOL, $c)) as $l)
            if (str_contains($l, '// ') AND $xpl = explode('// ', $l))
                $fix[] = (($xpl[0] ?? null) AND trim($xpl[0])) ? $xpl[0] : PHP_EOL;
            else $fix[] = $l;
        return $fix ? implode(PHP_EOL, $fix) : $c;
    }

}
