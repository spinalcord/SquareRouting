<?php

declare(strict_types=1);

namespace SquareRouting\Core\CLI\Commands;

use SquareRouting\Core\CLI\AbstractCommand;
use SquareRouting\Core\CLI\CommandInterface;
use SquareRouting\Core\CLI\SchemaGenerator;
use SquareRouting\Core\Scheme;

class GenerateSchemaCommand extends AbstractCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'generate:schema';
    }

    public function getDescription(): string
    {
        return 'Generates TableName.php and ColumnName.php from Scheme definitions.';
    }

    public function execute(array $args): void
    {
        echo "Generating schema constants...\n";


        $schemaGenerator = $this->container->get(SchemaGenerator::class);
        $schemaGenerator->generate();
        echo "Schema generation complete.\n";
    }
}