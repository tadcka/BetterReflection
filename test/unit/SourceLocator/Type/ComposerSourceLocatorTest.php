<?php

namespace BetterReflectionTest\SourceLocator\Type;

use BetterReflection\Identifier\Identifier;
use BetterReflection\Identifier\IdentifierType;
use BetterReflection\Reflector\Reflector;
use BetterReflection\SourceLocator\Type\ComposerSourceLocator;
use ClassWithNoNamespace;
use Composer\Autoload\ClassLoader;

/**
 * @covers \BetterReflection\SourceLocator\Type\ComposerSourceLocator
 */
class ComposerSourceLocatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return Reflector|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockReflector()
    {
        return $this->getMock(Reflector::class);
    }

    public function testInvokableLoadsSource()
    {
        $className = 'ClassWithNoNamespace';
        $fileName = __DIR__ . '/../../Fixture/NoNamespace.php';

        $loader = $this->getMockBuilder(ClassLoader::class)
            ->setMethods(['findFile'])
            ->getMock();

        $loader
            ->expects($this->once())
            ->method('findFile')
            ->with($className)
            ->will($this->returnValue($fileName));

        /** @var ClassLoader $loader */
        $locator = new ComposerSourceLocator($loader);

        $reflectionClass = $locator->locateIdentifier($this->getMockReflector(), new Identifier(
            $className,
            new IdentifierType(IdentifierType::IDENTIFIER_CLASS)
        ));

        $this->assertSame('ClassWithNoNamespace', $reflectionClass->getName());
    }

    public function testInvokableThrowsExceptionWhenClassNotResolved()
    {
        $className = ClassWithNoNamespace::class;

        $loader = $this->getMockBuilder(ClassLoader::class)
            ->setMethods(['findFile'])
            ->getMock();

        $loader
            ->expects($this->once())
            ->method('findFile')
            ->with($className)
            ->will($this->returnValue(null));

        /** @var ClassLoader $loader */
        $locator = new ComposerSourceLocator($loader);

        $this->assertNull($locator->locateIdentifier($this->getMockReflector(), new Identifier(
            $className,
            new IdentifierType(IdentifierType::IDENTIFIER_CLASS)
        )));
    }

    public function testInvokeThrowsExceptionWhenTryingToLocateFunction()
    {
        $loader = $this->getMock(ClassLoader::class);

        /** @var ClassLoader $loader */
        $locator = new ComposerSourceLocator($loader);

        $this->assertNull($locator->locateIdentifier($this->getMockReflector(), new Identifier(
            'foo',
            new IdentifierType(IdentifierType::IDENTIFIER_FUNCTION)
        )));
    }
}
