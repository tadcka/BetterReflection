<?php

namespace BetterReflection\SourceLocator\Ast;

use BetterReflection\Identifier\Identifier;
use BetterReflection\Identifier\IdentifierType;
use BetterReflection\SourceLocator\Located\DefiniteLocatedSource;
use BetterReflection\SourceLocator\Located\PotentiallyLocatedSource;
use BetterReflection\SourceLocator\Located\LocatedSource;
use BetterReflection\Reflection\Reflection;
use BetterReflection\Reflector\Reflector;
use BetterReflection\Reflector\Exception\IdentifierNotFound;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\Lexer;

class Locator
{
    /**
     * @var NodeReflector
     */
    private $nodeReflector;

    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Reflector $reflector)
    {
        $this->nodeReflector = new NodeReflector($reflector);

        $this->parser = new Parser\Multiple([
            new Parser\Php7(new Lexer()),
            new Parser\Php5(new Lexer())
        ]);
    }

    /**
     * Determine if the AST from a located source contains the identifier
     *
     * @todo improve this implementation to peek instead of just throwing exception
     *
     * @param LocatedSource $locatedSource
     * @param Identifier $identifier
     * @return bool
     */
    public function hasIdentifier(LocatedSource $locatedSource, Identifier $identifier)
    {
        try {
            $this->findReflection($locatedSource, $identifier);
            return true;
        } catch (IdentifierNotFound $identifierNotFoundException) {
            return false;
        }
    }

    /**
     * @param Identifier $identifier
     * @param LocatedSource $locatedSource
     * @return Reflection
     */
    public function findReflection(LocatedSource $locatedSource, Identifier $identifier)
    {
        return $this->findInArray(
            $this->findReflectionsOfType(
                $locatedSource,
                $identifier->getType()
            ),
            $identifier
        );
    }

    /**
     * Get an array of reflections found in a LocatedSource.
     *
     * @param LocatedSource $locatedSource
     * @param IdentifierType $identifierType
     * @return Reflection[]
     */
    public function findReflectionsOfType(LocatedSource $locatedSource, IdentifierType $identifierType)
    {
        return $this->reflectFromTree(
            $this->parser->parse($locatedSource->getSource()),
            $identifierType,
            $locatedSource
        );
    }

    /**
     * Given an array of Reflections, try to find the identifier.
     *
     * @param Reflection[] $reflections
     * @param Identifier $identifier
     * @return Reflection
     */
    private function findInArray($reflections, Identifier $identifier)
    {
        foreach ($reflections as $reflection) {
            if ($reflection->getName() === $identifier->getName()) {
                return $reflection;
            }
        }

        throw IdentifierNotFound::fromIdentifier($identifier);
    }

    /**
     * @param Node $node
     * @param LocatedSource $locatedSource
     * @param Node\Stmt\Namespace_|null $namespace
     * @return Reflection|null
     */
    private function reflectNode(Node $node, LocatedSource $locatedSource, Node\Stmt\Namespace_ $namespace = null)
    {
        if ($locatedSource instanceof PotentiallyLocatedSource) {
            $locatedSource = DefiniteLocatedSource::fromPotentiallyLocatedSource($locatedSource);
        }

        return $this->nodeReflector->__invoke($node, $locatedSource, $namespace);
    }

    /**
     * Process and reflect all the matching identifiers found inside a namespace node.
     *
     * @param Node\Stmt\Namespace_ $namespace
     * @param IdentifierType $identifierType
     * @param LocatedSource $locatedSource
     * @return Reflection[]
     */
    private function reflectFromNamespace(
        Node\Stmt\Namespace_ $namespace,
        IdentifierType $identifierType,
        LocatedSource $locatedSource
    ) {
        $reflections = [];
        foreach ($namespace->stmts as $node) {
            $reflection = $this->reflectNode($node, $locatedSource, $namespace);

            if (null !== $reflection && $identifierType->isMatchingReflector($reflection)) {
                $reflections[] = $reflection;
            }
        }
        return $reflections;
    }

    /**
     * Reflect identifiers from an AST. If a namespace is found, also load all the
     * matching identifiers found in the namespace.
     *
     * @param Node[] $ast
     * @param IdentifierType $identifierType
     * @param LocatedSource $locatedSource
     * @return \BetterReflection\Reflection\Reflection[]
     */
    private function reflectFromTree(array $ast, IdentifierType $identifierType, LocatedSource $locatedSource)
    {
        $reflections = [];
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $reflections = array_merge(
                    $reflections,
                    $this->reflectFromNamespace($node, $identifierType, $locatedSource)
                );
            } elseif ($node instanceof Node\Stmt\ClassLike) {
                $reflection = $this->reflectNode($node, $locatedSource, null);
                if ($identifierType->isMatchingReflector($reflection)) {
                    $reflections[] = $reflection;
                }
            } elseif ($node instanceof Node\Stmt\Function_) {
                $reflection = $this->reflectNode($node, $locatedSource, null);
                if ($identifierType->isMatchingReflector($reflection)) {
                    $reflections[] = $reflection;
                }
            }
        }
        return $reflections;
    }
}
