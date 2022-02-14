<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\Tests\OrmFunctionalTestCase;

final class GH9483Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEntitySchema(
            [
                SomeEntity::class,
            ]
        );
    }

    public function testLimitSubqueryWalkerIsIdentifierUndefined(): void
    {
        $this->_em->persist(new SomeEntity());
        $this->_em->flush();

        $dql   = 'SELECT e FROM Doctrine\Tests\ORM\Functional\Ticket\SomeEntity e';
        $query = $this->_em->createQuery($dql);
        $query->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_TREE_WALKERS, [\Doctrine\ORM\Tools\Pagination\LimitSubqueryWalker::class]);

        $query->getResult(AbstractQuery::HYDRATE_ARRAY);
    }
}

/** @Entity */
final class SomeEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    public $id;
}
