<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

final class BuiltinClassList
{
    /**
     * @var array<string, true> PHP internal class/interface FQNs (with leading \)
     */
    private static array $builtins;

    public static function isBuiltin(string $fqn): bool
    {
        if (!isset(self::$builtins)) {
            self::$builtins = self::buildSet();
        }
        return isset(self::$builtins[ltrim($fqn, '\\')]);
    }

    /**
     * @return array<string, true>
     */
    private static function buildSet(): array
    {
        $classes = [
            '\DateTime', '\DateTimeImmutable', '\DateTimeInterface',
            '\DateInterval', '\DatePeriod', '\DateTimeZone',
            '\Exception', '\Error', '\ErrorException',
            '\InvalidArgumentException', '\RuntimeException',
            '\LogicException', '\DomainException', '\LengthException',
            '\OutOfBoundsException', '\OutOfRangeException',
            '\OverflowException', '\RangeException', '\UnderflowException',
            '\UnexpectedValueException', '\BadFunctionCallException',
            '\BadMethodCallException', '\TypeError', '\ParseError',
            '\ArithmeticError', '\DivisionByZeroError',
            '\ArgumentCountError', '\ValueError', '\CompileError',
            '\stdClass', '\Closure', '\Generator', '\WeakMap', '\WeakReference',
            '__PHP_Incomplete_Class', 'PHPUnit\Framework\TestCase',
            '\ArrayIterator', '\ArrayObject',
            '\DirectoryIterator', '\FilesystemIterator',
            '\GlobIterator', '\RecursiveDirectoryIterator',
            '\RecursiveIteratorIterator', '\FilterIterator',
            '\CallbackFilterIterator', '\LimitIterator',
            '\CachingIterator', '\AppendIterator',
            '\InfiniteIterator', '\EmptyIterator',
            '\IteratorIterator', '\MultipleIterator',
            '\NoRewindIterator', '\ParentIterator',
            '\RegexIterator', '\RecursiveRegexIterator',
            '\SplFileInfo', '\SplFileObject', '\SplTempFileObject',
            '\SplObjectStorage', '\SplFixedArray',
            '\SplPriorityQueue', '\SplQueue', '\SplStack',
            '\SplMinHeap', '\SplMaxHeap',
            '\SplHeap', '\SplDoublyLinkedList',
            '\Countable', '\ArrayAccess', '\Serializable',
            '\Iterator', '\IteratorAggregate', '\Traversable',
            '\Stringable', '\Throwable', '\UnitEnum', '\BackedEnum',
            '\JsonSerializable', '\OuterIterator',
            '\RecursiveIterator', '\SeekableIterator',
            '\ReflectionClass', '\ReflectionObject',
            '\ReflectionMethod', '\ReflectionFunction',
            '\ReflectionFunctionAbstract', '\ReflectionParameter',
            '\ReflectionProperty', '\ReflectionClassConstant',
            '\ReflectionExtension', '\ReflectionZendExtension',
            '\ReflectionAttribute', '\ReflectionEnum',
            '\ReflectionEnumUnitCase', '\ReflectionEnumBackedCase',
            '\ReflectionGenerator', '\ReflectionIntersectionType',
            '\ReflectionNamedType', '\ReflectionType',
            '\ReflectionUnionType', '\ReflectionFiber',
            '\Reflector', '\ReflectionException',
            '\PDO', '\PDOStatement', '\PDOException',
            '\CURLFile', '\CURLStringFile',
            '\PhpToken', '\SensitiveParameterValue',
        ];
        return array_combine(
            array_map(fn(string $c) => ltrim($c, '\\'), $classes),
            array_fill(0, count($classes), true)
        );
    }
}
