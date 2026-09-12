<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Validation;

use ContactWarden\Validation\RequiredFieldValidator;
use PHPUnit\Framework\TestCase;

final class RequiredFieldValidatorTest extends TestCase
{
    public function test_no_errors_when_all_required_fields_present(): void
    {
        $errors = RequiredFieldValidator::validate(
            ['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Hi'],
            ['name', 'email', 'message'],
        );

        $this->assertSame([], $errors);
    }

    public function test_reports_missing_required_fields(): void
    {
        $errors = RequiredFieldValidator::validate(
            ['name' => '', 'email' => 'jane@example.com', 'message' => ''],
            ['name', 'email', 'message'],
        );

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('message', $errors);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function test_field_absent_from_array_counts_as_missing(): void
    {
        $errors = RequiredFieldValidator::validate([], ['name']);

        $this->assertArrayHasKey('name', $errors);
    }

    public function test_only_fields_marked_required_are_checked(): void
    {
        $errors = RequiredFieldValidator::validate(['name' => '', 'email' => 'jane@example.com'], ['email']);

        $this->assertSame([], $errors);
    }

    public function test_custom_message_used_when_provided(): void
    {
        $errors = RequiredFieldValidator::validate([], ['name'], ['name' => 'Name, please.']);

        $this->assertSame('Name, please.', $errors['name']);
    }

    public function test_invalid_email_format_is_rejected_even_when_not_required(): void
    {
        $errors = RequiredFieldValidator::validate(['email' => 'not-an-email'], []);

        $this->assertArrayHasKey('email', $errors);
    }

    public function test_empty_email_is_not_a_format_error(): void
    {
        $errors = RequiredFieldValidator::validate(['email' => ''], []);

        $this->assertSame([], $errors);
    }

    public function test_custom_email_invalid_message(): void
    {
        $errors = RequiredFieldValidator::validate(
            ['email' => 'nope'],
            [],
            ['email_invalid' => 'That email looks off.'],
        );

        $this->assertSame('That email looks off.', $errors['email']);
    }
}
