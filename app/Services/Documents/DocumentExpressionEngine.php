<?php

namespace App\Services\Documents;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;

/** Evaluates safe Document Studio conditions and arithmetic formulas without PHP eval. */
class DocumentExpressionEngine
{
    /** Evaluates a structured condition against the supplied render context. */
    public function condition(array $condition, array $context): bool
    {
        $left = $this->value((string) ($condition['path'] ?? $condition['left'] ?? ''), $context);
        $right = $condition['value'] ?? $condition['right'] ?? null;
        if (is_string($right) && str_starts_with($right, '$')) $right = $this->value(substr($right, 1), $context);
        $operator = (string) ($condition['operator'] ?? 'truthy');

        return match ($operator) {
            'eq' => $left == $right,
            'neq' => $left != $right,
            'gt' => $this->number($left) > $this->number($right),
            'gte' => $this->number($left) >= $this->number($right),
            'lt' => $this->number($left) < $this->number($right),
            'lte' => $this->number($left) <= $this->number($right),
            'contains' => str_contains($this->lower((string) $left), $this->lower((string) $right)),
            'empty' => blank($left),
            'not_empty' => filled($left),
            'falsy' => ! (bool) $left,
            default => (bool) $left,
        };
    }

    /** Resolves one dot-path from the render context. */
    public function value(string $path, array $context): mixed
    {
        if ($path === '') return null;
        return data_get($context, $path);
    }

    /** Evaluates a numeric formula containing context paths, literals and + - * / parentheses. */
    public function formula(string $expression, array $context): float
    {
        if (strlen($expression) > 500) throw $this->invalid('Formula is too long.');
        if (preg_match('/[A-Za-z_][A-Za-z0-9_.-]*\s*\(/', $expression)) throw $this->invalid('Formula functions are not supported.');
        $tokens = $this->tokenize($expression, $context);
        return $this->evaluatePostfix($this->toPostfix($tokens));
    }

    /** Converts the formula into numeric/operator tokens while resolving variable paths. */
    private function tokenize(string $expression, array $context): array
    {
        preg_match_all('/(?:[A-Za-z_][A-Za-z0-9_.-]*)|(?:\d+(?:\.\d+)?)|[()+\-*\/]/', $expression, $matches);
        $raw = $matches[0] ?? [];
        $compacted = preg_replace('/\s+/', '', $expression) ?? '';
        if (implode('', $raw) !== $compacted) throw $this->invalid('Formula contains unsupported characters.');

        $tokens = [];
        foreach ($raw as $token) {
            if (preg_match('/^[A-Za-z_]/', $token)) $tokens[] = (float) $this->number($this->value($token, $context));
            elseif (is_numeric($token)) $tokens[] = (float) $token;
            else $tokens[] = $token;
        }
        return $tokens;
    }

    /** Converts infix numeric tokens into postfix order using the shunting-yard algorithm. */
    private function toPostfix(array $tokens): array
    {
        $output = [];
        $operators = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        foreach ($tokens as $index => $token) {
            if (is_float($token) || is_int($token)) {
                $output[] = (float) $token;
                continue;
            }
            if ($token === '(') {
                $operators[] = $token;
                continue;
            }
            if ($token === ')') {
                while ($operators && end($operators) !== '(') $output[] = array_pop($operators);
                if (! $operators) throw $this->invalid('Formula parentheses are unbalanced.');
                array_pop($operators);
                continue;
            }
            if (! isset($precedence[$token])) throw $this->invalid('Unsupported formula operator.');
            if ($token === '-' && ($index === 0 || in_array($tokens[$index - 1], ['(', '+', '-', '*', '/'], true))) $output[] = 0.0;
            while ($operators && isset($precedence[end($operators)]) && $precedence[end($operators)] >= $precedence[$token]) $output[] = array_pop($operators);
            $operators[] = $token;
        }
        while ($operators) {
            $operator = array_pop($operators);
            if ($operator === '(') throw $this->invalid('Formula parentheses are unbalanced.');
            $output[] = $operator;
        }
        return $output;
    }

    /** Evaluates postfix arithmetic tokens and rejects division by zero or malformed expressions. */
    private function evaluatePostfix(array $tokens): float
    {
        $stack = [];
        foreach ($tokens as $token) {
            if (is_float($token) || is_int($token)) {
                $stack[] = (float) $token;
                continue;
            }
            if (count($stack) < 2) throw $this->invalid('Formula is incomplete.');
            $right = array_pop($stack);
            $left = array_pop($stack);
            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => abs($right) < 0.0000001 ? throw $this->invalid('Formula cannot divide by zero.') : $left / $right,
                default => throw $this->invalid('Unsupported formula operator.'),
            };
        }
        if (count($stack) !== 1) throw $this->invalid('Formula is incomplete.');
        return (float) $stack[0];
    }


    /** Builds a validation exception without requiring Laravel's validator facade/container binding. */
    private function invalid(string $message): ValidationException
    {
        $translator = new Translator(new ArrayLoader(), 'en');
        $validator = (new ValidatorFactory($translator))->make([], []);
        $validator->errors()->add('expression', $message);
        return new ValidationException($validator);
    }


    /** Lowercases condition text with mbstring when available and a conservative fallback otherwise. */
    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    /** Normalizes currency-formatted numeric values into a float. */
    private function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) return (float) $value;
        if (is_string($value)) {
            $normalized = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value)) ?? '0';
            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }
        return 0.0;
    }
}
