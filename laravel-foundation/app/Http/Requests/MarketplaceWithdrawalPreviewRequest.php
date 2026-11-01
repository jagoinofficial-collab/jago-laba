<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MarketplaceWithdrawalPreviewRequest extends FormRequest
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
            'store_id' => ['required', 'string', 'max:120'],
            'report' => ['required', 'file', 'max:10240'],
        ];
    }

    public function storeId(): string
    {
        return trim((string) $this->validated('store_id'));
    }
}
