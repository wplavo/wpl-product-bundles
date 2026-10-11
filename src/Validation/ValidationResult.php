<?php

declare(strict_types=1);

namespace WPL\ProductBundles\Validation;

/**
 * Collects validation errors from a single validation pass.
 *
 * Mutable by design — validators append errors as they scan the config.
 */
final class ValidationResult {

    /** @var array<array{code: string, message: string, field: string|null}> */
    private array $errors = [];

    public function addError(string $code, string $message, ?string $field = null): self {
        $this->errors[] = [
            'code'    => $code,
            'message' => $message,
            'field'   => $field,
        ];
        return $this;
    }

    public function isValid(): bool {
        return empty($this->errors);
    }

    /** @return array<array{code: string, message: string, field: string|null}> */
    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstCode(): ?string {
        return $this->errors[0]['code'] ?? null;
    }

    public function getFirstMessage(): ?string {
        return $this->errors[0]['message'] ?? null;
    }

    /**
     * Merges all errors from another ValidationResult into this one.
     */
    public function merge(ValidationResult $other): void {
        foreach ($other->getErrors() as $error) {
            $this->errors[] = $error;
        }
    }

    /**
     * Returns all error codes, optionally filtered to a specific field.
     *
     * @return string[]
     */
    public function getCodes(?string $field = null): array {
        return array_column(
            $field === null
                ? $this->errors
                : array_filter($this->errors, fn($e) => $e['field'] === $field),
            'code'
        );
    }
}
