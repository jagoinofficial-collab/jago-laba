<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class KonveksiPilotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:dashboard'],
        ];
    }

    public function tab(): string
    {
        return (string) ($this->input('tab') ?: 'dashboard');
    }
}
