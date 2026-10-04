<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Account;
use App\Ledger\Money;
use App\Models\BusinessTransaction;
use App\Models\ChargeAllocation;
use App\Models\CycleInvestment;
use App\Models\CycleInvestmentCharge;
use App\Models\DepositSubmission;
use App\Models\EventBankDeposit;
use App\Models\EventBankWithdrawal;
use App\Models\EventExpense;
use App\Models\EventIncome;
use App\Models\EventPayment;
use App\Models\EventRefund;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleTransaction;
use App\Models\GeneralExpense;
use App\Models\GeneralIncome;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Member;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only browser over the double-entry journal: search, filters, export.
 */
class JournalController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    private const STATUSES = ['active', 'reversed', 'reversal'];

    private const SORTS = ['date_desc', 'date_asc', 'posted_desc', 'amount_desc', 'amount_asc'];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $lineFilters = $this->lineFilters($filters);

        $entries = $this->sorted($this->filteredQuery($filters), $filters['sort'])
            ->with([
                'lines' => fn ($query) => $query->orderBy('id'),
                'lines.ledgerAccount:id,code,name,type',
                'lines.user:id,name',
                'lines.member:id,full_name',
                'lines.fundCycle:id,name',
                'lines.cycleInvestment:id,title,type',
                'lines.order:id,order_number,fund_cycle_event_id',
                'postedBy:id,name',
                'reversal:id,reversal_of_id',
                'source' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    EventPayment::class => ['order:id,fund_cycle_event_id'],
                    EventRefund::class => ['order:id,fund_cycle_event_id'],
                    CycleInvestment::class => ['event:id,cycle_investment_id'],
                    CycleInvestmentCharge::class => ['investment.event:id,cycle_investment_id'],
                ]),
            ])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (JournalEntry $entry): array => $this->transformEntry($entry, $lineFilters));

        return Inertia::render('admin/Journal', [
            'entries' => $entries,
            'summary' => $this->summary($filters, $lineFilters),
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $entryIds = $this->filteredQuery($filters)->select('journal_entries.id');

        $lines = JournalLine::query()
            ->whereIn('journal_entry_id', $entryIds)
            ->with([
                'entry:id,entry_date,kind,description,source_type,source_id,reversal_of_id,posted_by_user_id,posted_at',
                'entry.postedBy:id,name',
                'entry.reversal:id,reversal_of_id',
                'ledgerAccount:id,code,name',
                'user:id,name',
                'member:id,full_name',
                'fundCycle:id,name',
                'cycleInvestment:id,title',
                'order:id,order_number',
            ])
            ->orderBy('journal_entry_id')
            ->orderBy('id');

        return response()->streamDownload(function () use ($lines): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so spreadsheet apps read Bengali names correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Entry', 'Date', 'Kind', 'Description', 'Source', 'Reverses', 'Reversed by',
                'Account code', 'Account', 'Debit', 'Credit',
                'User', 'Member', 'Cycle', 'Project', 'Order', 'Memo', 'Posted by', 'Posted at',
            ]);

            $lines->lazy(500)->each(function (JournalLine $line) use ($handle): void {
                $entry = $line->entry;

                fputcsv($handle, [
                    $entry->id,
                    $entry->entry_date?->format('Y-m-d'),
                    $entry->kind,
                    $entry->description,
                    $entry->source_type ? class_basename($entry->source_type).' #'.$entry->source_id : '',
                    $entry->reversal_of_id,
                    $entry->reversal?->id,
                    $line->ledgerAccount?->code,
                    $line->ledgerAccount?->name,
                    Money::toTaka($line->debit),
                    Money::toTaka($line->credit),
                    $line->user?->name,
                    $line->member?->full_name,
                    $line->fundCycle?->name,
                    $line->cycleInvestment?->title,
                    $line->order?->order_number,
                    $line->memo,
                    $entry->postedBy?->name ?? 'System',
                    $entry->posted_at?->format('Y-m-d H:i:s'),
                ]);
            });

            fclose($handle);
        }, 'journal-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $perPage = $request->integer('per_page', 25);
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $accountType = $request->string('account_type')->toString();
        $scope = $request->string('scope')->toString();

        return [
            'search' => trim($request->string('search')->toString()),
            'kind' => $request->string('kind')->toString(),
            'source' => $request->string('source')->toString(),
            'account' => Account::tryFrom($request->string('account')->toString())?->value ?? '',
            'account_type' => in_array($accountType, ['asset', 'liability', 'equity', 'income', 'expense'], true) ? $accountType : '',
            'scope' => in_array($scope, ['fund', 'platform'], true) ? $scope : '',
            'cycle' => $request->integer('cycle') ?: null,
            'investment' => $request->integer('investment') ?: null,
            'user' => $request->integer('user') ?: null,
            'member' => $request->integer('member') ?: null,
            'posted_by' => $request->string('posted_by')->toString(),
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
            'from_date' => $this->dateOrEmpty($request->string('from_date')->toString()),
            'to_date' => $this->dateOrEmpty($request->string('to_date')->toString()),
            'min_amount' => $request->filled('min_amount') ? max(0, (float) $request->input('min_amount')) : null,
            'max_amount' => $request->filled('max_amount') ? max(0, (float) $request->input('max_amount')) : null,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'date_desc',
            'per_page' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25,
        ];
    }

    /**
     * Filters that apply to a single journal line. An entry matches when at
     * least one of its lines satisfies all of them together.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function lineFilters(array $filters): array
    {
        return array_filter([
            'account' => $filters['account'],
            'account_type' => $filters['account_type'],
            'scope' => $filters['scope'],
            'fund_cycle_id' => $filters['cycle'],
            'cycle_investment_id' => $filters['investment'],
            'user_id' => $filters['user'],
            'member_id' => $filters['member'],
        ], fn ($value): bool => $value !== '' && $value !== null);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $lineFilters = $this->lineFilters($filters);

        return JournalEntry::query()
            ->when($filters['search'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['search']))
            ->when($filters['kind'] !== '', fn (Builder $query) => $query->where('kind', $filters['kind']))
            ->when($filters['source'] !== '', fn (Builder $query) => $query->where('source_type', $filters['source']))
            ->when($filters['posted_by'] === 'system', fn (Builder $query) => $query->whereNull('posted_by_user_id'))
            ->when(ctype_digit($filters['posted_by']), fn (Builder $query) => $query->where('posted_by_user_id', (int) $filters['posted_by']))
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->whereNull('reversal_of_id')->whereDoesntHave('reversal'))
            ->when($filters['status'] === 'reversed', fn (Builder $query) => $query->whereHas('reversal'))
            ->when($filters['status'] === 'reversal', fn (Builder $query) => $query->whereNotNull('reversal_of_id'))
            ->when($filters['from_date'] !== '', fn (Builder $query) => $query->whereDate('entry_date', '>=', $filters['from_date']))
            ->when($filters['to_date'] !== '', fn (Builder $query) => $query->whereDate('entry_date', '<=', $filters['to_date']))
            ->when($filters['min_amount'] !== null, fn (Builder $query) => $query->whereIn('id', $this->entryIdsByTotal('>=', $filters['min_amount'])))
            ->when($filters['max_amount'] !== null, fn (Builder $query) => $query->whereIn('id', $this->entryIdsByTotal('<=', $filters['max_amount'])))
            ->when($lineFilters !== [], fn (Builder $query) => $query->whereHas(
                'lines',
                fn (Builder $lines) => $this->applyLineFilters($lines, $lineFilters),
            ));
    }

    /**
     * `#123` or `123` finds the entry by id; anything else matches text on the
     * entry, its lines, and the people / order those lines point to.
     */
    private function applySearch(Builder $query, string $search): void
    {
        $entryId = preg_match('/^#?(\d+)$/', $search, $matches) === 1 ? (int) $matches[1] : null;
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $query->where(function (Builder $query) use ($entryId, $like): void {
            if ($entryId !== null) {
                $query->whereKey($entryId)->orWhere('reversal_of_id', $entryId);
            }

            $query
                ->orWhere('description', 'like', $like)
                ->orWhere('kind', 'like', $like)
                ->orWhere('idempotency_key', 'like', $like)
                ->orWhereHas('lines', fn (Builder $lines) => $lines->where(fn (Builder $line) => $line
                    ->where('memo', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user->where(fn (Builder $user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)))
                    ->orWhereHas('member', fn (Builder $member) => $member->where('full_name', 'like', $like))
                    ->orWhereHas('order', fn (Builder $order) => $order->where(fn (Builder $order) => $order
                        ->where('order_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like)))));
        });
    }

    /**
     * @param  array<string, mixed>  $lineFilters
     */
    private function applyLineFilters(Builder $lines, array $lineFilters): void
    {
        foreach ($lineFilters as $key => $value) {
            match ($key) {
                'account' => $lines->whereHas('ledgerAccount', fn (Builder $account) => $account->where('code', $value)),
                'account_type' => $lines->whereHas('ledgerAccount', fn (Builder $account) => $account->where('type', $value)),
                'scope' => $lines->whereHas('ledgerAccount', fn (Builder $account) => $account->where('scope', $value)),
                default => $lines->where('journal_lines.'.$key, $value),
            };
        }
    }

    private function entryIdsByTotal(string $operator, float $amount): Builder
    {
        return JournalLine::query()
            ->select('journal_entry_id')
            ->groupBy('journal_entry_id')
            ->havingRaw("SUM(debit) {$operator} ?", [Money::toPaisa($amount)]);
    }

    private function sorted(Builder $query, string $sort): Builder
    {
        $total = JournalLine::query()
            ->selectRaw('SUM(debit)')
            ->whereColumn('journal_lines.journal_entry_id', 'journal_entries.id');

        return match ($sort) {
            'date_asc' => $query->orderBy('entry_date')->orderBy('id'),
            'posted_desc' => $query->orderByDesc('posted_at')->orderByDesc('id'),
            'amount_desc' => $query->orderByDesc($total)->orderByDesc('id'),
            'amount_asc' => $query->orderBy($total)->orderBy('id'),
            default => $query->orderByDesc('entry_date')->orderByDesc('id'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $lineFilters
     * @return array<string, mixed>
     */
    private function summary(array $filters, array $lineFilters): array
    {
        $entryIds = $this->filteredQuery($filters)->select('journal_entries.id');

        $totals = JournalLine::query()
            ->whereIn('journal_entry_id', $entryIds)
            ->selectRaw('COUNT(DISTINCT journal_entry_id) as entries, COALESCE(SUM(debit), 0) as debit')
            ->first();

        $summary = [
            'entries' => (int) $totals->entries,
            'total' => Money::toTaka((int) $totals->debit),
            'matched' => null,
        ];

        if ($lineFilters !== []) {
            $matched = JournalLine::query()
                ->whereIn('journal_entry_id', $this->filteredQuery($filters)->select('journal_entries.id'))
                ->tap(fn (Builder $lines) => $this->applyLineFilters($lines, $lineFilters))
                ->selectRaw('COUNT(*) as line_count, COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
                ->first();

            $summary['matched'] = [
                'lines' => (int) $matched->line_count,
                'debit' => Money::toTaka((int) $matched->debit),
                'credit' => Money::toTaka((int) $matched->credit),
                'net' => Money::toTaka((int) $matched->debit - (int) $matched->credit),
            ];
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $lineFilters
     * @return array<string, mixed>
     */
    private function transformEntry(JournalEntry $entry, array $lineFilters): array
    {
        return [
            'id' => $entry->id,
            'entry_date' => $entry->entry_date?->format('Y-m-d'),
            'kind' => $entry->kind,
            'description' => $entry->description,
            'idempotency_key' => $entry->idempotency_key,
            'reversal_of_id' => $entry->reversal_of_id,
            'reversed_by_id' => $entry->reversal?->id,
            'posted_by' => $entry->postedBy?->name,
            'posted_at' => $entry->posted_at?->format('d M Y, h:i A'),
            'total' => Money::toTaka((int) $entry->lines->sum('debit')),
            'source' => $this->sourceLink($entry),
            'lines' => $entry->lines->map(fn (JournalLine $line): array => [
                'id' => $line->id,
                'code' => $line->ledgerAccount?->code,
                'account' => $line->ledgerAccount?->name,
                'debit' => Money::toTaka($line->debit),
                'credit' => Money::toTaka($line->credit),
                'memo' => $line->memo,
                'matches' => $lineFilters !== [] && $this->lineMatches($line, $lineFilters),
                'dimensions' => array_values(array_filter([
                    $line->user ? ['type' => 'user', 'id' => $line->user->id, 'label' => $line->user->name] : null,
                    $line->member ? ['type' => 'member', 'id' => $line->member->id, 'label' => $line->member->full_name] : null,
                    $line->fundCycle ? ['type' => 'cycle', 'id' => $line->fundCycle->id, 'label' => $line->fundCycle->name] : null,
                    $line->cycleInvestment ? ['type' => 'investment', 'id' => $line->cycleInvestment->id, 'label' => $line->cycleInvestment->title] : null,
                    $line->order ? ['type' => 'order', 'id' => $line->order->id, 'label' => $line->order->order_number] : null,
                ])),
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $lineFilters
     */
    private function lineMatches(JournalLine $line, array $lineFilters): bool
    {
        $account = $line->ledgerAccount;

        foreach ($lineFilters as $key => $value) {
            $matches = match ($key) {
                'account' => $account?->code === $value,
                'account_type' => $account?->type === $value,
                'scope' => Account::tryFrom((string) $account?->code)?->scope() === $value,
                default => (int) $line->{$key} === (int) $value,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{label: string, url: string|null}|null
     */
    private function sourceLink(JournalEntry $entry): ?array
    {
        if ($entry->source_type === null) {
            return null;
        }

        $label = $this->sourceTypeLabel($entry->source_type).' #'.$entry->source_id;
        $source = $entry->source;

        if ($source === null) {
            return ['label' => $label.' (removed)', 'url' => null];
        }

        $url = match (true) {
            $source instanceof DepositSubmission => '/admin/deposits',
            $source instanceof ChargeAllocation => '/admin/charges',
            $source instanceof PayoutRequest => '/admin/payouts',
            $source instanceof GeneralIncome => '/admin/general-incomes',
            $source instanceof GeneralExpense => '/admin/general-expenses',
            $source instanceof FundCycle => '/admin/fund-cycles/'.$source->id,
            $source instanceof FundCycleTransaction => '/admin/fund-cycles/'.$source->fund_cycle_id,
            $source instanceof FundCycleAllocation => '/admin/fund-cycles/'.$source->fund_cycle_id.'/allocations',
            $source instanceof EventBankWithdrawal => '/admin/events/'.$source->fund_cycle_event_id.'?tab=withdrawals',
            $source instanceof EventBankDeposit => '/admin/events/'.$source->fund_cycle_event_id.'?tab=deposits',
            $source instanceof EventExpense => '/admin/events/'.$source->fund_cycle_event_id.'?tab=costs',
            $source instanceof EventIncome => '/admin/events/'.$source->fund_cycle_event_id.'?tab=accounts',
            $source instanceof EventPayment, $source instanceof EventRefund => $source->order
                ? '/admin/events/'.$source->order->fund_cycle_event_id.'/orders/'.$source->event_order_id
                : null,
            $source instanceof BusinessTransaction => '/admin/businesses/'.$source->cycle_investment_id,
            $source instanceof CycleInvestmentCharge => $this->investmentUrl($source->investment),
            $source instanceof CycleInvestment => $this->investmentUrl($source),
            default => null,
        };

        return ['label' => $label, 'url' => $url];
    }

    private function investmentUrl(?CycleInvestment $investment): ?string
    {
        if ($investment === null) {
            return null;
        }

        if ($investment->type === 'event') {
            return $investment->event ? '/admin/events/'.$investment->event->id.'?tab=accounts' : null;
        }

        return '/admin/businesses/'.$investment->id;
    }

    private function sourceTypeLabel(string $sourceType): string
    {
        return str(class_basename($sourceType))->headline()->toString();
    }

    private function dateOrEmpty(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $option = fn ($value, string $label): array => ['value' => (string) $value, 'label' => $label];

        $lineUserIds = JournalLine::query()->whereNotNull('user_id')->distinct()->select('user_id');
        $lineMemberIds = JournalLine::query()->whereNotNull('member_id')->distinct()->select('member_id');

        return [
            'kinds' => JournalEntry::query()->distinct()->orderBy('kind')->pluck('kind')
                ->map(fn (string $kind): array => $option($kind, $kind))->values(),
            'sources' => JournalEntry::query()->whereNotNull('source_type')->distinct()->orderBy('source_type')->pluck('source_type')
                ->map(fn (string $type): array => $option($type, $this->sourceTypeLabel($type)))->values(),
            'accounts' => collect(Account::cases())
                ->map(fn (Account $account): array => $option($account->value, $account->value.' · '.$account->label()))->values(),
            'account_types' => collect(['asset', 'liability', 'equity', 'income', 'expense'])
                ->map(fn (string $type): array => $option($type, ucfirst($type)))->values(),
            'scopes' => [$option('fund', 'Fund (cycle)'), $option('platform', 'Platform')],
            'cycles' => FundCycle::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn (FundCycle $cycle): array => $option($cycle->id, $cycle->name))->values(),
            'investments' => CycleInvestment::query()->orderByDesc('id')->get(['id', 'fund_cycle_id', 'title', 'type'])
                ->map(fn (CycleInvestment $investment): array => [
                    ...$option($investment->id, $investment->title.' ('.$investment->type.')'),
                    'cycle' => (string) $investment->fund_cycle_id,
                ])->values(),
            'users' => User::query()->whereIn('id', $lineUserIds)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => $option($user->id, $user->name))->values(),
            'members' => Member::query()->whereIn('id', $lineMemberIds)->orderBy('full_name')->get(['id', 'full_name'])
                ->map(fn (Member $member): array => $option($member->id, $member->full_name))->values(),
            'posted_by' => User::query()
                ->whereIn('id', JournalEntry::query()->whereNotNull('posted_by_user_id')->distinct()->select('posted_by_user_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => $option($user->id, $user->name))
                ->prepend($option('system', 'System'))
                ->values(),
            'statuses' => [
                $option('active', 'Active'),
                $option('reversed', 'Reversed'),
                $option('reversal', 'Reversal entries'),
            ],
            'sorts' => [
                $option('date_desc', 'Newest date first'),
                $option('date_asc', 'Oldest date first'),
                $option('posted_desc', 'Recently posted'),
                $option('amount_desc', 'Largest amount'),
                $option('amount_asc', 'Smallest amount'),
            ],
            'per_page' => array_map(fn (int $size): array => $option($size, (string) $size), self::PER_PAGE_OPTIONS),
        ];
    }
}
