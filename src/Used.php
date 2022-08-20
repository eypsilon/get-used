<?php declare(strict_types=1);

namespace Many\Dev;

use Exception;
use ReflectionClass;

use function array_combine;
use function array_keys;
use function array_merge;
use function array_merge_recursive;
use function array_pop;
use function array_replace;
use function array_reverse;
use function array_values;
use function count;
use function defined;
use function dirname;
use function explode;
use function file_get_contents;
use function get_declared_classes;
use function get_declared_interfaces;
use function get_declared_traits;
use function get_defined_constants;
use function get_defined_functions;
use function get_included_files;
use function implode;
use function in_array;
use function is_array;
use function is_countable;
use function is_file;
use function is_string;
use function json_encode;
use function method_exists;
use function natsort;
use function realpath;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strpos;
use function trim;

use const PHP_EOL;
use const JSON_UNESCAPED_SLASHES;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

/**
 * Get use-keywords for Constants, Functions and Constants used in a namespaed Class
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
        'comment_out_existing' => true,
    ];

    /**
     * @var array exclude functions, classes, methods, etc.
     */
    private static $ecludeNames = [
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
     * @var array parameter default Placeholder
     */
    private $parDefPlaceholder = [
        'any'      => 'null',
        'array'    => '[]',
        'bool'     => 'false',
        'callable' => 'object',
        'double'   => 7.6,
        'float'    => 5.4,
        'int'      => 3,
        'integer'  => 2,
        'null'     => 'null',
        'string'   => 'null',
    ];

    /**
     * @var array declared class
     */
    private static $getDeclaredClassList = [];

    /**
     * @var array declared class
     */
    private static $getNamespaceTree = [];


    /**
     * Searches in a given Class for used classes, functions & consts to build a list with use statements
     *
     * @param string|object $forClass
     * @param array temp var
     * @return array
     */
    function getClassUseList($forClass, array $r=[]): array
    {
        try {
            $rflctr = new ReflectionClass($forClass);
            $filename = $rflctr->getFileName();
        } catch(Exception $e) {
            $filename = null;
        }
        if ($filename AND is_file($filename)) {
            $getClassContent = file_get_contents($filename);
            $tmpClassContent = $getClassContent;
            if ($rflctr->getDocComment())
                $getClassContent = str_replace($rflctr->getDocComment(), '', $getClassContent);
            foreach($rflctr->getMethods() as $i => $v) {
                $getMethDoc = $rflctr->getMethod($v->getName())->getDocComment();
                if ($getMethDoc)
                    $getClassContent = str_replace($getMethDoc, '', $getClassContent);
            }
            foreach($rflctr->getProperties() as $i => $v) {
                $getMethDoc = $rflctr->getProperty($v->getName())->getDocComment();
                if ($getMethDoc)
                    $getClassContent = str_replace("$getMethDoc", '', $getClassContent);
            }
            $r = [
                'filename'     => $rflctr->getFileName(),
                'class'        => $this->getUsedClasses($getClassContent),
                'function'     => $this->getUsedFunctions($getClassContent),
                'constant'     => $this->getUsedConstants($getClassContent),
                'file_content' => trim(str_replace('<?php ', '', $tmpClassContent)),
                'reflection'   => $rflctr->hasMethod('__toString') ? trim($rflctr->__toString()) : null,
            ];
            $r['methods'] = sprintf(
                '/** Copy&Paster */%3$s%3$suse %1$s;%3$s$var = new \\%1$s;%3$s%3$s%2$s'
                , $forClass
                , $this->getMethodList($rflctr)
                , PHP_EOL
            );
            $r['methods'] = trim($r['methods']);
            $r['print'] = $this->filterExistingUses($getClassContent, (string) $this->toString($r));
        }
        $r['for_class'] = $forClass;
        $r['namespace_tree'] = $this->getNamespaceTree();
        return $r;
    }


    /**
     * Get available namespace trees
     *
     * @param array $r temp var
     * @return array
     */
    function getNamespaceTree(array $r=[]): array
    {
        foreach(self::$getNamespaceTree as $namespace) {
            $expl = explode('\\', $namespace);
            $r[$expl[0]] = $expl[0];
        }
        return $r;
    }


    /**
     * Get Classes for specific Namespace
     *
     * @param string $namespace
     * @param array $r temp var
     * @return array
     */
    function getClassesForNamespace(string $namespace, array $r=[]): array
    {
        $getDeclaredClasses = $this->getDeclaredClassList(true, $namespace);
        $getDeclaredAbstracts = $this->requireAbstracts($namespace);
        $getDeclaredClasses = array_merge($getDeclaredClasses, $getDeclaredAbstracts);
        foreach($getDeclaredClasses as $class) {
            if (str_starts_with($class, $namespace) AND !in_array($class, self::$ecludeNames['class']))
                $r[] = $class;
        }
        natsort($r);
        $namespceTree = $this->getNamespaceTree();
        $composerClassMap = static::getComposerClassMap();
        if ($composerClassMap)
            $composerClassMap = array_values($composerClassMap);
        return [
            'classmap_use' => $this->buildUseStatementsForClassMap($namespceTree, $composerClassMap),
            'namespace' => $namespace,
            'namespace_tree' => $namespceTree,
            'class_map' => array_values($r),
            'use' => $this->buildUseListStr($r),
            'use_nested' => $this->buildUseNestedStr($namespace, $r),
        ];
    }


    /**
     * Get included files
     *
     * @return string|null
     */
    function getIncludedFiles(): ?string
    {
        $getIncFiles = get_included_files();
        natsort($getIncFiles);
        $r = json_encode(array_values($getIncFiles), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $r = str_replace(realpath('./../'), '', $r);
        return $r ?? null;
    }

    /**
     * Set/overwrite default configs
     *
     * @param array $v
     * @return void
     */
    static function setConfig(array $v)
    {
        if ($v['exclude'] ?? null)
            self::$ecludeNames = array_merge_recursive(self::$ecludeNames, $v['exclude']);
        return self::$config = array_replace(self::$config, $v);
    }

    /**
     * Get Vendor Autoload path for a given File. Searches '/vendor/autoload.php'
     * in the parent directories of the File iteratively, max depth 10 parents
     *
     * @param string $file path to file
     * @param string $autoloadFile autoloader of interest
     * @return string|null
     */
    static function getAutoloadPath(string $file, string $autoloadFile='/vendor/autoload.php'): ?string
    {
        $expl = explode('/', $file);
        foreach($expl as $i => $f)
            if ($i < 10) {
                if (is_file($testFile = implode('/', $expl) . $autoloadFile))
                    return $testFile;
                array_pop($expl);
            }
        return null;
    }


    /**
     * Get root path from this class
     *
     * @return array
     */
    static function getRootPath(): array
    {
        return [
            'dir' => dirname(dirname(dirname(__DIR__))),
            'path' => realpath('../'),
        ];
    }


    /**
     * Get Composer generated Class map
     *
     * @return array
     */
    static function getComposerClassMap(): array
    {
        $usePath = static::getRootPath();
        $loadMap = function($usePath, $r=[]) {
            $getClassMap = "{$usePath}/vendor/composer/autoload_classmap.php";
            if (is_file($getClassMap)) {
                $getClassMapRe = require $getClassMap;
                if (is_array($getClassMapRe)) {
                    $getClassMapRe = array_combine(
                        array_keys($getClassMapRe),
                        array_keys($getClassMapRe)
                    );
                }
                $r = $getClassMapRe;
            }
            return $r;
        };
        $combine = array_merge($loadMap($usePath['dir']), $loadMap($usePath['path']));
        natsort($combine);
        return array_values($combine);
    }


    /**
     * Search needle in Path iteratively, from left to write
     *
     * @param string $haystack
     * @param string $needle
     * @param string $split
     * @return string|null
     */
    static function searchPathLeftToRight(string $h, string $n, string $s='/'): ?string
    {
        $ts = array_reverse(explode($s, $n));
        foreach($ts as $nn) {
            $tr = array_reverse($ts);
            if (str_contains($h, implode($s, $tr)))
                return $n;
            array_pop($ts);
        }
        return null;
    }


    /**
     * Remove duplicates from print list
     *
     * @param string $haystack
     * @param string $needles
     * @param array $r temp var
     * @return string|null
     */
    protected function filterExistingUses(string $haystack, string $needles, array $r=[]): ?string
    {
        $coe = self::$config['comment_out_existing'] ? '// ' : null;
        foreach(explode(PHP_EOL, $needles) as $i => $n) {
            if (str_contains($haystack, $n))
                $r[] = $n ? "{$coe}{$n}" . ($i == 0 ? PHP_EOL : null) : null;
            else $r[] = $n;
        }
        return $r ? implode(PHP_EOL, $r) : null;
    }


    /**
     * Printed result
     *
     * @param array $res
     * @param array $r temp var
     * @param string $s temp var
     * @return string|null
     */
    protected function toString(array $res, array $r=[], string $s=null): ?string
    {
        $tpl = $this->useTemplate;
        foreach($res as $name => $arr) {
            if ($tpl[$name] ?? false) {
                $count = is_countable($arr) ? count($arr) : null;
                if ($count) {
                    $r[] = sprintf('%s(%s)', $name, $count);
                    foreach($arr as $var)
                        $s .= sprintf($tpl[$name], $var, PHP_EOL);
                        $s .= PHP_EOL;
                }
            }
        }
        $getExisting = $this->getExistingUses($res['file_content'], (string) $s);
        if ($getExisting) {
            $r = array_merge([sprintf('existing(%s)', count($getExisting))], $r);
            $s = implode(PHP_EOL, $getExisting) . "\n$s";
        }
        return !$r ? null : trim(sprintf('/** %1$s */%3$s%3$s%2$s', implode(', ', $r), $s, PHP_EOL));
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
        foreach(explode(PHP_EOL, $content) as $l) {
            $l = $l ? trim($l) : $l;
            if (str_starts_with($l, 'use ') AND str_ends_with($l, ';') AND !str_contains($checkIn, $l))
                $r[] = $l;
        }
        return $r;
    }


    /**
     * Get metod list for selected Class
     *
     * @param ReflectionClass $obj
     * @return mixed
     */
    protected function getMethodList(ReflectionClass $obj)
    {
        $buildList = function(array $arr, array $r=[], array $reArr=[]) use($obj) {
            foreach($arr as $isMethod) {
                if (!in_array($isMethod->name, self::$ecludeNames['method'])) {
                    $initClassWrap  = $isMethod->class;
                    $initGetMethod  = $obj->getMethod($isMethod->name);
                    $methodIsStatic = $initGetMethod->isStatic();
                    $separator      = $methodIsStatic ? '::' : '->';

                    if ($methodIsStatic)
                        $initClassWrap = sprintf('\\%1$s', $initClassWrap);
                    elseif (!$methodIsStatic)
                        $initClassWrap = sprintf('(new \\%1$s)', $initClassWrap);

                    if (!$initGetMethod->isPublic())
                        $initClassWrap = sprintf('// (not public) %s', $initClassWrap);

                    $fullName = $initClassWrap . $separator . $isMethod->name;
                    $r[$fullName] = [];

                    foreach($initGetMethod->getParameters() as $param) {
                        $optional = $param->isOptional();
                        $setGetType = $param->getType();
                        $getPlaceholder = $getTypeOf = null;
                        if ($setGetType AND method_exists($setGetType, 'getName')) {
                            $getTypeOf = $setGetType->getName();
                            if ($getTypeOf AND ($this->parDefPlaceholder[$getTypeOf] ?? false))
                                $getPlaceholder = trim($this->parDefPlaceholder[$getTypeOf].'');
                        }
                        $fullParam = sprintf('$%2$s%3$s'
                            , $getTypeOf
                            , $param->name
                            , ($optional AND $getPlaceholder) ? "={$getPlaceholder}" : null
                        );
                        $r[$fullName][] = $fullParam;
                        $r[$fullName]['type'][] = $getTypeOf;
                    }

                    if (!isset($reArr[$fullName])) {
                        if ($r[$fullName]['type'] ?? false) {
                            $getType = implode(', ', $r[$fullName]['type']);
                            $getType = rtrim($getType, ', ');
                            unset($r[$fullName]['type']);
                        } else $getType = null;
                        $reArr[$fullName] = sprintf('%1$s(%2$s);%3$s'
                            , $fullName
                            , implode(', ', $r[$fullName])
                            , $getType ? " // ({$getType})" : null
                        );
                    }
                }
            }
            return implode(PHP_EOL, array_values($reArr));
        };
        return $buildList($obj->getMethods());
    }

    /**
     * include Abstract classes like traits, interfaces etc.
     *
     * @param string|null $namespace
     * @param array tmp var
     * @return array
     */
    protected function requireAbstracts(string $namespace=null, array $r=[]): array
    {
        $getDeclaredAbstracts = array_merge(
            get_declared_traits(),
            get_declared_interfaces()
        );
        foreach($getDeclaredAbstracts as $abstr) {
            if (str_starts_with($abstr, $namespace)) {
                $r[] = $abstr;
                try {
                    new ReflectionClass($abstr);
                } catch(Exception $e) {}
            }
        }
        return $r;
    }


    /**
     * Builds "use Namespeced\Class\Statements;" for all
     * available classes in composers classmap
     *
     * @param array $tree
     * @param array $classmap
     * @return array
     */
    protected function buildUseStatementsForClassMap(array $tree, array $classmap): array
    {
        $useList = $useClasses = [];
        foreach($tree as $key)
            foreach($classmap as $class)
                if (str_starts_with($class, $key))
                    $useClasses[$key][] = $class;
        foreach($useClasses as $namespace => $classlist)
            $useList[$namespace] =
                $this->buildUseNestedStr($namespace, $classlist)
                . PHP_EOL . PHP_EOL .
                $this->buildUseListStr($classlist);
        return $useList;
    }


    /**
     * build "use Namespaced\List\Statement;"
     *
     * @param array $classes
     * @return string
     */
    protected function buildUseListStr(array $classes): string
    {
        return 'use ' . implode(';' . PHP_EOL . 'use ', $classes) . ';';
    }


    /**
     * Create "use Namespaced\{Nested\Statement,Chained\Statements};"
     *
     * @param string $namespace
     * @param array $classes
     * @return string
     */
    protected function buildUseNestedStr(string $namespace, array $classes): string
    {
        return sprintf('/** use %1$s(%3$s) */%4$s%4$suse %1$s\{%4$s    %2$s%4$s};'
            /*1*/, $namespace
            /*2*/, str_replace("{$namespace}\\", '', implode(",\n    ", $classes))
            /*3*/, count($classes)
            /*4*/, PHP_EOL
        );
    }


    /**
     * Get declared classes list, requires all classes from namespace
     *
     * @param boolean $getAll
     * @param string|null $namespace
     * @return array
     */
    protected function getDeclaredClassList(bool $getAll=false, string $namespace=null): array
    {
        if (self::$getDeclaredClassList)
            return self::$getDeclaredClassList;
        if ($getAll) {
            $res = get_declared_classes();
            $autoloaderClassName = '';
            foreach ($res as $className) {
                if (strpos($className, 'ComposerAutoloaderInit') === 0) {
                    $autoloaderClassName = $className;
                    break;
                }
            }
            $classLoader = $autoloaderClassName::getLoader();
            self::$getNamespaceTree = array_keys($classLoader->getClassMap());
            foreach(self::$getNamespaceTree as $mappedNamespace) {
                if (str_starts_with($mappedNamespace, $namespace)) {
                    try {
                        new ReflectionClass($mappedNamespace);
                    } catch(Exception $e) {}
                }
            }
        }
        return self::$getDeclaredClassList = self::$getDeclaredClassList
            ? self::$getDeclaredClassList : get_declared_classes();
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
        $this->getDeclaredClassList();
        $checkIfClass = function($cls) use($haystack) {
            if (str_contains($haystack, $cls)
                AND (
                    str_contains($haystack, "{$cls}(")
                    OR str_contains($haystack, "{$cls};")
                    OR str_contains($haystack, "({$cls} ")
                )
                AND !str_contains($haystack, "_{$cls}")
                OR str_contains($haystack, "[{$cls}")
                OR str_contains($haystack, "!{$cls}")
                OR str_contains($haystack, "\\{$cls}")
                OR str_contains($haystack, "{$cls}::")
            ) {
                if (str_contains($haystack, " {$cls}")
                    OR str_contains($haystack, "[{$cls}")
                    OR str_contains($haystack, "({$cls}")
                ) return $cls;
            }
            return false;
        };
        foreach(self::$getDeclaredClassList as $needle) {
            if (!in_array($needle, self::$ecludeNames['class'])) {
                if ($cls = $checkIfClass($needle)) {
                    $r[] = $cls;
                }
            }
        }
        natsort($r);
        return array_values($r);
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
            if (str_contains($haystack, " {$fn}(")
                OR str_contains($haystack, "[{$fn}(")
                OR str_contains($haystack, "!{$fn}(")
                OR str_contains($haystack, "({$fn}(")
                OR str_contains($haystack, "={$fn}(")
                OR str_contains($haystack, "/{$fn}(")
                OR str_contains($haystack, "\\{$fn}(")
                OR str_contains($haystack, "@{$fn}(")
                OR str_contains($haystack, "@\\{$fn}(")
                OR str_contains($haystack, "...{$fn}(")
            )
                if (str_contains($haystack, "{$fn}")
                    AND !str_contains($haystack, "function {$fn}(")
                    AND !str_contains($haystack, "class {$fn}")
                    AND !str_contains($haystack, "->{$fn}(")
                ) return $fn;
            return false;
        };
        $needles = get_defined_functions(true);
        $needles = $needles['internal'] ?? $needles;
        foreach($needles as $needle) {
            if (is_string($needle)
                AND $fn = $checkIfFuntion($needle)
                AND !in_array($needle, self::$ecludeNames['function'])
            ) $r[] = $fn;
        }
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
            if (str_contains($haystack, $c) AND !in_array($c, self::$ecludeNames['constant']))
                if (defined($c)) {
                    if (str_contains($haystack, " {$c}")
                        AND (
                            str_contains($haystack, "{$c}")
                            OR str_contains($haystack, "[{$c}")
                            OR str_contains($haystack, "({$c}")
                            OR str_contains($haystack, "!{$c}")
                            OR str_contains($haystack, "/{$c}")
                            OR str_contains($haystack, "\\{$c}")
                            OR str_contains($haystack, "*{$c}")
                        )
                    ) $r[] = $c;
                }
        return $r;
    }

}
