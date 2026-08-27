<?php

namespace Invue\Panels\Console\Support;

/**
 * Turns a FieldDescriptor's `kind` into the actual invue/forms field and
 * invue/tables column markup the generator writes into the Vue stubs, plus
 * the matching FormRequest validation rule. One place per concern, so a new
 * kind (or a nicer heuristic later) only needs changing here.
 */
class FieldRenderer
{
    public static function tableColumn(FieldDescriptor $field): string
    {
        return match ($field->kind) {
            'boolean' => sprintf('<IconColumn field="%s" label="%s" boolean />', $field->name, $field->label),
            'text' => sprintf('<TextColumn field="%s" label="%s" limit="60" />', $field->name, $field->label),
            'date' => sprintf('<TextColumn field="%s" label="%s" date="YYYY-MM-DD" sortable />', $field->name, $field->label),
            'datetime' => sprintf('<TextColumn field="%s" label="%s" date-time="YYYY-MM-DD HH:mm" sortable />', $field->name, $field->label),
            'number' => sprintf('<TextColumn field="%s" label="%s" numeric sortable />', $field->name, $field->label),
            'email' => sprintf('<TextColumn field="%s" label="%s" searchable copyable />', $field->name, $field->label),
            default => sprintf('<TextColumn field="%s" label="%s" searchable sortable />', $field->name, $field->label),
        };
    }

    public static function tableColumnImport(FieldDescriptor $field): string
    {
        return $field->kind === 'boolean' ? 'IconColumn' : 'TextColumn';
    }

    public static function formField(FieldDescriptor $field): string
    {
        $camel = $field->camel();
        $error = "{$camel}Error";

        return match ($field->kind) {
            'boolean' => sprintf('<Checkbox v-model="%s" :error="%s" label="%s" />', $camel, $error, $field->label),
            'text' => sprintf('<Textarea v-model="%s" :error="%s" label="%s" rows="4" />', $camel, $error, $field->label),
            'date' => sprintf('<TextInput v-model="%s" :error="%s" type="date" label="%s" />', $camel, $error, $field->label),
            'datetime' => sprintf('<TextInput v-model="%s" :error="%s" type="datetime-local" label="%s" />', $camel, $error, $field->label),
            'number' => sprintf('<TextInput v-model="%s" :error="%s" type="number" label="%s" />', $camel, $error, $field->label),
            'email' => sprintf('<TextInput v-model="%s" :error="%s" type="email" label="%s" />', $camel, $error, $field->label),
            default => sprintf('<TextInput v-model="%s" :error="%s" label="%s" />', $camel, $error, $field->label),
        };
    }

    public static function formFieldImport(FieldDescriptor $field): string
    {
        return match ($field->kind) {
            'boolean' => 'Checkbox',
            'text' => 'Textarea',
            default => 'TextInput',
        };
    }

    public static function defaultValue(FieldDescriptor $field): string
    {
        return $field->kind === 'boolean' ? 'false' : "''";
    }

    public static function validationRuleLine(FieldDescriptor $field): string
    {
        $rules = match ($field->kind) {
            'boolean' => ['boolean'],
            'number' => ['numeric'],
            'date', 'datetime' => ['date'],
            'email' => ['email', 'max:255'],
            'text' => ['string'],
            default => ['string', 'max:255'],
        };

        array_unshift($rules, $field->nullable ? 'nullable' : 'required');

        $quoted = implode(', ', array_map(fn (string $rule) => "'{$rule}'", $rules));

        return "            '{$field->name}' => [{$quoted}],";
    }
}
