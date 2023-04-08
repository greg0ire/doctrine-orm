<?php

declare(strict_types=1);

namespace Utils\Rector\Rector;

use Doctrine\ORM\Mapping\AssociationMapping;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Type\ObjectType;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function in_array;

final class MappingDtoRector extends AbstractRector
{
    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        // what node types are we looking for?
        // pick any node from https://github.com/rectorphp/php-parser-nodes-docs/
        return [ArrayDimFetch::class];
    }

    /**
     * @param ArrayDimFetch $node - we can add "MethodCall" type here, because
     *                         only this node is in "getNodeTypes()"
     */
    public function refactor(Node $node): Node|null
    {
        if (! $this->isObjectType($node->var, new ObjectType(AssociationMapping::class))) {
            return null;
        }

        if (
            in_array($node->dim->value, [
                'isOwningSide',
                'type',
                'isCascadeRemove',
                'isCascadePersist',
                'isCascadeRefresh',
                'isCascadeMerge',
                'isCascadeDetach',
            ])
        ) {
            return new MethodCall($node->var, $node->dim->value);
        }

        return new PropertyFetch($node->var, $node->dim);
    }

    /**
     * This method helps other to understand the rule and to generate documentation.
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change array access to property access',
            [
                new CodeSample(
                    // code before
                    '$associationMapping["foo"];',
                    // code after
                    '$associationMapping->foo;',
                ),
            ],
        );
    }
}
