<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketplaceReportRequest;
use App\Http\Responses\MarketplaceReportResponse;
use App\Marketplace\ExpensesReadService;

final class MarketplaceExpensesReadController
{
    public function __invoke(MarketplaceReportRequest $request, ExpensesReadService $expenses): mixed
    {
        try {
            $data = $expenses->listing((string) $request->attributes->get('company_key'), $request->validated('store_id'), $request->validated());
        } catch (\DomainException $exception) { abort(403, $exception->getMessage()); }

        return MarketplaceReportResponse::make($request, 'Pengeluaran', $data, 'expenses', [
            'expense_date' => 'Tanggal', 'description' => 'Keterangan', 'category' => 'Kategori', 'amount' => 'Nominal',
            'vendor_name' => 'Vendor', 'affects_profit' => 'Mempengaruhi laba', 'is_cashflow_only' => 'Arus kas saja',
        ]);
    }
}
