<?php

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Proxy\Proxy;

class DDC531Test extends \Doctrine\Tests\OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->_schemaTool->createSchema(
            [
            $this->_em->getClassMetadata(DDC531Item::class),
            $this->_em->getClassMetadata(DDC531SubItem::class),
            ]
        );
    }

    public function testIssue()
    {
        $item1 = new DDC531Item;
        $item2 = new DDC531Item;
        $item2->parent = $item1;
        $item1->getChildren()->add($item2);
        $this->_em->persist($item1);
        $this->_em->persist($item2);
        $this->_em->flush();
        $this->_em->clear();

        $item3 = $this->_em->find(DDC531Item::class, $item2->id); // Load child item first (id 2)
        // parent will already be loaded, cannot be lazy because it has mapped subclasses and we would not
        // know which proxy type to put in.
        $this->assertInstanceOf(DDC531Item::class, $item3->parent);
        $this->assertNotInstanceOf(Proxy::class, $item3->parent);
        $item4 = $this->_em->find(DDC531Item::class, $item1->id); // Load parent item (id 1)
        $this->assertNull($item4->parent);
        $this->assertNotNull($item4->getChildren());
        $this->assertTrue($item4->getChildren()->contains($item3)); // lazy-loads children
    }
}

/**
 * @Entity
 * @InheritanceType("SINGLE_TABLE")
 * @DiscriminatorColumn(name="type", type="integer")
 * @DiscriminatorMap({"0" = "DDC531Item", "1" = "DDC531SubItem"})
 */
class DDC531Item
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @OneToMany(targetEntity="DDC531Item", mappedBy="parent")
     */
    protected $children;

    /**
     * @ManyToOne(targetEntity="DDC531Item", inversedBy="children")
     * @JoinColumn(name="parentId", referencedColumnName="id")
     */
    public $parent;

    public function __construct()
    {
        $this->children = new \Doctrine\Common\Collections\ArrayCollection;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getChildren()
    {
        return $this->children;
    }
}

/**
 * @Entity
 */
class DDC531SubItem extends DDC531Item
{
}
