<?php

namespace App\Core;

/**
 * Compact rule-based validator.
 *
 * Usage:
 *   $v = new Validator($request->all(), [
 *       'name'  => 'required|string|max:150',
 *       'price' => 'nullable|decimal|min:0',
 *       'type'  => 'required|in:imported,local,custom',
 *   ]);
 *   if ($v->fails()) { ...$v->errors()... }
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
        $this->run();
    }

    public function fails(): bool { return $this->errors !== []; }
    public function passes(): bool { return $this->errors === []; }
    public function errors(): array { return $this->errors; }

    /** Validated subset of data (only fields that had rules). */
    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules    = explode('|', $ruleString);
            $value    = $this->data[$field] ?? null;
            $nullable = in_array('nullable', $rules, true);
            $isEmpty  = ($value === null || $value === '');

            if ($isEmpty && $nullable) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $name, $param);
            }
        }
    }

    private function applyRule(string $field, $value, string $rule, ?string $param): void
    {
        $label = ucwords(str_replace('_', ' ', $field));

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && !$value)) {
                    $this->add($field, "$label is required.");
                }
                break;
            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->add($field, "$label must be text.");
                }
                break;
            case 'numeric':
            case 'decimal':
                if ($value !== '' && $value !== null && !is_numeric($value)) {
                    $this->add($field, "$label must be a number.");
                }
                break;
            case 'integer':
                if ($value !== '' && $value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->add($field, "$label must be a whole number.");
                }
                break;
            case 'min':
                if (is_numeric($value) && (float) $value < (float) $param) {
                    $this->add($field, "$label must be at least $param.");
                } elseif (is_string($value) && mb_strlen($value) < (int) $param) {
                    $this->add($field, "$label must be at least $param characters.");
                }
                break;
            case 'max':
                if (is_numeric($value) && (float) $value > (float) $param) {
                    $this->add($field, "$label must not exceed $param.");
                } elseif (is_string($value) && mb_strlen($value) > (int) $param) {
                    $this->add($field, "$label must not exceed $param characters.");
                }
                break;
            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->add($field, "$label is invalid.");
                }
                break;
            case 'email':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->add($field, "$label must be a valid email.");
                }
                break;
        }
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
