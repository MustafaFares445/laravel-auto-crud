<?php

namespace Mrmarchone\LaravelAutoCrud\Enums;

enum ResponseMessages: string
{
    case RETRIEVED = 'data retrieved successfully.';

    case CREATED = 'data created successfully.';

    case UPDATED = 'data updated successfully.';

    case DELETED = 'data deleted successfully.';

    public function message(): string
    {
        return __(config('laravel_auto_crud.response_messages.' . strtolower($this->name)) ?? $this->value);
    }
}
