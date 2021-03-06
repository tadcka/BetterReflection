<?php

namespace BetterReflection\SourceLocator;

/**
 * {@inheritDoc}
 */
class InternalLocatedSource extends LocatedSource
{
    /**
     * @param string $source
     */
    public function __construct($source)
    {
        parent::__construct($source, null);
    }

    /**
     * {@inheritDoc}
     */
    public function isInternal()
    {
        return true;
    }
}
