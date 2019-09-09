<?php

namespace HackerBoy\LaravelCockroachDB\Builder;

use Illuminate\Database\Schema\PostgresBuilder;

class CockroachBuilder extends PostgresBuilder
{
    /**
     * Determine if the given table exists.
     *
     * @param  string  $table
     * @return bool
     */
    public function hasTable($table)
    {
        if (is_array($schema = $this->connection->getConfig('schema'))) {
            $schema = head($schema);
        }

        $schema = $schema ? $schema : 'public';

        $table = $this->connection->getTablePrefix().$table;

        return count($this->connection->select(
                $this->grammar->compileTableExists(), [$schema, $table]
            )) > 0;
    }

    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function dropAllTables()
    {
        $tables = [];

        foreach ($this->getAllTables() as $row) {
            $row = (array) $row;

            $tables[] = reset($row);
        }

        if (empty($tables)) {
            return;
        }

        $this->connection->statement(
            $this->grammar->compileDropAllTables($tables)
        );
    }

}
