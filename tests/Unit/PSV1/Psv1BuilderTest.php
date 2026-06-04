<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\PSV1;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Renderer\PSV1\Psv1Builder;

final class Psv1BuilderTest extends TestCase
{
    private Psv1Builder $builder;

    protected function setUp(): void
    {
        $this->builder = new Psv1Builder();
    }

    public function testHeaderNoKeywords(): void
    {
        $result = $this->builder->header('class', [], 'App\MyClass');
        $this->assertSame('@class App\MyClass' . PHP_EOL, $result);
    }

    public function testHeaderWithKeywords(): void
    {
        $result = $this->builder->header('class', ['final', 'abstract'], 'App\MyClass');
        $this->assertSame('@class final abstract App\MyClass' . PHP_EOL, $result);
    }

    public function testHeaderFileType(): void
    {
        $result = $this->builder->header('file', [], 'src/functions.php');
        $this->assertSame('@file src/functions.php' . PHP_EOL, $result);
    }

    public function testHeaderEnumType(): void
    {
        $result = $this->builder->header('enum', [], 'App\Status');
        $this->assertSame('@enum App\Status' . PHP_EOL, $result);
    }

    public function testExtends(): void
    {
        $result = $this->builder->extends('App\BaseClass');
        $this->assertSame('>App\BaseClass' . PHP_EOL, $result);
    }

    public function testImplements(): void
    {
        $result = $this->builder->implements('App\Contracts\ServiceInterface');
        $this->assertSame('<App\Contracts\ServiceInterface' . PHP_EOL, $result);
    }

    public function testTraitUse(): void
    {
        $result = $this->builder->traitUse('App\LoggableTrait');
        $this->assertSame('%App\LoggableTrait' . PHP_EOL, $result);
    }

    public function testConstantPublicWithTypeAndValue(): void
    {
        $result = $this->builder->constant('DEFAULT_LIMIT', 'public', 'int', '25');
        $this->assertSame('!+DEFAULT_LIMIT:int=25' . PHP_EOL, $result);
    }

    public function testConstantPrivateNoTypeNoValue(): void
    {
        $result = $this->builder->constant('SECRET', 'private', null, null);
        $this->assertSame('!-SECRET' . PHP_EOL, $result);
    }

    public function testConstantProtectedWithType(): void
    {
        $result = $this->builder->constant('NAME', 'protected', 'string', null);
        $this->assertSame('!#NAME:string' . PHP_EOL, $result);
    }

    public function testPropertyPublic(): void
    {
        $property = [
            'name' => 'name',
            'visibility' => 'public',
            'type' => 'string',
            'defaultValue' => null,
            'isStatic' => false,
            'isReadonly' => false,
        ];
        $result = $this->builder->property($property);
        $this->assertSame('$+name:string' . PHP_EOL, $result);
    }

    public function testPropertyPrivateStatic(): void
    {
        $property = [
            'name' => 'count',
            'visibility' => 'private',
            'type' => 'int',
            'defaultValue' => '0',
            'isStatic' => true,
            'isReadonly' => false,
        ];
        $result = $this->builder->property($property);
        $this->assertSame('$-static count:int=0' . PHP_EOL, $result);
    }

    public function testPropertyProtectedReadonly(): void
    {
        $property = [
            'name' => 'vectorStore',
            'visibility' => 'protected',
            'type' => 'App\Storage\VectorStore',
            'defaultValue' => null,
            'isStatic' => false,
            'isReadonly' => true,
        ];
        $result = $this->builder->property($property);
        $this->assertSame('$#readonly vectorStore:App\Storage\VectorStore' . PHP_EOL, $result);
    }

    public function testPropertyNoType(): void
    {
        $property = [
            'name' => 'mixed',
            'visibility' => 'public',
            'type' => null,
            'defaultValue' => null,
            'isStatic' => false,
            'isReadonly' => false,
        ];
        $result = $this->builder->property($property);
        $this->assertSame('$+mixed' . PHP_EOL, $result);
    }

    public function testPropertyUnionType(): void
    {
        $property = [
            'name' => 'result',
            'visibility' => 'public',
            'type' => 'int|string|null',
            'defaultValue' => null,
            'isStatic' => false,
            'isReadonly' => false,
        ];
        $result = $this->builder->property($property);
        $this->assertSame('$+result:int|string|null' . PHP_EOL, $result);
    }

    public function testMethodPublic(): void
    {
        $method = [
            'name' => 'search',
            'visibility' => 'public',
            'isAbstract' => false,
            'isFinal' => false,
            'isStatic' => false,
        ];
        $result = $this->builder->method($method);
        $this->assertSame('.+search' . PHP_EOL, $result);
    }

    public function testMethodFinal(): void
    {
        $method = [
            'name' => 'search',
            'visibility' => 'public',
            'isAbstract' => false,
            'isFinal' => true,
            'isStatic' => false,
        ];
        $result = $this->builder->method($method);
        $this->assertSame('.+search final' . PHP_EOL, $result);
    }

    public function testMethodAbstract(): void
    {
        $method = [
            'name' => 'doSomething',
            'visibility' => 'protected',
            'isAbstract' => true,
            'isFinal' => false,
            'isStatic' => false,
        ];
        $result = $this->builder->method($method);
        $this->assertSame('.#doSomething abstract' . PHP_EOL, $result);
    }

    public function testMethodStatic(): void
    {
        $method = [
            'name' => 'merge',
            'visibility' => 'public',
            'isAbstract' => false,
            'isFinal' => false,
            'isStatic' => true,
        ];
        $result = $this->builder->method($method);
        $this->assertSame('.+merge static' . PHP_EOL, $result);
    }

