<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GeneralIncomeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGeneralIncomeRequest;
use App\Http\Requests\Admin\UpdateGeneralIncomeRequest;
use App\Models\GeneralIncome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GeneralIncomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/GeneralIncomes', [
            'incomeCategories' => GeneralIncomeCategory::options(),
            'generalIncomes' => GeneralIncome::query()
                ->with('createdBy:id,name')
                ->orderByDesc('income_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn(GeneralIncome $income): array => [
                    'id' => $income->id,
                    'income_date' => $income->income_date?->format('Y-m-d'),
                    'category' => $income->category->value,
                    'category_label' => $income->category->label(),
                    'amount' => $income->amount,
                    'description' => $income->description,
                    'receipt_path' => $income->receipt_path,
                    'receipt_url' => $income->receiptUrl(),
                    'created_by_name' => $income->createdBy?->name,
                    'created_at' => $income->created_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ]);
    }

    public function store(StoreGeneralIncomeRequest $request): RedirectResponse
    {
        $receiptPath = $request->file('receipt')?->store('general-income-attachments', GeneralIncome::attachmentDisk());

        GeneralIncome::query()->create([
            ...$request->safe()->only(['income_date', 'category', 'amount', 'description']),
            'receipt_path' => $receiptPath,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.general-incomes.index');
    }

    public function update(UpdateGeneralIncomeRequest $request, GeneralIncome $generalIncome): RedirectResponse
    {
        $attributes = $request->safe()->only(['income_date', 'category', 'amount', 'description']);

        if ($request->hasFile('receipt')) {
            if ($generalIncome->receipt_path !== null) {
                Storage::disk(GeneralIncome::attachmentDisk())->delete($generalIncome->receipt_path);
            }

            $attributes['receipt_path'] = $request->file('receipt')?->store('general-income-attachments', GeneralIncome::attachmentDisk());
        }

        $generalIncome->update($attributes);

        return to_route('admin.general-incomes.index');
    }
}
