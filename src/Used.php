<?php declare(strict_types=1);

namespace Many\Dev;

use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_merge;
use function array_merge_recursive;
use function array_values;
use function count;
use function defined;
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
use function ksort;
use function natsort;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function trim;
use const PHP_EOL;

/**
 * Get use-keywords for Classes, Functions and Constants used in a Class
 *
 * @author Engin Ypsilon <engin.ypsilon@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 */
class Used
{

    /**
     * @var array Class config
     */
    private static $config = [
        'comment_out' => true, // existing
    ];

    /**
     * @var array exclude functions, classes, methods, etc.
     */
    private static $excludeNames = [
        'class'    => [],
        'function' => [],
        'constant' => [],
        'method'   => ['__construct'],
    ];

    /**
     * @var array templates for finale "use Statements;"
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
     * Get used Statements from plain file, avoiding the use of Reflection and Namespaces
     *
     * @param string $f file
     * @param array $c config
     * @return array|null
     */
    function get(string $f, array $c=[]): ?array
    {
        if ($c)
            $this->setConfig($c);
        return $this->buildUsed($f);
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
     * Get Used
     *
     * @param string $f file
     * @return array|null
     */
    protected function buildUsed(string $f): ?array
    {
        if (is_file($f) AND $c = file_get_contents($f)) {
            $c = $this->removeDocBlocks($c);
            $r = [
                'file' => $f,
                'class' => $this->getUsedClasses($c),
                'function' => $this->getUsedFunctions($c),
                'constant' => $this->getUsedConstants($c),
                'file_content' => trim(str_replace('<?php ', '', $c)),
            ];
            $r['print'] = $this->filterExistingUses($c, (string) $this->useToString($r));
        }
        return $r ?? null;
    }

    /**
     * Comment duplicates out in print list
     *
     * @param string $h haystack
     * @param string $n needles
     * @param array $r temp var
     * @return string|null
     */
    protected function filterExistingUses(string $h, string $n, array $r=[]): ?string
    {
        $doc = self::$config['comment_out'] ? '// ' : null;
        $count = 0;
        foreach(explode(PHP_EOL, $n) as $i => $n) {
            if ($i > 1 AND str_contains($h, $n)) {
                if ($n)
                    ++$count;
                $r[] = $n ? "{$doc}{$n}" . ($i == 0 ? PHP_EOL : null) : null;
            } else $r[] = $n;
        }
        if (isset($r[0]) AND is_string($r[0]))
            $r[0] = str_replace('{{ALREADY_DEFINED}}', (string) $count, $r[0]);
        return $r ? implode(PHP_EOL, $r) : null;
    }

    /**
     * Printed result
     *
     * @param array $res
     * @param array $r temp var
     * @param string $s temp var
     * @param int $totalMatches
     * @return string|null
     */
    protected function useToString(array $res, array $r=[], string $s=null, int $totalMatches=0): ?string
    {
        $tpl = $this->useTemplate;
        $rs = $err = [];
        foreach($res as $name => $arr) {
            if ($tpl[$name] ?? false) {
                $count = is_countable($arr) ? count($arr) : null;
                if ($count) {
                    $totalMatches += $count;
                    $r[$count+1] = sprintf('%s(%s)', $name, $count);
                    foreach($arr as $var)
                        $rs[] = sprintf($tpl[$name], $var, null);
                }
            }
        }

        $s = implode(PHP_EOL, $rs);
        $getExisting = $this->getExistingUses($res['file_content'], (string) $s);

        // check for possbible duplicates, prefers already existing ones found on top of file
        if ($getExisting['missed'] ?? false) {
            foreach($rs as $i => $useStmt) {
                $useStmt = str_replace('use ', '', $useStmt);
                foreach($getExisting['missed'] as $existing) {
                    if (str_ends_with($existing, '\\' . $useStmt)) {
                        if (isset($rs[$i])) {
                            $err[] = "// ## possible duplicate detected\n// ## {$rs[$i]}";
                            unset($rs[$i]);
                        }
                    }
                }
            }
            $s = implode(PHP_EOL, $rs);
        }

        if ($getExisting['use_defined'] ?? false)
            $r['a'] = sprintf('lines(%s-%s), defined({{ALREADY_DEFINED}})'
                , $getExisting['use_first_line']
                , $getExisting['use_last_line']
            );

        if ($getExisting['missed'] ?? false) {
            $r['b'] = sprintf('taken(%s)', $missedNamesTotal = count($getExisting['missed']));
            $s = implode(PHP_EOL, $getExisting['missed']) . "\n$s";
        }

        if ($err)
            $s = implode(PHP_EOL, $err) . "\n\n$s";

        ksort($r);
        return !$r ? null : trim(sprintf(
            '/** %1$s, total(%4$s) */%3$s%3$s%2$s'
            , implode(', ', $r)
            , $s
            , PHP_EOL
            , $totalMatches + ($missedNamesTotal ?? 0)
        ));
    }

    /**
     * Get existing use Keywords, that are missing in the generated ones
     *
     * @param string $content filecontent
     * @param string $checkIn generated use Keywords;
     * @param array temp var
     * @return array
     */
    protected function getExistingUses(string $content, string $checkIn, array $r=[]): array
    {
        $classStartsHere = function($l) {
            foreach($this->classIndicatorNames as $n)
                if (str_starts_with($l, $n))
                    return true;
            return false;
        };
        foreach(explode(PHP_EOL, $content) as $i => $l) {
            $l = $l ? trim($l) : $l;
            if ($classStartsHere($l))
                break;
            elseif (str_starts_with($l, 'use ') AND str_ends_with($l, ';')) {
                $r['use_defined'][$i+1] = $l;
                if (!str_contains($checkIn, $l))
                    $r['missed'][] = $l;
            }
        }
        $r['use_first_line'] = array_key_first($r['use_defined'] ?? []);
        $r['use_last_line'] = array_key_last($r['use_defined'] ?? []);
        return $r;
    }

    /**
     * Check if haystack don't contains any of pattern
     *
     * @param string $haystack
     * @param array $p pattern
     * @return bool
     */
    protected function checkIfNotContainsPattern(string $h, array $p): bool
    {
        return !preg_match('[' . implode('|', $p) . ']', $h) == true;
    }

    /**
     * Check if haystack contains any of pattern
     *
     * @param string $haystack
     * @param array $p pattern
     * @return
     */
    protected function checkIfContainsPattern(string $h, array $p): bool
    {
        return preg_match('[' . implode('|', $p) . ']', $h) == true;
    }

    /**
     * Remove docBlocks from content before parsing it
     *
     * @param string $c content
     * @return string|null
     */
    protected function removeDocBlocks(string $c): ?string
    {
        preg_match_all("!/\*\*.*?\*/!s", $c, $docs);
        if (($docs[0] ?? false) AND is_array($docs[0]))
            $c = str_replace($docs[0], PHP_EOL, $c);
        return $this->removeSlashDocs($c);
    }

    /**
     * Remove slash comments (// ...). Not precisely, but will do the job
     *
     * @param string $c content
     * @param array $fix remove
     * @return string|null
     */
    protected function removeSlashDocs(string $c, array $fix=[]): ?string
    {
        foreach(explode(PHP_EOL, $c) as $l)
            if (str_contains($l, '// ')) {
                $xpl = explode('// ', $l);
                if (($xpl[0] ?? null) AND trim($xpl[0]))
                    $fix[] = $xpl[0];
                else $fix[] = null;
            } else $fix[] = $l;
        return implode(PHP_EOL, $fix);
    }

    /**
     * Get declared classes, including traits and interfaces
     *
     * @return array
     */
    protected function getDeclaredClassList(): array
    {
        return array_merge(
            get_declared_traits(),
            get_declared_interfaces(),
            get_declared_classes()
        );
    }

    /**
     * Get Used Classes
     *
     * @param string $haystack
     * @param array $r temp var
     * @return array
     */
    protected function getUsedClasses(string $haystack, array $r=[]): array
    {
        $checkIfClass = function($cls) use($haystack) {
            $c = str_replace('\\', '\\\\', $cls);
            return $this->checkIfContainsPattern($haystack, [
                "\({$c}\s",
                "\[{$c}",
                "!{$c}",
                "new\s{$c}\(",
                "new\s\\\\{$c}",
                "\s\\\\{$c}",
                "{$c}::",
            ]) ? $cls : false;
        };
        foreach($this->getDeclaredClassList() as $needle)
            if (!in_array($needle, self::$excludeNames['class']))
                if ($cls = $checkIfClass($needle))
                    $r[] = $cls;
        natsort($r);
        return array_values($r);
    }

    /**
     * Search in content for used Constants
     *
     * @param string $haystack
     * @param array $r temp var
     * @return array
     */
    protected function getUsedConstants(string $haystack, array $r=[]): array
    {
        foreach(array_keys(get_defined_constants()) as $c)
            if (str_contains($haystack, $c) AND !in_array($c, self::$excludeNames['constant']))
                if (defined($c))
                    if ($this->checkIfContainsPattern($haystack, [
                        "\s{$c}",
                        "\[{$c}",
                        "\({$c}",
                        "!{$c}",
                        "\/{$c}",
                        "\\\\{$c}",
                        "\*{$c}",
                    ])) $r[] = $c;
        return $r;
    }

    /**
     * Get Used PHP internal Functions
     *
     * @param string $haystack
     * @param array $r temp var
     * @return array
     */
    protected function getUsedFunctions(string $haystack, array $r=[]): array
    {
        $checkIfFuntion = function(string $fn) use($haystack) {
            $pattern = [
                "\s{$fn}\(",
                "\[{$fn}\(",
                "\!{$fn}\(",
                "\({$fn}\(",
                "={$fn}\(",
                "\/{$fn}\(",
                "\\\\{$fn}\(",
                "@{$fn}\(",
                "@\\\\{$fn}\(",
                "\.\.\.{$fn}\(",
            ];
            $antiPattern = [
                "function\s{$fn}\(",
                "class\s{$fn}",
                "->{$fn}\("
            ];
            if ($this->checkIfContainsPattern($haystack, $pattern))
                if ($this->checkIfNotContainsPattern($haystack, $antiPattern))
                    return $fn;
            return false;
        };
        $needles = get_defined_functions(true);
        if (isset($needles['internal']) AND isset($needles['user']))
            $needles = array_merge($needles['internal'], $needles['user']);
        foreach($needles as $needle) {
            if (is_string($needle)
                AND $checkIfFuntion($needle)
                AND !in_array($needle, self::$excludeNames['function'])
            ) $r[] = $needle;
        }
        natsort($r);
        return array_values($r);
    }

}
