<?php

namespace BetterReflection\Reflection;

use BetterReflection\Reflector\Reflector;
use BetterReflection\SourceLocator\LocatedSource;
use BetterReflection\TypesFinder\FindReturnType;
use Closure;
use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_ as NamespaceNode;
use PhpParser\Node\Expr\Yield_ as YieldNode;
use PhpParser\Node\Expr\Closure as ClosureNode;
use phpDocumentor\Reflection\Type;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as StandardPrettyPrinter;
use PhpParser\PrettyPrinterAbstract;
use SuperClosure\Analyzer\AstAnalyzer;

abstract class ReflectionFunctionAbstract implements \Reflector
{
    /**
     * @var NamespaceNode
     */
    private $declaringNamespace;

    /**
     * @var LocatedSource
     */
    private $locatedSource;

    /**
     * @var Node\Stmt\ClassMethod|Node\Stmt\Function_
     */
    private $node;

    /**
     * @var Reflector
     */
    private $reflector;

    protected function __construct()
    {
    }

    public static function export()
    {
        throw new \Exception('Unable to export statically');
    }

    /**
     * Populate the common elements of the function abstract.
     *
     * @param Reflector $reflector
     * @param Node\Stmt\ClassMethod|Node\Stmt\Function_|Node\Stmt $node
     * @param LocatedSource $locatedSource
     * @param NamespaceNode|null $declaringNamespace
     */
    protected function populateFunctionAbstract(Reflector $reflector, Node\Stmt $node, LocatedSource $locatedSource, NamespaceNode $declaringNamespace = null)
    {
        if (!($node instanceof Node\Stmt\ClassMethod) && !($node instanceof Node\Stmt\Function_)) {
            throw new \InvalidArgumentException('Node parameter must be ClassMethod or Function_');
        }

        $this->reflector = $reflector;
        $this->node = $node;
        $this->locatedSource = $locatedSource;
        $this->declaringNamespace = $declaringNamespace;

        $this->setNodeOptionalFlag();
    }

    /**
     * Get the AST node from which this function was created
     *
     * @return Node\Stmt\ClassMethod|Node\Stmt\Function_
     */
    protected function getNode()
    {
        return $this->node;
    }

    /**
     * We must determine if params are optional or not ahead of time, but we
     * must do it in reverse...
     */
    private function setNodeOptionalFlag()
    {
        $overallOptionalFlag = true;
        $lastParamIndex = (count($this->node->params) - 1);
        for ($i = $lastParamIndex; $i >= 0; $i--) {
            $hasDefault = ($this->node->params[$i]->default !== null);

            // When we find the first parameter that does not have a default,
            // flip the flag as all params for this are no longer optional
            // EVEN if they have a default value
            if (!$hasDefault) {
                $overallOptionalFlag = false;
            }

            $this->node->params[$i]->isOptional = $overallOptionalFlag;
        }
    }

    /**
     * Get the "full" name of the function (e.g. for A\B\foo, this will return
     * "A\B\foo").
     *
     * @return string
     */
    public function getName()
    {
        if (!$this->inNamespace()) {
            return $this->getShortName();
        }

        return $this->getNamespaceName() . '\\' . $this->getShortName();
    }

    /**
     * Get the "short" name of the function (e.g. for A\B\foo, this will return
     * "foo").
     *
     * @return string
     */
    public function getShortName()
    {
        return $this->node->name;
    }

    /**
     * Get the "namespace" name of the function (e.g. for A\B\foo, this will
     * return "A\B").
     *
     * @return string
     */
    public function getNamespaceName()
    {
        if (!$this->inNamespace()) {
            return '';
        }

        return implode('\\', $this->declaringNamespace->name->parts);
    }

    /**
     * Decide if this function is part of a namespace. Returns false if the class
     * is in the global namespace or does not have a specified namespace.
     *
     * @return bool
     */
    public function inNamespace()
    {
        return null !== $this->declaringNamespace
            && null !== $this->declaringNamespace->name;
    }

    /**
     * Get the number of parameters for this class.
     *
     * @return int
     */
    public function getNumberOfParameters()
    {
        return count($this->getParameters());
    }

    /**
     * Get the number of required parameters for this method.
     *
     * @return int
     */
    public function getNumberOfRequiredParameters()
    {
        return count(array_filter(
            $this->getParameters(),
            function (ReflectionParameter $p) {
                return !$p->isOptional();
            }
        ));
    }

    /**
     * Get an array list of the parameters for this method signature, as an
     * array of ReflectionParameter instances.
     *
     * @return ReflectionParameter[]
     */
    public function getParameters()
    {
        $parameters = [];
        foreach ($this->node->params as $paramIndex => $paramNode) {
            $parameters[] = ReflectionParameter::createFromNode(
                $this->reflector,
                $paramNode,
                $this,
                $paramIndex
            );
        }
        return $parameters;
    }

