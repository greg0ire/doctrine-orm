<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Tests\OrmFunctionalTestCase;

use function assert;

/**
 * Functional tests for the Single Table Inheritance mapping strategy.
 */
class GH8926Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->_schemaTool->createSchema([
            $this->_em->getClassMetadata(First::class),
            $this->_em->getClassMetadata(Second::class),
            $this->_em->getClassMetadata(Third::class),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->_schemaTool->dropSchema([
            $this->_em->getClassMetadata(First::class),
            $this->_em->getClassMetadata(Second::class),
            $this->_em->getClassMetadata(Third::class),
        ]);
    }

    public function testIssue(): void
    {
    }
}

/**
 * @Entity
 */
class First
{
    /**
     * @Id
     * @Column(type="guid")
     */
    private string $id;

    /**
     * @OneToMany(targetEntity="Second", mappedBy="first", fetch="EXTRA_LAZY", orphanRemoval="true", cascade={"all"})
     */
    private Collection $second;
}

/**
 * @Entity
 */
class Second
{
    /**
     * @Id
     * @ManyToOne(targetEntity="First", inversedBy="second", fetch="EAGER")
     * @JoinColumn(
     *     name="first_id",
     *     referencedColumnName="id",
     *     unique="true",
     *     nullable="false",
     *     onDelete="cascade"
     * )
     */
    private First $first;

    /**
     * @ManyToMany(targetEntity="Third", fetch="EAGER")
     * @JoinTable(name="second_third",
     *      joinColumns={@JoinColumn(name="second_id", referencedColumnName="first_id")},
     *      inverseJoinColumns={@JoinColumn(
     *     name="third_id",
     *     referencedColumnName="id",
     *     unique=true
     * )})
     */
    private Collection $third;
}

/**
 * @Entity
 */
class Third
{
    /**
     * @Id
     * @Column(type="guid")
     */
    private string $id;
}
