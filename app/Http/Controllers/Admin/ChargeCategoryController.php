<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChargeCategoryRequest;
use App\Http\Requests\Admin\UpdateChargeCategoryRequest;
use App\Models\ChargeCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChargeCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ChargeCategories', [
            'chargeCategories' => ChargeCategory::query()
                ->orderByDesc('code')
                ->orderBy('title')
                ->get()
                ->map(fn(ChargeCategory $category): array => [
                    'id' => $category->id,
                    'code' => $category->code,
                    'title' => $category->title,
                    'default_amount' => $category->default_amount,
                    'is_active' => $category->is_active,
                    'is_system' => $category->code === ChargeCategory::CODE_REGISTRATION_FEE,
                    'created_at' => $category->created_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ]);
    }

    public function store(StoreChargeCategoryRequest $request): RedirectResponse
    {
        ChargeCategory::query()->create($request->validated());

        return to_route('admin.charge-categories.index');
    }

    public function update(UpdateChargeCategoryRequest $request, ChargeCategory $chargeCategory): RedirectResponse
    {
        $chargeCategory->update($request->validated());

        return to_route('admin.charge-categories.index');
    }
}
