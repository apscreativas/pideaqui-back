<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        $from = $request->date_from ? Carbon::parse($request->date_from)->toDateString() : now()->startOfMonth()->toDateString();
        $to = $request->date_to ? Carbon::parse($request->date_to)->toDateString() : now()->toDateString();

        $query = Expense::query()
            ->with(['category:id,name', 'subcategory:id,name,expense_category_id', 'branch:id,name', 'creator:id,name', 'attachments'])
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('expense_date', [$from, $to])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->subcategory_id, fn ($q, $id) => $q->where('expense_subcategory_id', $id))
            ->when($request->min_amount, fn ($q, $v) => $q->where('amount', '>=', $v))
            ->when($request->max_amount, fn ($q, $v) => $q->where('amount', '<=', $v))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        $expenses = $query->get();

        $categories = ExpenseCategory::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['subcategories' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $totals = [
            'count' => $expenses->count(),
            'total' => round((float) $expenses->sum('amount'), 2),
            'avg' => $expenses->count() > 0 ? round((float) $expenses->avg('amount'), 2) : 0,
            'by_category' => $expenses->groupBy('expense_category_id')->map(function ($group) use ($categories) {
                $cat = $categories->firstWhere('id', $group->first()->expense_category_id);

                return [
                    'name' => $cat?->name ?? '—',
                    'count' => $group->count(),
                    'total' => round((float) $group->sum('amount'), 2),
                ];
            })->values(),
        ];

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'branches' => $branches,
            'filters' => [
                'date_from' => $from,
                'date_to' => $to,
                'branch_id' => $request->branch_id,
                'category_id' => $request->category_id,
                'subcategory_id' => $request->subcategory_id,
                'min_amount' => $request->min_amount,
                'max_amount' => $request->max_amount,
            ],
            'totals' => $totals,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Expense::class);

        $restaurantId = $request->user()->restaurant_id;

        $categories = ExpenseCategory::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with(['subcategories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Expenses/Create', [
            'categories' => $categories,
            'branches' => $branches,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $user = $request->user();
        $data = $request->validated();

        $expense = DB::transaction(function () use ($data, $user, $request) {
            $expense = Expense::create([
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $data['branch_id'],
                'expense_category_id' => $data['expense_category_id'],
                'expense_subcategory_id' => $data['expense_subcategory_id'],
                'created_by_user_id' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
            ]);

            $this->storeAttachments($expense, $request->file('attachments', []));

            return $expense;
        });

        return redirect()
            ->route('expenses.show', $expense->id)
            ->with('success', 'Gasto registrado.');
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'subcategory', 'branch:id,name', 'creator:id,name', 'attachments']);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense,
        ]);
    }

    public function edit(Expense $expense, Request $request): Response
    {
        $this->authorize('update', $expense);

        $expense->load(['attachments']);

        $restaurantId = $request->user()->restaurant_id;

        $categories = ExpenseCategory::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['subcategories' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense,
            'categories' => $categories,
            'branches' => $branches,
            'today' => now()->toDateString(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $data = $request->validated();

        DB::transaction(function () use ($expense, $data, $request) {
            $expense->update([
                'branch_id' => $data['branch_id'],
                'expense_category_id' => $data['expense_category_id'],
                'expense_subcategory_id' => $data['expense_subcategory_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
            ]);

            $this->storeAttachments($expense, $request->file('attachments', []));
        });

        return redirect()->route('expenses.show', $expense->id)->with('success', 'Gasto actualizado.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $disk = config('filesystems.media_disk', 'public');

        DB::transaction(function () use ($expense, $disk) {
            foreach ($expense->attachments as $att) {
                Storage::disk($disk)->delete($att->file_path);
            }
            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', 'Gasto eliminado.');
    }

    public function destroyAttachment(ExpenseAttachment $attachment, Request $request): RedirectResponse
    {
        $expense = $attachment->expense;
        $this->authorize('update', $expense);

        Storage::disk(config('filesystems.media_disk', 'public'))->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Archivo eliminado.');
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storeAttachments(Expense $expense, array $files): void
    {
        if (empty($files)) {
            return;
        }

        $disk = config('filesystems.media_disk', 'public');
        foreach ($files as $file) {
            $path = $file->store("expenses/{$expense->id}", $disk);

            $expense->attachments()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }
}