    public function testMethodPrivate(): void
    {
        $method = [
            'name' => 'internal',
            'visibility' => 'private',
            'isAbstract' => false,
            'isFinal' => false,
            'isStatic' => false,
        ];
        $result = $this->builder->method($method);
        $this->assertSame('.-internal' . PHP_EOL, $result);
    }

    public function testParameter(): void
    {
        $parameter = [
            'name' => 'id',
            'type' => 'int',
            'defaultValue' => null,
            'isPassedByReference' => false,
        ];
        $result = $this->builder->parameter($parameter);
        $this->assertSame('    $id:int' . PHP_EOL, $result);
    }

    public function testParameterWithDefault(): void
    {
        $parameter = [
            'name' => 'limit',
            'type' => 'int',
            'defaultValue' => '10',
            'isPassedByReference' => false,
        ];
        $result = $this->builder->parameter($parameter);
        $this->assertSame('    $limit:int=10' . PHP_EOL, $result);
    }

    public function testParameterByReference(): void
    {
        $parameter = [
            'name' => 'source',
            'type' => 'array',
            'defaultValue' => null,
            'isPassedByReference' => true,
        ];
        $result = $this->builder->parameter($parameter);
        $this->assertSame('    &$source:array' . PHP_EOL, $result);
    }

    public function testParameterNoType(): void
    {
        $parameter = [
            'name' => 'mixed',
            'type' => null,
            'defaultValue' => null,
            'isPassedByReference' => false,
        ];
        $result = $this->builder->parameter($parameter);
        $this->assertSame('    $mixed' . PHP_EOL, $result);
    }

    public function testParameterUnionType(): void
    {
        $parameter = [
            'name' => 'status',
            'type' => 'int|string',
            'defaultValue' => null,
            'isPassedByReference' => false,
        ];
        $result = $this->builder->parameter($parameter);
        $this->assertSame('    $status:int|string' . PHP_EOL, $result);
    }

    public function testReturnType(): void
    {
        $result = $this->builder->returnType('void');
        $this->assertSame('    :void' . PHP_EOL, $result);
    }

    public function testReturnTypeNullable(): void
    {
        $result = $this->builder->returnType('?App\Entity\User');
        $this->assertSame('    :?App\Entity\User' . PHP_EOL, $result);
    }

    public function testReturnTypeNull(): void
    {
        $result = $this->builder->returnType(null);
        $this->assertSame('', $result);
    }

    public function testReturnTypeUnion(): void
    {
        $result = $this->builder->returnType('int|string');
        $this->assertSame('    :int|string' . PHP_EOL, $result);
    }

    public function testCreates(): void
    {
        $result = $this->builder->creates('App\Entity\User');
        $this->assertSame('    ^App\Entity\User' . PHP_EOL, $result);
    }

    public function testFunctionNoKeywords(): void
    {
        $function = [
            'name' => 'getUser',
            'isStatic' => false,
        ];
        $result = $this->builder->function_($function);
        $this->assertSame('.getUser' . PHP_EOL, $result);
    }

    public function testFunctionStatic(): void
    {
        $function = [
            'name' => 'helper',
            'isStatic' => true,
        ];
        $result = $this->builder->function_($function);
        $this->assertSame('.helper static' . PHP_EOL, $result);
    }

    public function testFileConstant(): void
    {
        $result = $this->builder->fileConstant('MAX_RETRIES', 'int', '3');
        $this->assertSame('!MAX_RETRIES:int=3' . PHP_EOL, $result);
    }

    public function testFileConstantNoType(): void
    {
        $result = $this->builder->fileConstant('VERSION', null, "'1.0'");
        $this->assertSame("!VERSION='1.0'" . PHP_EOL, $result);
    }

    public function testFileConstantNoValue(): void
    {
        $result = $this->builder->fileConstant('DEBUG', 'bool', null);
        $this->assertSame('!DEBUG:bool' . PHP_EOL, $result);
    }

    public function testGlobalVariable(): void
    {
        $result = $this->builder->globalVariable('debugMode');
        $this->assertSame('$debugMode' . PHP_EOL, $result);
    }

    public function testGlobalVariableWithNamespace(): void
    {
        $result = $this->builder->globalVariable('App\Logger');
        $this->assertSame('$App\Logger' . PHP_EOL, $result);
    }

    public function testEnumCaseBacked(): void
    {
        $case = ['name' => 'Active', 'value' => '1'];
        $result = $this->builder->enumCase($case, 'int');
        $this->assertSame('~Active=1' . PHP_EOL, $result);
    }

    public function testEnumCasePure(): void
    {
        $case = ['name' => 'Pending'];
        $result = $this->builder->enumCase($case, null);
        $this->assertSame('~Pending' . PHP_EOL, $result);
    }

    public function testEnumCaseBackedNoValue(): void
    {
        $case = ['name' => 'Draft'];
        $result = $this->builder->enumCase($case, 'string');
        $this->assertSame('~Draft' . PHP_EOL, $result);
    }

    public function testDeterministicAcrossMethods(): void
    {
        $first = $this->builder->header('class', ['final'], 'App\C');
        $first .= $this->builder->extends('App\B');
        $first .= $this->builder->implements('App\I');
        $first .= $this->builder->constant('X', 'public', 'int', '1');

        $second = $this->builder->header('class', ['final'], 'App\C');
        $second .= $this->builder->extends('App\B');
        $second .= $this->builder->implements('App\I');
        $second .= $this->builder->constant('X', 'public', 'int', '1');

        $this->assertSame($first, $second);
    }
}
