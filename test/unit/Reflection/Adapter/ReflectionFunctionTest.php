<?php

namespace BetterReflectionTest\Reflection\Adapter;

use BetterReflection\Reflection\Adapter\Exception\NotImplemented;
use ReflectionClass as CoreReflectionClass;
use ReflectionFunction as CoreReflectionFunction;
use BetterReflection\Reflection\Adapter\ReflectionFunction as ReflectionFunctionAdapter;
use BetterReflection\Reflection\ReflectionFunction as BetterReflectionFunction;
use BetterReflection\Reflection\ReflectionParameter as BetterReflectionParameter;

/**
 * @covers \BetterReflection\Reflection\Adapter\ReflectionFunction
 */
class ReflectionFunctionTest extends \PHPUnit_Framework_TestCase
{
    public function coreReflectionMethodNamesProvider()
    {
        $methods = get_class_methods(CoreReflectionFunction::class);
        return array_combine($methods, array_map(function ($i) { return [$i]; }, $methods));
    }

    /**
     * @param string $methodName
     * @dataProvider coreReflectionMethodNamesProvider
     */
    public function testCoreReflectionMethods($methodName)
    {
        $reflectionFunctionAdapterReflection = new CoreReflectionClass(ReflectionFunctionAdapter::class);
        $this->assertTrue($reflectionFunctionAdapterReflection->hasMethod($methodName));
    }

    public function methodExpectationProvider()
    {
        $mockParameter = $this->getMockBuilder(BetterReflectionParameter::class)
            ->disableOriginalConstructor()
            ->getMock();

        return [
            // Inherited
            ['__toString', null, '', []],
            ['inNamespace', null, true, []],
            ['isClosure', null, true, []],
            ['isDeprecated', null, true, []],
            ['isInternal', null, true, []],
            ['isUserDefined', null, true, []],
            ['getClosureThis', NotImplemented::class, null, []],
            ['getClosureScopeClass', NotImplemented::class, null, []],
            ['getDocComment', null, '', []],
            ['getStartLine', null, 123, []],
            ['getEndLine', null, 123, []],
            ['getExtension', NotImplemented::class, null, []],
            ['getExtensionName', NotImplemented::class, null, []],
            ['getFileName', null, '', []],
            ['getName', null, '', []],
            ['getNamespaceName', null, '', []],
            ['getNumberOfParameters', null, 123, []],
            ['getNumberOfRequiredParameters', null, 123, []],
            ['getParameters', null, [$mockParameter], []],
            ['getShortName', null, '', []],
            ['getStaticVariables', NotImplemented::class, null, []],
            ['returnsReference', null, true, []],
            ['isGenerator', null, true, []],
            ['isVariadic', null, true, []],

            // ReflectionFunction
            ['isDisabled', NotImplemented::class, null, []],
            ['invoke', NotImplemented::class, null, []],
            ['invokeArgs', NotImplemented::class, null, [[]]],
            ['getClosure', NotImplemented::class, null, []],
        ];
    }

    /**
     * @param string $methodName
     * @param string|null $expectedException
     * @param mixed $returnValue
     * @param array $args
     * @dataProvider methodExpectationProvider
     */
    public function testAdapterMethods($methodName, $expectedException, $returnValue, array $args)
    {
        /* @var BetterReflectionFunction|\PHPUnit_Framework_MockObject_MockObject $reflectionStub */
        $reflectionStub = $this->getMockBuilder(BetterReflectionFunction::class)
            ->disableOriginalConstructor()
            ->getMock();

        if (null === $expectedException) {
            $reflectionStub->expects($this->once())
                ->method($methodName)
                ->with(...$args)
                ->will($this->returnValue($returnValue));
        }

        if (null !== $expectedException) {
            $this->setExpectedException($expectedException);
        }

        $adapter = new ReflectionFunctionAdapter($reflectionStub);
        $adapter->{$methodName}(...$args);
    }

    public function testExport()
    {
        $this->setExpectedException(\Exception::class, 'Unable to export statically');
        ReflectionFunctionAdapter::export('str_replace');
    }
}
