<?php

namespace BetterReflection\SourceLocator\Type;

use BetterReflection\Identifier\Identifier;

class AggregateSourceLocator implements SourceLocator
{
    /**
     * @var SourceLocator[]
     */
    private $sourceLocators;

    /**
     * @param SourceLocator[] $sourceLocators
     */
    public function __construct(array $sourceLocators = [])
    {
        // This slightly confusing code simply type-checks the $sourceLocators
        // array by unpacking them and splatting them in the closure.
        $validator = function (SourceLocator ...$sourceLocator) {
            return $sourceLocator;
        };
        $this->sourceLocators = $validator(...$sourceLocators);
    }

    /**
     * {@inheritDoc}
     */
    public function __invoke(Identifier $identifier)
    {
        foreach ($this->sourceLocators as $sourceLocator) {
            if ($located = $sourceLocator($identifier)) {
                return $located;
            }
        }

        return null;
    }
}
