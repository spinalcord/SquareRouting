<?php

namespace SquareRouting\CLI\Commands;

use SquareRouting\Core\CLI\BaseCommand;
use SquareRouting\Core\CLI\CommandInterface;
use SquareRouting\Core\Schema;
use SquareRouting\Core\SchemaGenerator;

class GenerateSchemaCommand extends BaseCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $schema = new Schema();
        $generator = new SchemaGenerator($schema);

        $action = $this->ask("What do you want to generate? (all, tables, columns, stats)", false);

        switch ($action) {
            case 'all':
                $generator->generate();
                $this->success("All schema files generated successfully.");
                break;
            case 'tables':
                $generator->generateTableNames();
                $this->success("Table name schema files generated successfully.");
                break;
            case 'columns':
                $generator->generateColumnNames();
                $this->success("Column name schema files generated successfully.");
                break;
            case 'stats':
                $generator->printStatistics();
                break;
            default:
                $this->error("Invalid action. Please choose 'all', 'tables', 'columns', or 'stats'.");
                return 1;
        }

        return 0;
    }

    public function getDescription(): string
    {
        return "Generates schema files (TableName.php, ColumnName.php, TableName.ts, ColumnName.ts) from the Schema class.";
    }
}