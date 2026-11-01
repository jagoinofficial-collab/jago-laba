<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MarketplaceAnalyticsRequest extends FormRequest
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
            'store' => ['nullable', 'string', 'max:120'],
            'store_id' => ['nullable', 'string', 'max:120'],
            'period' => ['nullable', 'string', 'in:today,yesterday,week,month,last_month,custom'],
            'date_start' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom'],
            'date_end' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom'],
            'date_basis' => ['nullable', 'string', 'in:created,released'],
            'analytics_mode' => ['nullable', 'string', 'in:all,settled'],
        ];
    }

    public function storeId(): ?string
    {
        $value = $this->input('store_id', $this->input('store'));

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter([
            'period' => (string) ($this->input('period') ?: 'month'),
            'date_start' => (string) ($this->input('date_start') ?: ''),
            'date_end' => (string) ($this->input('date_end') ?: ''),
            'date_basis' => (string) ($this->input('date_basis') ?: 'created'),
            'analytics_mode' => (string) ($this->input('analytics_mode') ?: 'all'),
        ], static fn (string $value): bool => $value !== '');
    }
}
