<?php

declare(strict_types=1);

namespace Rector\Doctrine\NodeAnalyzer;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Reflection\Php\PhpPropertyReflection;
use PHPStan\Type\ObjectType;
use Rector\Core\Reflection\ReflectionResolver;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\NodeTypeResolver;

final class SetterClassMethodAnalyzer
{
    public function __construct(
        private readonly NodeTypeResolver $nodeTypeResolver,
        private readonly NodeNameResolver $nodeNameResolver,
        private readonly ReflectionResolver $reflectionResolver
    ) {
    }

    public function matchNullalbeClassMethodPropertyName(ClassMethod $classMethod): ?string
    {
        $propertyFetch = $this->matchNullalbeClassMethodPropertyFetch($classMethod);
        if (! $propertyFetch instanceof PropertyFetch) {
            return null;
        }

        $phpPropertyReflection = $this->reflectionResolver->resolvePropertyReflectionFromPropertyFetch($propertyFetch);
        if (! $phpPropertyReflection instanceof PhpPropertyReflection) {
            return null;
        }

        $reflectionProperty = $phpPropertyReflection->getNativeReflection();
        return $reflectionProperty->getName();
    }

    /**
     * Matches:
     *
     * public function setSomething(?Type $someValue); { <$this->someProperty> = $someValue; }
     */
    private function matchNullalbeClassMethodPropertyFetch(ClassMethod $classMethod): ?PropertyFetch
    {
        $propertyFetch = $this->matchSetterOnlyPropertyFetch($classMethod);
        if (! $propertyFetch instanceof PropertyFetch) {
            return null;
        }

        // is nullable param
        $onlyParam = $classMethod->params[0];
        if (! $this->nodeTypeResolver->isNullableTypeOfSpecificType($onlyParam, ObjectType::class)) {
            return null;
        }

        return $propertyFetch;
    }

    private function matchSetterOnlyPropertyFetch(ClassMethod $classMethod): ?PropertyFetch
    {
        if (count($classMethod->params) !== 1) {
            return null;
        }

        $stmts = (array) $classMethod->stmts;
        if (count($stmts) !== 1) {
            return null;
        }

        $onlyStmt = $stmts[0] ?? null;
        if (! $onlyStmt instanceof Stmt) {
            return null;
        }

        if ($onlyStmt instanceof Expression) {
            $onlyStmt = $onlyStmt->expr;
        }

        if (! $onlyStmt instanceof Assign) {
            return null;
        }

        if (! $onlyStmt->var instanceof PropertyFetch) {
            return null;
        }

        $propertyFetch = $onlyStmt->var;
        if (! $this->isVariableName($propertyFetch->var, 'this')) {
            return null;
        }

        return $propertyFetch;
    }

    private function isVariableName(?Node $node, string $name): bool
    {
        if (! $node instanceof Variable) {
            return false;
        }

        return $this->nodeNameResolver->isName($node, $name);
    }
}
