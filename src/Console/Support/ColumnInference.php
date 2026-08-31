<?php

namespace Invue\Panels\Console\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reads an already-migrated table's real columns (`Schema::getColumns()`,
 * Laravel 11+, no doctrine/dbal needed) and turns them into FieldDescriptors
 * for make:invue-resource. Best-effort by design: relations (`*_id`),
 * timestamps, the primary key, and password-like columns are skipped
 * entirely rather than guessed at — same "ship the common case, leave an
 * explicit gap" posture as invue/tables' v1-scope list. Always eyeball the
 * generated Table/Form after running the command.
 */
class ColumnInference
{
    /**
     * @return list<FieldDescriptor>
     */
    public static function forTable(string $table, string $primaryKey): array
    {
        $skip = [$primaryKey, 'created_at', 'updated_at', 'deleted_at', 'remember_token'];

        $fields = [];

        foreach (Schema::getColumns($table) as $column) {
            $name = $column['name'];

            if (in_array($name, $skip, true)) {
                continue;
            }

            if (str_ends_with($name, '_id') || str_contains($name, 'password')) {
                // Foreign keys and passwords are deliberately out of v1 scope:
                // relations need their own UI story, passwords need hashing
                // wired into the generated controller — both real follow-ups,
                // neither guessed at here. See panels/README.md.
                continue;
            }

            $fields[] = new FieldDescriptor(
                name: $name,
                label: Str::headline($name),
                kind: static::kindFor($name, $column),
                nullable: (bool) ($column['nullable'] ?? true),
            );
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    protected static function kindFor(string $name, array $column): string
    {
        $type = strtolower($column['type_name'] ?? $column['type'] ?? '');
        $fullType = strtolower($column['type'] ?? '');

        $looksBoolean = (bool) preg_match('/^(is_|has_|can_)/', $name)
            || in_array($name, ['active', 'published', 'enabled', 'verified', 'featured'], true);

        // 'tinyint(1)' — the display width, not just the bare type name — is
        // the actual on-disk convention `$table->boolean()` produces on both
        // MySQL and SQLite (Postgres has a real `boolean` type, already
        // covered above). Checking it directly catches every boolean column
        // regardless of its name; the name heuristic only remains as a
        // fallback for a bare 'tinyint' with no width info at all.
        if (
            in_array($type, ['boolean', 'bool', 'bit'], true)
            || $fullType === 'tinyint(1)'
            || ($type === 'tinyint' && $looksBoolean)
        ) {
            return 'boolean';
        }

        if (str_contains($name, 'email')) {
            return 'email';
        }

        if (in_array($type, ['text', 'longtext', 'mediumtext', 'tinytext'], true)) {
            return 'text';
        }

        if ($type === 'date') {
            return 'date';
        }

        if (in_array($type, ['datetime', 'timestamp'], true)) {
            return 'datetime';
        }

        if (in_array($type, ['integer', 'int', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double', 'numeric'], true)) {
            return 'number';
        }

        return 'string';
    }
}
