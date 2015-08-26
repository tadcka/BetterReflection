<?php

namespace BetterReflectionTest\SourceLocator;

use BetterReflection\Identifier\Identifier;
use BetterReflection\Identifier\IdentifierType;
use BetterReflection\Reflection\ReflectionClass;
use BetterReflection\Reflection\ReflectionMethod;
use BetterReflection\Reflector\ClassReflector;
use BetterReflection\SourceLocator\InternalLocatedSource;
use BetterReflection\SourceLocator\PhpInternalSourceLocator;
use ReflectionClass as PhpReflectionClass;

/**
 * @covers \BetterReflection\SourceLocator\PhpInternalSourceLocator
 */
class PhpInternalSourceLocatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider internalSymbolsProvider
     *
     * @param string $className
     */
    public function testCanFetchInternalLocatedSource($className)
    {
        $locator = new PhpInternalSourceLocator();

        try {
            $source = $locator->__invoke(
                new Identifier($className, new IdentifierType(IdentifierType::IDENTIFIER_CLASS))
            );

            $this->assertInstanceOf(InternalLocatedSource::class, $source);
            $this->assertNotEmpty($source->getSource());
        } catch (\ReflectionException $e) {
            $this->markTestIncomplete(sprintf(
                'Can\'t reflect class "%s" due to an internal reflection exception: "%s". Consider adding a stub class',
                $className,
                $e->getMessage()
            ));
        }

    }

    /**
     * @dataProvider internalSymbolsProvider
     *
     * @param string $className
     */
    public function testCanReflectInternalClasses($className)
    {
        /* @var $class */
        $phpInternalSourceLocator = new PhpInternalSourceLocator();
        $reflector = (new ClassReflector($phpInternalSourceLocator));

        try {
            $class = $reflector->reflect($className);
        } catch (\ReflectionException $e) {
            if ($phpInternalSourceLocator->hasStub($className)) {
                throw $e;
            }

            $this->markTestIncomplete(sprintf(
                'Can\'t reflect class "%s" due to an internal reflection exception: "%s". Consider adding a stub class',
                $className,
                $e->getMessage()
            ));
        }

        $this->assertInstanceOf(ReflectionClass::class, $class);
        $this->assertSame($className, $class->getName());
        $this->assertTrue($class->isInternal());
        $this->assertFalse($class->isUserDefined());
    }

    /**
     * @return string[] internal symbols
     */
    public function internalSymbolsProvider()
    {
        $allSymbols = array_merge(
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits()
        );

        $indexedSymbols = array_combine($allSymbols, $allSymbols);

        return array_map(
            function ($symbol) {
                return [$symbol];
            },
            array_filter(
                $indexedSymbols,
                function ($symbol) {
                    $reflection = new PhpReflectionClass($symbol);

                    return $reflection->isInternal();
                }
            )
        );
    }

    public function testReturnsNullForNonExistentCode()
    {
        $locator = new PhpInternalSourceLocator();
        $this->assertNull(
            $locator->__invoke(
                new Identifier(
                    'Foo\Bar',
                    new IdentifierType(IdentifierType::IDENTIFIER_CLASS)
                )
            )
        );
    }

    public function testReturnsNullForFunctions()
    {
        $locator = new PhpInternalSourceLocator();
        $this->assertNull(
            $locator->__invoke(
                new Identifier(
                    'foo',
                    new IdentifierType(IdentifierType::IDENTIFIER_FUNCTION)
                )
            )
        );
    }

    /**
     * @dataProvider stubbedClassesProvider
     *
     * @param string $className
     */
    public function testAllGeneratedStubsAreInSyncWithInternalReflectionClasses($className)
    {
        if (! (
            class_exists($className, false)
            || interface_exists($className, false)
            || trait_exists($className, false)
        )) {
            $this->markTestSkipped(sprintf('Class "%s" is not available in this environment', $className));
        }

        $reflector = new ClassReflector(new PhpInternalSourceLocator());

        $this->assertSameClassAttributes(new \ReflectionClass($className), $reflector->reflect($className));
    }

    /**
     * @return string[][]
     */
    public function stubbedClassesProvider()
    {
        $classNames = array_filter(
            str_replace('.stub', '', scandir(__DIR__ . '/../../../stub')),
            function ($fileName) {
                return trim($fileName, '.');
            }
        );

        return array_combine(
            $classNames,
            array_map(
                function ($fileName) {
                    return [$fileName];
                },
                $classNames
            )
        );
    }

    private function assertSameClassAttributes(\ReflectionClass $original, ReflectionClass $stubbed)
    {
        $this->assertSame($original->getName(), $stubbed->getName());

        $internalParent     = $original->getParentClass();
        $betterParent       = $stubbed->getParentClass();
        $internalParentName = $internalParent ? $internalParent->getName() : null;
        $betterParentName   = $betterParent ? $betterParent->getName() : null;

        $this->assertSame($internalParentName, $betterParentName);
        //$this->assertCount(count($original->getMethods()), $stubbed->getMethods());

        $this->assertEquals($original->getConstants(), $stubbed->getConstants());

        foreach ($original->getMethods() as $method) {
            $this->assertSameMethodAttributes($method, $stubbed->getMethod($method->getName()));
        }
    }

    private function assertSameMethodAttributes(\ReflectionMethod $original, ReflectionMethod $stubbed)
    {
        $this->markTestIncomplete('TBD - #107 required');
        // @TODO once https://github.com/Roave/BetterReflection/issues/107 is handled
    }
}
