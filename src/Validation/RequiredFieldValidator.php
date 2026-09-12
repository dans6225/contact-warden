<?php

declare(strict_types=1);

namespace ContactWarden\Validation;

/**
 * Field-level validation for re-rendering a form with errors — deliberately
 * separate from scoring: a submission can be well-formed (every required
 * field present, a valid email) and still get REJECTed, or malformed and
 * never reach the Engine at all. Run this first; only hand well-formed
 * submissions to Engine::handle().
 */
final class RequiredFieldValidator
{
    /**
     * @param array<string,string> $fields field name => trimmed value
     * @param string[] $requiredFields which of $fields must be non-empty
     * @param array<string,string> $messages optional field => custom message,
     *   keyed by field name for "required" errors and by "<field>_invalid"
     *   for format errors (e.g. 'email_invalid')
     *
     * @return array<string,string> field => error message; empty if valid
     */
    public static function validate(array $fields, array $requiredFields, array $messages = []): array
    {
        $errors = [];

        foreach ($requiredFields as $field) {
            if (($fields[$field] ?? '') === '') {
                $errors[$field] = $messages[$field] ?? sprintf('Please enter your %s.', $field);
            }
        }

        $email = $fields['email'] ?? '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = $messages['email_invalid'] ?? 'Please enter a valid email address.';
        }

        return $errors;
    }
}
