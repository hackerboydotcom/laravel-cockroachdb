<?php

namespace HackerBoy\LaravelCockroachDB;

use Illuminate\Database\PostgresConnection;
use HackerBoy\LaravelCockroachDB\Builder\CockroachBuilder;
use HackerBoy\LaravelCockroachDB\Processor\CockroachProcessor;
use Doctrine\DBAL\Driver\PDOPgSql\Driver as DoctrineDriver;
use HackerBoy\LaravelCockroachDB\Grammar\Query\CockroachGrammar as QueryGrammar;
use HackerBoy\LaravelCockroachDB\Grammar\Schema\CockroachGrammar as SchemaGrammar;

class CockroachConnection extends PostgresConnection
{
        /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        // For update is not supported because cockroachdb has no lock
        // https://github.com/cockroachdb/cockroach/issues/6583
        if (preg_match('/for\supdate$/i', $query)) {
            $query = str_replace('for update', '', $query);
        }

        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }
            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)
                              ->prepare($query));
            $this->bindValues($statement, $this->prepareBindings($bindings));
            $statement->execute();
            return $statement->fetchAll();
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        if (preg_match('/^update/i', $query)) {

            preg_match_all('/"[a-z0-9_]+"\./', $query, $matches);
            $matches = $matches[0];

            foreach ($matches as $match) {
                $query = str_replace($match, '', $query);
            }

            $query = str_replace('"', '', $query);

        }

        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }
            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            $statement = $this->getPdo()->prepare($query);
            $this->bindValues($statement, $this->prepareBindings($bindings));
            $statement->execute();
            $this->recordsHaveBeenModified(
                ($count = $statement->rowCount()) > 0
            );
            return $count;
        });
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \App\Cockroach\Query\CockroachGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Illuminate\Database\Schema\PostgresBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CockroachBuilder($this);
    }

    /*
     * Get the default schema grammar instance.
     *
     * @return \App\Cockroach\Schema\CockroachGrammar
    */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\PostgresProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new CockroachProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \Doctrine\DBAL\Driver\PDOPgSql\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}
