<?php

/**
 * This file contains the migration which changes all of the foreign keys which link back to the given
 * primary key and then changes the parent tables column and readds all of the foreign keys.
 *
 * @author Isaac Skelton <contact@isaacskelton.com>
 * @since 1.0.0
 * @package Kingga\LaravelMigrations
 */

namespace Kingga\LaravelMigrations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * The migration base class which will change a primary keys type and all of the foreign keys which
 * link to it.
 */
abstract class ChangeKeyTypeMigration extends Migration
{
    /**
     * Table table name which the columns reference the key in.
     *
     * @return string
     */
    abstract protected function getTable(): string;

    /**
     * The column inside of the table which the key points towards.
     *
     * @return string
     */
    abstract protected function getColumn(): string;

    /**
     * The parent and child column method when going back to the previous type. The first
     * item should be the column of $table->{$column} and the second item should be the
     * method called on the child table.
     *
     * @return string[]
     */
    abstract protected function getFrom(): array;

    /**
     * The parent and child column method when migrating to the new type. The first item
     * should be the column of the parent table and the second item should be the method
     * called on the child table.
     *
     * @return string[]
     */
    abstract protected function getTo(): array;

    /**
     * This will store a list of rules when migrating up or down so that they
     * keys can keep the same update & delete rules.
     *
     * @var array
     */
    private $rules = [];

    /**
     * Get all tables which reference the users table ID column.
     *
     * @return Collection
     */
    protected function getReferencedTables()
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('table_schema', env('DB_DATABASE'))
            ->where('referenced_table_name', $this->getTable())
            ->where('referenced_column_name', $this->getColumn())
            ->get(['table_name', 'constraint_name', 'column_name']);
    }

    /**
     * Get the rules attached to the deleted foreign key, e.g. UPDATE and DELETE
     * rules (CASCADE, RESTRICT, etc.).
     *
     * @param string $key_name The name of the foreign key.
     */
    protected function getFkRules(string $key_name)
    {
        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('constraint_schema', env('DB_DATABASE'))
            ->where('constraint_name', $key_name)
            ->select(['update_rule', 'delete_rule'])
            ->first();
    }

    /**
     * Check if the column is nullable or has a maximum size/length.
     *
     * @param string $table The name of the table.
     * @param string $column The column in the table.
     */
    protected function getExtraColumnDetails(string $table, string $column)
    {
        return DB::table('information_schema.COLUMNS')
            ->where('table_schema', env('DB_DATABASE'))
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->select(['character_maximum_length', 'is_nullable'])
            ->first();
    }

    /**
     * Delete the old foreign key so we can change the columns type to the new version.
     *
     * @param $columns The columns information retrieved from $this->getReferencedTables().
     */
    protected function deleteForeigns($columns)
    {
        foreach ($columns as $column) {
            // Save the rules for this foreign.
            $this->rules["{$column->table_name}.{$column->column_name}"] = $this->getFkRules($column->constraint_name);

            Schema::table($column->table_name, function (Blueprint $table) use ($column) {
                $table->dropForeign($column->constraint_name);
            });
        }
    }

    /**
     * Change the columns type and add foreign keys to the updated column.
     *
     * @param $columns The column information retrieved from $this->getReferencedTables().
     * @param string $method The method to use when changing the column.
     */
    protected function addForeigns($columns, string $method)
    {
        foreach ($columns as $column) {
            Schema::table($column->table_name, function (Blueprint $table) use ($column, $method) {
                // Check if the column is nullable or has a maximum size/length.
                $info = $this->getExtraColumnDetails($column->table_name, $column->column_name);

                // Change the column to the new type.
                call_user_func(
                        [$table, $method],
                        $column->column_name,
                        $info->character_maximum_length ?? null
                    )
                    ->nullable($info->is_nullable === 'YES')
                    ->change();

                // Add the foreign key constraint.
                $rules = $this->rules["{$column->table_name}.{$column->column_name}"];

                $table->foreign($column->column_name)
                    ->references($this->getColumn())
                    ->on($this->getTable())
                    ->onDelete(strtolower($rules->delete_rule))
                    ->onUpdate(strtolower($rules->update_rule));
            });
        }
    }

    /**
     * Do the migration.
     */
    protected function do()
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        $columns = $this->getReferencedTables();

        // Drop all foreign keys so we can change the column type.
        $this->deleteForeigns($columns);

        // Update the column to the new type.
        Schema::table($this->getTable(), function (Blueprint $table) use ($caller) {
            call_user_func([$table, $caller === 'down' ? $this->getFrom()[0] : $this->getTo()[0]], $this->getColumn())
                ->change();
        });

        // Add the foreign keys again to the new type (this will also change their columns).
        $this->addForeigns($columns, $caller === 'up' ? $this->getTo()[1] : $this->getFrom()[1]);
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->do();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->do();
    }
}
