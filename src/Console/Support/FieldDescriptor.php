<?php

namespace Invue\Panels\Console\Support;

use Illuminate\Support\Str;

/**
 * One inferred DB column, translated into an Invue form/table field "kind".
 * `kind` drives which invue/forms component and invue/tables column the
 * generator writes for this field — see ColumnInference::kindFor().
 */
class FieldDescriptor
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $kind,
        public readonly bool $nullable,
    ) {}

    public function camel(): string
    {
        return Str::camel($this->name);
    }
}
