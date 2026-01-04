<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseValidatorService
{
    /**
     * Check if database connection is available.
     *
     * @return bool True if connection is available, false otherwise
     */
    public function checkDataBaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Check if a table exists in the database.
     *
     * @param string $table Table name to check
     * @return bool True if table exists, false otherwise
     */
    public function checkTableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
