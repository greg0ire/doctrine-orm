<?php

declare(strict_types=1);

namespace Utils\PHPStan;

use Doctrine\ORM\Query\ResultSetMapping;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function array_diff;
use function assert;
use function implode;
use function sprintf;

final class FrozenClassRule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return array<int, IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof Node\Stmt\Class_);
        if ($node->namespacedName->toString() !== ResultSetMapping::class) {
            return [];
        }

        $propertyNames = [];
        foreach ($node->getProperties() as $property) {
            foreach ($property->props as $prop) {
                $propertyNames[] = $prop->name->name;
            }
        }

        $diff = array_diff($propertyNames, [
            'isMixed',
            'isSelect',
            'aliasMap',
            'relationMap',
            'parentAliasMap',
            'fieldMappings',
            'columnAliasMappings',
            'scalarMappings',
            'enumMappings',
            'typeMappings',
            'entityMappings',
            'metaMappings',
            'columnOwnerMap',
            'discriminatorColumns',
            'indexByMap',
            'declaringClasses',
            'isIdentifierColumn',
            'newObjectMappings',
            'metadataParameterMapping',
            'discriminatorParameters',
        ]);

        if ($diff) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'Class %s is frozen, but it has properties that are not allowed to be set: %s',
                        $node->namespacedName->toString(),
                        implode(', ', $diff)
                    )
                )
                    ->identifier('doctrine.ORM.FrozenClass')
                    ->build(),
            ];
        }

        return [];
    }
}
