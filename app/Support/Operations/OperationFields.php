<?php

namespace App\Support\Operations;

use Illuminate\Validation\Rule;

/**
 * config/operations.php'dagi amalning qo'shimcha maydonlari ('fields'):
 *
 *   'only'  => ['type' => 'select', 'label' => ..., 'option' => '--only', 'empty' => 'Hammasi',
 *               'choices' => [Klass::class, 'metod']],   // kalit => nomi qaytaradi
 *   'force' => ['type' => 'checkbox', 'label' => ..., 'option' => '--force', 'tag' => 'qayta'],
 *
 * Tanlangan qiymatlar JobRun->params'da saqlanadi va buyruqqa tegishli
 * opsiya sifatida beriladi. Config keshlanishi mumkin bo'lishi uchun
 * 'choices' closure emas, [klass, metod] ko'rinishida.
 */
class OperationFields
{
    /** Sahifa uchun: 'choices' hisoblangan holda */
    public static function resolve(array $operation): array
    {
        $fields = [];

        foreach ($operation['fields'] ?? [] as $name => $field) {
            if (($field['type'] ?? null) === 'select') {
                $field['choices'] = call_user_func($field['choices']);
            }

            $fields[$name] = $field;
        }

        return $fields;
    }

    public static function rules(array $operation): array
    {
        $rules = [];

        foreach (self::resolve($operation) as $name => $field) {
            $rules[$name] = $field['type'] === 'select'
                ? ['nullable', 'string', Rule::in(array_keys($field['choices']))]
                : ['nullable', 'boolean'];
        }

        return $rules;
    }

    /** Validatsiyadan o'tgan qiymatlardan params uchun faqat tanlanganlari */
    public static function values(array $operation, array $validated): array
    {
        $values = [];

        foreach ($operation['fields'] ?? [] as $name => $field) {
            $value = $validated[$name] ?? null;

            if ($field['type'] === 'select' && $value !== null && $value !== '') {
                $values[$name] = $value;
            } elseif ($field['type'] === 'checkbox' && filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $values[$name] = true;
            }
        }

        return $values;
    }

    /** params -> buyruq opsiyalari, masalan ['--only' => 'zitic', '--force' => true] */
    public static function options(array $operation, array $params): array
    {
        $options = [];

        foreach ($operation['fields'] ?? [] as $name => $field) {
            if (array_key_exists($name, $params)) {
                $options[$field['option']] = $params[$name];
            }
        }

        return $options;
    }

    /** "So'nggi ishlar"da ko'rsatish uchun qisqa yorliqlar, masalan ['Zitic', 'qayta'] */
    public static function tags(array $operation, array $params): array
    {
        $tags = [];

        foreach (self::resolve($operation) as $name => $field) {
            if (! array_key_exists($name, $params)) {
                continue;
            }

            $tags[] = $field['type'] === 'select'
                ? ($field['choices'][$params[$name]] ?? $params[$name])
                : ($field['tag'] ?? $field['label']);
        }

        return $tags;
    }
}