    /**
     * Get a single parameter by name. Returns null if parameter not found for
     * the function.
     *
     * @param string $parameterName
     * @return ReflectionParameter|null
     */
    public function getParameter($parameterName)
    {
        foreach ($this->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return $parameter;
            }
        }
        return null;
    }

    /**
     * @return string
     */
    public function getDocComment()
    {
        if (!$this->node->hasAttribute('comments')) {
            return '';
        }
        /* @var \PhpParser\Comment\Doc $comment */
        $comment = $this->node->getAttribute('comments')[0];
        return $comment->getReformattedText();
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->locatedSource->getFileName();
    }

    /**
     * @return LocatedSource
     */
    public function getLocatedSource()
    {
        return $this->locatedSource;
    }

    /**
     * Is this function a closure?
     *
     * Note - we cannot reflect on closures at the moment (as there is no PHP
     * source code we can access).
     *
     * @see https://github.com/Roave/BetterReflection/issues/37
     * @return bool
     */
    public function isClosure()
    {
        return false;
    }

    /**
     * Is this function deprecated?
     *
     * Note - we cannot reflect on internal functions (as there is no PHP source
     * code we can access. This means, at present, we can only EVER return false
     * from this function.
     *
     * @see https://github.com/Roave/BetterReflection/issues/38
     * @return bool
     */
    public function isDeprecated()
    {
        return false;
    }

    /**
     * Is this an internal function?
     *
     * Note - we cannot reflect on internal functions (as there is no PHP source
     * code we can access. This means, at present, we can only EVER return false
     * from this function.
     *
     * @see https://github.com/Roave/BetterReflection/issues/38
     * @return bool
     */
    public function isInternal()
    {
        return false;
    }

    /**
     * Is this a user-defined function (will always return the opposite of
     * whatever isInternal returns).
     *
     * @return bool
     */
    public function isUserDefined()
    {
        return !$this->isInternal();
    }

    /**
     * Check if the function has a variadic parameter.
     *
     * @return bool
     */
    public function isVariadic()
    {
        $parameters = $this->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively search an array of statements (PhpParser nodes) to find if a
     * yield expression exists anywhere (thus indicating this is a generator).
     *
     * @param \PhpParser\Node $node
     * @return bool
     */
    private function nodeIsOrContainsYield(Node $node)
    {
        if ($node instanceof YieldNode) {
            return true;
        }

        foreach ($node as $nodeProperty) {
            if ($nodeProperty instanceof Node && $this->nodeIsOrContainsYield($nodeProperty)) {
                return true;
            }

            if (is_array($nodeProperty)) {
                foreach ($nodeProperty as $nodePropertyArrayItem) {
                    if ($nodePropertyArrayItem instanceof Node && $this->nodeIsOrContainsYield($nodePropertyArrayItem)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if this function can be used as a generator (i.e. contains the
     * "yield" keyword).
     *
     * @return bool
     */
    public function isGenerator()
    {
        if (!isset($this->node)) {
            return false;
        }
        return $this->nodeIsOrContainsYield($this->node);
    }

    /**
     * Get the line number that this function starts on.
     *
     * @return int
     */
    public function getStartLine()
    {
        return (int)$this->node->getAttribute('startLine', -1);
    }

    /**
     * Get the line number that this function ends on.
     *
     * @return int
     */
    public function getEndLine()
    {
        return (int)$this->node->getAttribute('endLine', -1);
    }

    /**
     * Is this function declared as a reference.
     *
     * @return bool
     */
    public function returnsReference()
    {
        return (bool)$this->node->byRef;
    }

    /**
     * Get the return types defined in the DocBlocks. This returns an array because
     * the parameter may have multiple (compound) types specified (for example
     * when you type hint pipe-separated "string|null", in which case this
     * would return an array of Type objects, one for string, one for null.
     *
     * @return Type[]
     */
    public function getDocBlockReturnTypes()
    {
        return  (new FindReturnType())->__invoke($this);
    }

    /**
     * Retrieves the body of this function as AST nodes
     *
     * @return array
     */
    public function getBodyAst()
    {
        return $this->node->stmts;
    }

    /**
     * Retrieves the body of this function as code.
     *
     * If a PrettyPrinter is provided as a paramter, it will be used, otherwise
     * a default will be used.
     *
     * Note that the formatting of the code may not be the same as the original
     * function. If specific formatting is required, you should provide your
     * own implementation of a PrettyPrinter to unparse the AST.
     *
     * @param PrettyPrinterAbstract|null $printer
     * @return string
     */
    public function getBodyCode(PrettyPrinterAbstract $printer = null)
    {
        if (null === $printer) {
            $printer = new StandardPrettyPrinter();
        }

        return $printer->prettyPrint($this->getBodyAst());
    }

    /**
     * Fetch the AST for this method or function.
     *
     * @return Node\Stmt\ClassMethod|Node\Stmt\Function_
     */
    public function getAst()
    {
        return $this->node;
    }

    /**
     * Override the method or function's body of statements with an entirely new
     * body of statements (!) within the reflection.
     *
     * You may either pass a string containing statements, or a closure:
     *
     * @example
     * // The following are identical...
     * $reflectionFunction->setBody('return true');
     * $reflectionFunction->setBody(function () { return true; });
     *
     * @param string|Closure $newBody
     */
    public function setBody($newBody)
    {
        if ($newBody instanceof Closure) {
            $closureData = (new AstAnalyzer())->analyze($newBody);

            if (!isset($closureData['ast']) || !($closureData['ast'] instanceof ClosureNode)) {
                throw new Exception\ClosureAstExtractionFailure('Failed to extract AST from closure - AST data not returned by AstAnalyzer');
            }

            $functionStatements = $closureData['ast']->stmts;
        }

        if (is_string($newBody)) {
            $functionStatements = (new ParserFactory())
                ->create(ParserFactory::PREFER_PHP7)
                ->parse('<?php ' . $newBody);
        }

        if (!isset($functionStatements)) {
            throw new \InvalidArgumentException('New body for function or method must be a Closure or string with PHP statements');
        }

        $this->node->stmts = $functionStatements;
    }
}
