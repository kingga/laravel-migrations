# Laravel Migrations
Some classes which help with migrations, e.g. changing the id column from unsigned integer to unsigned big integer along with foreign keys.

## Installation
1. `composer require kingga/laravel-migrations`
2. ...
3. Profit

## Migrations
### Change Key Type Migration
This migration is used when you want to change the type on of data stored in a column but you can't because it is referenced by other foreign key constraints. I came across this problem when trying to update the primary key on my users table from `unsigned integer` to `unsigned big integer`.

#### Usage
```php
<?php

use Kingga\LaravelMigrations\ChangeKeyTypeMigration;

class MigrationNameHere extends ChangeKeyTypeMigration
{
    /**
     * {@inheritDoc}
     */
    protected function getTable(): string
    {
        return 'users';
    }

    /**
     * {@inheritDoc}
     */
    protected function getColumn(): string
    {
        return 'id';
    }

    /**
     * {@inheritDoc}
     */
    protected function getFrom(): array
    {
        // These methods are used on the 'down' method.
        // 0 => parent.id->increments, foreign.user_id->unsignedInteger.
        return ['increments', 'unsignedInteger'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getTo(): array
    {
        // These methods are used on the 'up' method.
        // 0 => parent.id->bigIncrements, foreign.user_id->unsignedBigIncrements.
        return ['bigIncrements', 'unsignedBigIncrements'];
    }
}
```

This will keep the nullable and size information when changing the column as well as the update and delete rules on the constraint.
