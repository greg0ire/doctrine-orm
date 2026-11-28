<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\SchemaTool;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\SequenceGenerator;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Tests\OrmFunctionalTestCase;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

use function array_filter;
use function array_values;
use function implode;
use function method_exists;
use function str_starts_with;

class PostgreSqlSchemaToolTest extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->_em->getConnection()->getDatabasePlatform();
        if (! $platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('The ' . self::class . ' requires the use of postgresql.');
        }
    }

    #[Group('DDC-1657')]
    public function testUpdateSchemaWithPostgreSQLSchema(): void
    {
        $sql = $this->getUpdateSchemaSqlForModels(
            DDC1657Screen::class,
            DDC1657Avatar::class,
        );
        $sql = array_filter($sql, static fn ($sql) => str_starts_with($sql, 'DROP SEQUENCE stonewood.'));

        self::assertCount(0, $sql, implode("\n", $sql));
    }

    public function testSetDeferrableForeignKey(): void
    {
        $schema = $this->getSchemaForModels(
            EntityWithSelfReferencingAssociation::class,
        );

        $table = $schema->getTable('entitywithselfreferencingassociation');
        $fks   = array_values($table->getForeignKeys());

        self::assertCount(1, $fks);

        self::assertTrue($fks[0]->getOption('deferrable'));
    }

    /** @return iterable<string, array{bool}> */
    public static function provideSchemaEditorApiUsage(): iterable
    {
        yield 'schema editor' => [true];
        yield 'legacy schema API' => [false];
    }

    #[After]
    public function cleanUpDropSchemaAssets(): void
    {
        $connection = $this->_em->getConnection();

        if (! $connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $connection->executeStatement('DROP TABLE IF EXISTS orm_drop_schema_with_sequence CASCADE');
        $connection->executeStatement('DROP TABLE IF EXISTS orm_drop_schema_without_sequence CASCADE');
        $connection->executeStatement('DROP SEQUENCE IF EXISTS orm_drop_schema_with_sequence_id_seq CASCADE');
        $connection->executeStatement('DROP SEQUENCE IF EXISTS orm_drop_schema_without_sequence_id_seq CASCADE');
    }

    #[IgnoreDeprecations]
    #[DataProvider('provideSchemaEditorApiUsage')]
    public function testGetDropSchemaSqlWithPartiallyDeployedSchema(bool $useSchemaEditorApi): void
    {
        if ($useSchemaEditorApi && ! method_exists(Schema::class, 'edit')) {
            self::markTestSkipped('Requires the schema editor API.');
        }

        $connection = $this->_em->getConnection();

        $this->cleanUpDropSchemaAssets();

        $connection->executeStatement('CREATE TABLE orm_drop_schema_without_sequence (id integer NOT NULL)');
        $connection->executeStatement('CREATE SEQUENCE orm_drop_schema_without_sequence_id_seq');

        $deployedSchema = $this->createSchemaManager()->introspectSchema();
        self::assertTrue($deployedSchema->hasTable('orm_drop_schema_without_sequence'));
        self::assertTrue($deployedSchema->hasSequence('orm_drop_schema_without_sequence_id_seq'));
        self::assertFalse($deployedSchema->hasTable('orm_drop_schema_with_sequence'));
        self::assertFalse($deployedSchema->hasSequence('orm_drop_schema_with_sequence_id_seq'));

        $this->_em->getConfiguration()->setUseSchemaEditorApi($useSchemaEditorApi);

        $sql = (new SchemaTool($this->_em))->getDropSchemaSQL([
            $this->_em->getClassMetadata(DropSchemaWithSequence::class),
            $this->_em->getClassMetadata(DropSchemaWithoutSequence::class),
        ]);

        foreach ($sql as $statement) {
            $connection->executeStatement($statement);
        }

        $deployedSchema = $this->createSchemaManager()->introspectSchema();
        self::assertFalse($deployedSchema->hasTable('orm_drop_schema_without_sequence'));
        self::assertFalse($deployedSchema->hasSequence('orm_drop_schema_without_sequence_id_seq'));
    }
}

#[Table(name: 'stonewood.screen')]
#[Entity]
class DDC1657Screen
{
    /**
     * Identifier
     */
    #[Id]
    #[GeneratedValue(strategy: 'IDENTITY')]
    #[Column(name: 'pk', type: 'integer', nullable: false)]
    private int $pk;

    /**
     * Title
     */
    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    private string $title;

    /**
     * Path
     */
    #[Column(name: 'path', type: 'string', length: 255, nullable: false)]
    private string $path;

    /**
     * Register date
     *
     * @var Date
     */
    #[Column(name: 'ddate', type: 'date_immutable', nullable: false)]
    private $ddate;

    /**
     * Avatar
     *
     * @var Stonewood\Model\Entity\Avatar
     */
    #[ManyToOne(targetEntity: 'DDC1657Avatar')]
    #[JoinColumn(name: 'pk_avatar', referencedColumnName: 'pk', nullable: true, onDelete: 'CASCADE')]
    private $avatar;
}

#[Table(name: 'stonewood.avatar')]
#[Entity]
class DDC1657Avatar
{
    /**
     * Identifier
     */
    #[Id]
    #[GeneratedValue(strategy: 'IDENTITY')]
    #[Column(name: 'pk', type: 'integer', nullable: false)]
    private int $pk;
}

#[Table(name: 'entitywithselfreferencingassociation')]
#[Entity]
class EntityWithSelfReferencingAssociation
{
    /**
     * Identifier
     */
    #[Id]
    #[GeneratedValue(strategy: 'IDENTITY')]
    #[Column(type: 'integer', nullable: false)]
    private int $id;

    #[ManyToOne(targetEntity: self::class)]
    #[JoinColumn(deferrable: true)]
    private self $parent;
}

#[Table(name: 'orm_drop_schema_with_sequence')]
#[Entity]
class DropSchemaWithSequence
{
    #[Id]
    #[GeneratedValue(strategy: 'SEQUENCE')]
    #[SequenceGenerator(sequenceName: 'orm_drop_schema_with_sequence_id_seq', allocationSize: 1, initialValue: 1)]
    #[Column(type: 'integer')]
    public int $id = 0;
}

#[Table(name: 'orm_drop_schema_without_sequence')]
#[Entity]
class DropSchemaWithoutSequence
{
    #[Id]
    #[Column(type: 'integer')]
    public int $id = 0;
}
