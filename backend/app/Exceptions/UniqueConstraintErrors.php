<?php

namespace App\Exceptions;

use Illuminate\Database\UniqueConstraintViolationException;

class UniqueConstraintErrors
{
    public static function validationErrors(UniqueConstraintViolationException $exception): ?array
    {
        // Match only known business constraints; never turn unrelated SQL errors into validation errors.
        // Ignore DETAIL values, which may contain user-supplied text resembling a constraint name.
        $detail = explode("\n", (string) ($exception->errorInfo[2] ?? ''), 2)[0];
        foreach ([
            ['tenants_slug_unique', 'tenants.slug', 'slug'],
            ['users_tenant_id_email_unique', 'users.tenant_id, users.email', 'email'],
            ['forms_tenant_id_name_unique', 'forms.tenant_id, forms.name', 'name'],
            ['form_fields_form_version_id_name_unique', 'form_fields.form_version_id, form_fields.name', 'name'],
        ] as [$postgresConstraint, $sqliteColumns, $field]) {
            if (str_contains($detail, '"'.$postgresConstraint.'"')
                || str_contains($detail, 'UNIQUE constraint failed: '.$sqliteColumns)) {
                return [$field => ['O valor informado já está cadastrado neste contexto.']];
            }
        }

        return null;
    }
}
