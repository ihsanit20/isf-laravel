<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GeneralExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGeneralExpenseRequest;
use App\Http\Requests\Admin\UpdateGeneralExpenseRequest;
use App\Models\GeneralExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GeneralExpenseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/GeneralExpenses', [
            'expenseCategories' => GeneralExpenseCategory::options(),
            'generalExpenses' => GeneralExpense::query()
                ->with('createdBy:id,name')
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn(GeneralExpense $expense): array => [
                    'id' => $expense->id,
                    'expense_date' => $expense->expense_date?->format('Y-m-d'),
                    'category' => $expense->category->value,
                    'category_label' => $expense->category->label(),
                    'amount' => $expense->amount,
                    'description' => $expense->description,
                    'receipt_path' => $expense->receipt_path,
                    'receipt_url' => $expense->receiptUrl(),
                    'created_by_name' => $expense->createdBy?->name,
                    'created_at' => $expense->created_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ]);
    }

    public function store(StoreGeneralExpenseRequest $request): RedirectResponse
    {
        $receiptPath = $request->file('receipt')?->store('general-expense-attachments', GeneralExpense::attachmentDisk());

        GeneralExpense::query()->create([
            ...$request->safe()->only(['expense_date', 'category', 'amount', 'description']),
            'receipt_path' => $receiptPath,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.general-expenses.index');
    }

    public function update(UpdateGeneralExpenseRequest $request, GeneralExpense $generalExpense): RedirectResponse
    {
        $attributes = $request->safe()->only(['expense_date', 'category', 'amount', 'description']);

        if ($request->hasFile('receipt')) {
            if ($generalExpense->receipt_path !== null) {
                Storage::disk(GeneralExpense::attachmentDisk())->delete($generalExpense->receipt_path);
            }

            $attributes['receipt_path'] = $request->file('receipt')?->store('general-expense-attachments', GeneralExpense::attachmentDisk());
        }

        $generalExpense->update($attributes);

        return to_route('admin.general-expenses.index');
    }
}
