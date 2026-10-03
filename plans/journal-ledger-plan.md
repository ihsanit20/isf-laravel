# Internal Journal (Double-Entry Ledger): চূড়ান্ত নকশা ও বাস্তবায়ন পরিকল্পনা

তারিখ: ২ অক্টোবর ২০২৬
প্রসঙ্গ: [financial-flow-audit.md](financial-flow-audit.md)

> **অবস্থা (২ অক্টোবর ২০২৬): বাস্তবায়িত।**
> - Journal শুরু থেকেই চালু, তাই backfill বা shadow mode (Phase 2) বাদ দেওয়া হয়েছে।
> - Phase 1, 3, 4, 5 শেষ।
> - কোড আছে `app/Ledger/`-এ। Test আছে `tests/Feature/Ledger/`-এ, এই নথির §৯-এর পূর্ণ উদাহরণসহ।
> - Integrity check: `php artisan ledger:check`, প্রতিদিন রাত ২টায় চলে।
> - নকশা থেকে বিচ্যুতি:
>   - Refund এক ধাপে হয়: `4120 / টাকা ফেরত`। `2120` payable account বাদ দেওয়া হয়েছে।
>   - Deposit, charge allocation আর cycle allocation model-স্তরেই নিজে থেকে post হয়।

---

## ০. চূড়ান্ত সিদ্ধান্ত

| # | সিদ্ধান্ত |
|---|---|
| ১ | **টাকার সব লেনদেন journal-এ হবে।** কোনো balance আর আলাদা formula দিয়ে গোনা হবে না |
| ২ | **Journal সামলাবে backend-এর business layer (service + database)।** Controller বা UI কখনো সরাসরি journal-এ লিখবে না |
| ৩ | **UI এখনকার মতোই click করে চলবে।** ভেতরে ভেতরে journal entry তৈরি হবে |
| ৪ | **একটাই খাতা। Platform হলো মূল business, user-রা তার contact।** User-এর টাকা platform-এর কাছে **দায় (liability)**। Platform-এর নিজের আয়-ব্যয় আলাদা account-এ |
| ৫ | **একটাই joint bank account** (`1010 Bank`) |
| ৬ | **User → Members (one-to-many)।** User membership নিয়ে deposit করেন, member-এর unit অনুযায়ী cycle-এ বিনিয়োগ করেন, লাভ বা ক্ষতি পান নিজের ভাগ অনুযায়ী |
| ৭ | **Fund cycle = একটা মুদারাবা business।** Member-রা যে টাকা allocate করেন, সেটাই এর মূলধন। Platform এটা পরিচালনা করে |
| ৮ | **Cycle-এর অধীনে event আর investment হলো sub-business।** প্রতিটার আয় আর ব্যয়ের হিসাব আলাদা (dimension: `cycle_investment_id`) |
| ৯ | **Sub-business platform বা তার asset ব্যবহার করলে তার খরচ হিসেবে charge বসে।** Event-এ platform-এর shop আর লোকবল ব্যবহার হয়, তাই প্রতিটা event-এ platform charge, asset rent ইত্যাদি রাখা হয়। Business-এ platform বা asset ব্যবহার হলে charge বা rent বসবে, না হলে বসবে না। এই charge **sub-business-এর খরচ আর platform-এর আয়** |
| ৯ক | **কোন charge বা rent বসবে, তা admin-এর entry-র ওপর নির্ভর করে।** যা ধরা দরকার admin তা entry দেবেন, যা ছাড় দেওয়া যায় তা দেবেন না। System কোনো সীমা বা স্বয়ংক্রিয় নিয়ম চাপাবে না |
| ৯খ | **Event-এ order-এর বিক্রির বাইরেও আয় হতে পারে** (যেমন ব্যবহৃত box কম দামে বিক্রি)। এটা sub-business-এর "অন্যান্য আয়" |
| ৯গ | **Fund cycle-এর নিজেরও আয়-ব্যয় থাকতে পারে**, কোনো event বা business-এর সাথে যুক্ত নয় (যেমন cycle-এর চুক্তির কাগজপত্রের খরচ) |
| ১০ | **Cycle-এর লাভ বা ক্ষতি = সব sub-business-এর ফলাফল + cycle-এর নিজের আয় − cycle-এর নিজের ব্যয়**। **Cycle-এর লাভ থেকে platform কিছু পায় না।** পুরো allocated মূলধন আর অর্জিত লাভ (অথবা ক্ষতি বাদে বাকিটা) user-দের available balance-এ ফেরত যায় |
| ১০ক | **Platform-এর আয়:** fee (registration বা membership), sub-business-এর charge বা rent, অন্যান্য। **Platform-এর ব্যয়:** bank charge, SMS charge, IT, অন্যান্য। এসব user-এর হিসাবে কোনো প্রভাব ফেলে না |
| ১১ | **bKash fee event-এর খরচ।** SMS খরচ platform-এর (order-এর SMS হলেও) |
| ১২ | **Cash basis:** টাকা পেলেই আয় (revenue), টাকা দিলেই খরচ। মাস বা বছরের হিসাব বা period close আপাতত লাগবে না |

---

## ১. মূল ধারণা

```
                    ┌──────────── Platform (মূল business) ────────────┐
                    │                                                 │
  Assets            │   1010 Bank (joint)  ·  1020 bKash  ·  1030 Cash  │
                    │   1210 Business investment (মুদারাবা মূলধন খাটছে)   │
                    │                                                 │
  Liabilities       │   2010 Member balance      ← user/member-এর টাকা    │
  (user = contact)  │   2020 Cycle capital       ← cycle-এ বিনিয়োগ        │
                    │   2030 Cycle result        ← cycle-এর লাভ বা ক্ষতি   │
                    │                                                 │
  Fund P&L          │   4xxx / 5xxx  → project শেষে 2030-এ যায়            │
  (cycle-এর)        │                                                 │
                    │                                                 │
  Platform P&L      │   6xxx আয় / 7xxx ব্যয় → platform-এর নিজের            │
  Equity            │   3010 Platform equity                          │
                    └─────────────────────────────────────────────────┘
```

**User আর platform-এর টাকা যেভাবে আলাদা থাকে:**
- User-এর টাকা সবসময় **2xxx (দায়)**-এ থাকে।
- Cycle-এর আয়-ব্যয় (**4xxx/5xxx**) project শেষ হলে **2030**-এ যায়, অর্থাৎ সদস্যদের পাওনা হয়।
- Platform-এর আয়-ব্যয় (**6xxx/7xxx**) আর **3010** শুধু platform-এর।
- **Platform-এর নিজের তহবিল** = `3010 + 6xxx − 7xxx`। এটা ঋণাত্মক হলে platform-এর expense post হবে না। ফলে **সদস্যের টাকা দিয়ে platform-এর খরচ চালানো কাঠামোগতভাবেই অসম্ভব**।

**Bank-এর কোন টাকা কোন cycle-এর (earmark):**
- Bank একটাই। কিন্তু bank-এর line-এ `fund_cycle_id` থাকলে বোঝা যায় টাকাটা কোন cycle-এর।
- Allocation হলে টাকা "সাধারণ bank" থেকে "cycle X-এর bank"-এ যায় (একই account, শুধু dimension বদলায়)। Settlement হলে উল্টো দিকে ফেরে।
- এতে `balance(1010, cycle=X)` = cycle X-এর হাতে কত নগদ আছে। ফেরত আসা মূলধনও এতে যোগ হয়, তাই আবার খাটানো যায়।

---

## ২. Chart of Accounts

| Code | নাম | Type | Dimension |
|---|---|---|---|
| **Assets** | | | |
| 1010 | Bank (joint) | asset | cycle (earmark) |
| 1020 | bKash merchant wallet | asset | cycle, investment |
| 1030 | Event cash / float | asset | cycle, investment |
| 1210 | Business investment (খাটানো মূলধন) | asset | cycle, investment |
| **Liabilities: user বা contact-এর পাওনা** | | | |
| 2010 | Member balance (available) | liability | user, member |
| 2020 | Member cycle capital | liability | user, member, cycle |
| 2030 | Cycle result (ভাগ হয়নি এমন লাভ বা ক্ষতি) | liability | cycle, investment |
| 2120 | Customer refund payable | liability | cycle, investment, order |
| **Equity: platform** | | | |
| 3010 | Platform equity (জমা লাভ) | equity | — |
| 3900 | Opening balance adjustment | equity | — |
| **Fund আয়: cycle-এর, project শেষে 2030-এ যায়** | | | |
| 4110 | Event sales | income | cycle, investment, order |
| 4120 | Event sales refund (contra) | income | cycle, investment, order |
| 4150 | Sub-business other income (box বা scrap বিক্রি ইত্যাদি) | income | cycle, investment |
| 4310 | Business profit received | income | cycle, investment |
| 4510 | Cycle-level income (কোনো investment-এর নয়) | income | cycle |
| **Fund ব্যয়: cycle-এর** | | | |
| 5110 | Sub-business expense (category অনুযায়ী; event বা business) | expense | cycle, investment |
| 5310 | Payment gateway fee (bKash) | expense | cycle, investment |
| 5410 | Business capital loss | expense | cycle, investment |
| 5510 | Platform service charge (shop, লোকবল) | expense | cycle, investment |
| 5520 | Asset rent | expense | cycle, investment |
| 5590 | Other platform charge | expense | cycle, investment |
| 5610 | Cycle-level expense (কোনো investment-এর নয়) | expense | cycle |
| **Platform আয়** | | | |
| 6010 | Registration / membership fee | income | user, member |
| 6020 | Other fee | income | user, member |
| 6030 | Platform service charge income | income | cycle, investment |
| 6040 | Asset rent income | income | cycle, investment |
| 6050 | Other platform charge income | income | cycle, investment |
| 6090 | Other income (sponsorship …) | income | — |
| **Platform ব্যয়** | | | |
| 7010 | Bank charge | expense | — |
| 7020 | SMS charge | expense | — |
| 7030 | IT / hosting | expense | — |
| 7040 | Office / printing / transport | expense | — |
| 7090 | Other expense | expense | — |

এখনকার `GeneralIncomeCategory` 609x-এ আর `GeneralExpenseCategory` 70xx-এ চলে যাবে। `ledger_accounts`-এ একটা `scope` column থাকবে: `fund` (4xxx/5xxx) অথবা `platform` (6xxx/7xxx)। Report-এ এটা দিয়ে আলাদা করা হবে।

---

## ৩. Database নকশা

```php
Schema::create('ledger_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
    $table->enum('scope', ['fund', 'platform'])->nullable(); // income/expense only
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('journal_entries', function (Blueprint $table) {
    $table->id();
    $table->date('entry_date');
    $table->string('kind', 50);                    // deposit_verified, fee_settled, ...
    $table->string('description');
    $table->nullableMorphs('source');              // DepositSubmission, EventPayment, ...
    $table->string('idempotency_key')->unique();
    $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries');
    $table->foreignId('posted_by_user_id')->nullable()->constrained('users');
    $table->timestamp('posted_at');
    $table->timestamps();
});

Schema::create('journal_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
    $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
    $table->unsignedBigInteger('debit')->default(0);    // paisa
    $table->unsignedBigInteger('credit')->default(0);   // paisa
    $table->foreignId('user_id')->nullable()->constrained();   // contact
    $table->foreignId('member_id')->nullable()->constrained();
    $table->foreignId('fund_cycle_id')->nullable()->constrained();
    $table->foreignId('cycle_investment_id')->nullable()->constrained();
    $table->foreignId('event_order_id')->nullable()->constrained();
    $table->string('memo')->nullable();
    $table->timestamps();

    $table->index(['ledger_account_id', 'user_id']);
    $table->index(['ledger_account_id', 'fund_cycle_id']);
    $table->index(['ledger_account_id', 'cycle_investment_id']);
});

// project under a fund cycle (মুদারাবা বিনিয়োগ)
Schema::create('cycle_investments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fund_cycle_id')->constrained()->restrictOnDelete();
    $table->enum('type', ['event', 'business']);
    $table->string('title');
    $table->string('counterparty')->nullable();         // business partner
    $table->text('terms')->nullable();
    $table->date('invested_at')->nullable();
    $table->enum('status', ['active', 'closed'])->default('active');
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
});

// platform charge / asset rent levied on a sub-business (source document)
Schema::create('cycle_investment_charges', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cycle_investment_id')->constrained()->restrictOnDelete();
    $table->enum('type', ['platform_service', 'asset_rent', 'other']);
    $table->unsignedBigInteger('amount');               // paisa
    $table->string('note')->nullable();
    $table->date('charged_at');
    $table->foreignId('created_by_user_id')->nullable()->constrained('users');
    $table->timestamps();
});

// event other income (mirror of event_expenses), e.g. used box sale
Schema::create('event_incomes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fund_cycle_event_id')->constrained()->restrictOnDelete();
    $table->date('income_date');
    $table->enum('category', ['scrap_sale', 'sponsorship', 'other']);
    $table->enum('received_via', ['cash', 'bank', 'bkash']);
    $table->unsignedBigInteger('amount');               // paisa
    $table->text('description')->nullable();
    $table->string('receipt_path')->nullable();
    $table->foreignId('created_by_user_id')->nullable()->constrained('users');
    $table->timestamps();
});

// business sub-business transactions (source document)
Schema::create('business_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cycle_investment_id')->constrained()->restrictOnDelete();
    $table->enum('type', ['invest', 'profit', 'capital_return', 'capital_loss', 'other_income', 'expense']);
    $table->unsignedBigInteger('amount');               // paisa
    $table->date('transaction_date');
    $table->text('description')->nullable();
    $table->string('reference_no')->nullable();
    $table->string('receipt_path')->nullable();
    $table->foreignId('created_by_user_id')->nullable()->constrained('users');
    $table->timestamps();
});

// fund-cycle-level income / expense, not tied to any investment
Schema::create('fund_cycle_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fund_cycle_id')->constrained()->restrictOnDelete();
    $table->enum('direction', ['income', 'expense']);
    $table->string('category', 50);
    $table->unsignedBigInteger('amount');               // paisa
    $table->date('transaction_date');
    $table->text('description')->nullable();
    $table->string('receipt_path')->nullable();
    $table->foreignId('created_by_user_id')->nullable()->constrained('users');
    $table->timestamps();
});
```

এখনকার table-এ যা বদলাবে:
- `fund_cycle_events`-এ যোগ হবে `cycle_investment_id`। প্রতিটা event-এর সাথে একটা `type=event`-এর investment থাকবে।
- পুরোনো table (`deposit_submissions`, `event_payments`, `event_expenses` …) **source document হিসেবে থেকে যাবে**। টাকার হিসাব থাকবে শুধু journal-এ।
- Order-এর due-এর হিসাব operational, আগের মতোই order থেকে আসবে (`total − verified payments`)। Cash basis-এ due journal-এ ঢোকে না।

---

## ৪. কোন ঘটনায় কোন entry

`(cX)` মানে line-এ `fund_cycle_id = X`। `(—)` মানে কোনো cycle নেই।

### ৪.১ User ও Member

| ঘটনা (UI-তে যা click হয়) | Debit | Credit |
|---|---|---|
| Admin deposit verify করলেন | 1010 Bank (—) | 2010 Member balance (user) |
| Registration fee পরিশোধ | 2010 Member balance (user, member) | 6010 Fee income |
| Fee reverse (cancel) | উপরের entry-র reversal | |
| Member-কে cycle X-এ allocate করা | 2010 Member balance (user, member)<br>1010 Bank (cX) | 2020 Cycle capital (user, member, cX)<br>1010 Bank (—) |
| Payout *(নতুন)* | 2010 Member balance (user) | 1010 Bank (—) |

### ৪.২ Cycle → Event (`type=event`)

| ঘটনা | Debit | Credit |
|---|---|---|
| Bank থেকে event-এর জন্য তোলা | 1030 Event cash (cX, inv) | 1010 Bank (cX) |
| Event expense | 5110 Sub-business expense | 1030 Event cash |
| Customer-এর bKash পরিশোধ verify | 1020 bKash | **4110 Event sales** (order) |
| Customer-এর নগদ পরিশোধ verify | 1030 Event cash | **4110 Event sales** (order) |
| bKash fee | 5310 Gateway fee | 1020 bKash |
| bKash থেকে bank-এ settlement | 1010 Bank (cX) | 1020 bKash |
| নগদ বা বেঁচে যাওয়া float bank-এ জমা | 1010 Bank (cX) | 1030 Event cash |
| **অন্যান্য আয়** (box বিক্রি ইত্যাদি) | 1030 Cash / 1010 Bank (cX) / 1020 bKash | 4150 Sub-business other income |
| Order cancel, refund দিতে হবে | 4120 Sales refund | 2120 Refund payable |
| Refund দেওয়া হলো | 2120 Refund payable | 1020 bKash বা 1030 Cash |

### ৪.৩ Cycle → Business (`type=business`)

| ঘটনা | Debit | Credit |
|---|---|---|
| Business-এ বিনিয়োগ | 1210 Business investment (cX, inv) | 1010 Bank (cX) |
| লাভের টাকা এলো | 1010 Bank (cX) | 4310 Business profit |
| মূলধন ফেরত (পুরো বা আংশিক) | 1010 Bank (cX) | 1210 Business investment |
| মূলধনের ক্ষতি বা write-off | 5410 Capital loss | 1210 Business investment |
| অন্যান্য আয় | 1010 Bank (cX) | 4150 Sub-business other income |
| Business-এর খরচ (যাতায়াত, কাগজপত্র …) | 5110 Sub-business expense | 1010 Bank (cX) |

প্রতিটা লেনদেনের কাগজ হিসেবে `business_transactions`-এ একটা row থাকবে।

### ৪.৪ Platform charge আর asset rent (event বা business-এ)

Admin event বা business page থেকে **"Charge যোগ করুন"** চাপবেন (type, টাকা, note)। এতে `cycle_investment_charges`-এ একটা row তৈরি হয়, আর এই entry হয়:

| Type | Debit | Credit |
|---|---|---|
| Platform service | 5510 (cX, inv) | 6030 |
| Asset rent | 5520 (cX, inv) | 6040 |
| Other | 5590 (cX, inv) | 6050 |
| সবগুলোর সাথে earmark | 1010 Bank (—) | 1010 Bank (cX) |

- Earmark line-জোড়ায় charge-এর টাকা cycle-এর ভাগ থেকে platform-এর ভাগে যায়। টাকা একই bank-এ থাকে।
- Charge project চলার যেকোনো সময় বসানো যাবে। একাধিকবার বসানো যাবে।
- Project close হওয়ার পর আর বসানো যাবে না।
- Charge বাতিল করলে reversal entry হবে।
- কোন charge বা rent বসবে আর কত টাকা, তা পুরোপুরি admin-এর entry-র ওপর। System কোনো সীমা দেয় না। Business-এ platform বা asset ব্যবহার না হলে admin কোনো charge দেবেন না।

### ৪.৫ Project close (event finalize বা business close)

1. **Project-এর ফলাফল** = `4110 − 4120 + 4150 + 4310 − 5110 − 5310 − 5410 − 55xx` (ওই investment-এর)। এটাই **ফেরতযোগ্য লাভ** (অথবা ক্ষতি)।
2. Close করার আগের checklist:
   - pending payment নেই
   - event cash বা float ০ (সব টাকা bank-এ জমা হয়েছে)
   - bKash-এর টাকা bank-এ settled হয়েছে
   - business-এর 1210 ০ (মূলধন ফেরত এসেছে, অথবা ক্ষতি হিসেবে লেখা হয়েছে)
3. **Closing entry:** ওই investment-এর সব 4xxx/5xxx শূন্য করা হয়, ফলাফল যায় **2030 Cycle result (cX, inv)**-এ।
4. Investment `closed` হয়ে lock হয়ে যায়।

### ৪.৬ Fund cycle-এর নিজের আয়-ব্যয় *(নতুন)*

কোনো event বা business-এর সাথে যুক্ত নয়, এমন cycle-স্তরের লেনদেন। যেমন cycle-এর চুক্তির কাগজপত্র বা আইনি খরচ, cycle-এর নামে আসা কোনো আয়। Admin fund cycle page থেকে **"আয় বা ব্যয় যোগ করুন"** চাপবেন, তাতে `fund_cycle_transactions`-এ একটা row তৈরি হবে।

| ঘটনা | Debit | Credit |
|---|---|---|
| Cycle-level আয় | 1010 Bank (cX) | 4510 Cycle-level income (cX) |
| Cycle-level ব্যয় | 5610 Cycle-level expense (cX) | 1010 Bank (cX) |

এই line-গুলোতে `cycle_investment_id` খালি থাকে। এগুলো close হয় settlement-এর সময় (§৪.৭)।

### ৪.৭ Cycle settlement *(নতুন)*

শর্ত: cycle-এর সব investment `closed`।

ধাপ ১, **cycle-level আয়-ব্যয় close করা:** `4510 − 5610`-এর balance যায় **2030 Cycle result (cX)**-এ (এখানে investment থাকে না)।

**Cycle-এর লাভ বা ক্ষতি** = `balance(2030, cycle)` = সব sub-business-এর ফলাফল + cycle-এর নিজের আয় − cycle-এর নিজের ব্যয়। **এখান থেকে platform কিছু পায় না।** পুরোটা member-দের।

ধাপ ২, **ভাগ করা:**

| Debit | Credit |
|---|---|
| 2020 Cycle capital (প্রতি member) | 2010 Member balance (প্রতি member: মূলধন ± ভাগ) |
| 2030 Cycle result (লাভ হলে) | 2030 Cycle result (ক্ষতি হলে) |
| 1010 Bank (—) | 1010 Bank (cX), earmark ফেরত |

- ভাগ হবে **member-দের 2020 balance-এর অনুপাতে**।
- Paisa-র যে ভগ্নাংশ বাঁচে, সেটা সবচেয়ে বড় ভাগের member-এর ঘরে যাবে। নিয়মটা কোডে নির্দিষ্ট থাকবে।
- শেষে cycle X-এর 2020, 2030 আর 1010 (cX), তিনটাই ০। Cycle `settled` হবে।

### ৪.৮ Platform-এর নিজের হিসাব

| ঘটনা | Debit | Credit |
|---|---|---|
| Bank charge | 7010 | 1010 Bank (—) |
| SMS charge | 7020 | 1010 Bank (—) |
| অন্য খরচ | 70xx | 1010 Bank (—) |
| অন্য আয় | 1010 Bank (—) | 6090 |

Guard: Platform-এর নিজের তহবিল (`3010 + 6xxx − 7xxx`) খরচের চেয়ে কম হলে entry block হবে।

---

## ৫. কোন balance কোথা থেকে আসবে

| প্রশ্ন | Journal থেকে |
|---|---|
| User available balance | `balance(2010, user)` |
| Member-এর cycle-এ বিনিয়োগ | `balance(2020, member, cycle)` |
| Cycle-এর হাতে নগদ (তোলা যায় এমন) | `balance(1010, cycle)` |
| Cycle-এর যে টাকা এখন খাটছে | `balance(1020 + 1030 + 1210, cycle)` |
| চলমান project-এর লাভ-ক্ষতি | `balance(4xxx − 5xxx, investment)` |
| Cycle-এর নিজের আয়-ব্যয় | `balance(4510 − 5610, cycle)` |
| Cycle-এর চলমান মোট লাভ-ক্ষতি | `balance(2030 + 4xxx − 5xxx, cycle)` |
| Settlement-এর সময় ফেরতযোগ্য লাভ | `balance(2030, cycle)` |
| Platform-এর নিজের তহবিল | `−balance(3010 + 6xxx + 7xxx)` |
| Platform-এর আয়-ব্যয় | `balance(6xxx)`, `balance(7xxx)` |
| Joint bank (bank statement-এর সাথে মেলাতে) | `balance(1010)` |
| সব মিলিয়ে check | `Σ assets = Σ liabilities + platform equity` |

---

## ৬. Code-এর গঠন

```
app/Ledger/
├── LedgerService.php            // post(), reverse(), balance(), lockFor()
├── JournalEntryBuilder.php      // ->debit(acct, amt, dims)->credit(...)->post()
├── Accounts.php                 // account code constants
├── Exceptions/UnbalancedEntry.php, InsufficientBalance.php
└── Posters/
    ├── Member/      DepositVerified, FeeSettled, CycleAllocated, Payout
    ├── Event/       Withdrawal, Expense, OtherIncome, CustomerPayment,
    │                GatewayFee, BkashSettlement, CashDeposit, Refund
    ├── Business/    Invested, ProfitReceived, CapitalReturned, CapitalLoss,
    │                OtherIncome, Expense
    ├── Investment/  ChargeLevied, InvestmentClosed   // closing → 2030
    ├── Cycle/       CycleIncome, CycleExpense, CycleSettled
    └── Platform/    PlatformExpense, PlatformIncome
```

নিয়ম:
- **Controller → domain service → Poster → LedgerService**, সবটা একটাই `DB::transaction`-এর ভেতরে।
- Balance check হবে post করার আগে, lock নিয়ে (`lockFor(account, dims)`)।
- `idempotency_key` থাকবে, যাতে bKash callback দুবার এলেও entry দুবার না হয়।
- `JournalEntry` আর `JournalLine` model-এ update বা delete হলে exception হবে। ভুল শোধরাতে শুধু reversal।
- Closed investment বা settled cycle-এ post করা যাবে না। শুধু admin-এর `adjustment` চলবে, কারণসহ।

---

## ৭. UI

**এখনকার page:** দেখতে আর click করার ধরন একই থাকবে।

**নতুন page:**

| Page | কে দেখবেন | কাজ |
|---|---|---|
| Event অন্যান্য আয় | Admin | Event page-এ expense section-এর পাশে "আয়" section (box বিক্রি ইত্যাদি) |
| Business investments | Admin | তৈরি → Invest / Profit / Capital return / Loss / অন্যান্য আয় / খরচ / Charge বা rent → Close |
| Cycle-এর আয়-ব্যয় | Admin | Fund cycle page-এ একটা section: আয় বা ব্যয়, category, টাকা, receipt |
| Charge যোগ করা | Admin | Event বা business page-এ একটা section: type (platform service / asset rent / other), টাকা, note |
| Event close | Admin | এখনকার "Finalize" বোতাম। Checklist আর ফেরতযোগ্য লাভ দেখাবে → নিশ্চিত |
| Cycle settlement | Admin | Preview (প্রতিটা sub-business-এর ফলাফল, cycle-এর নিজের আয়-ব্যয়, প্রতি member-এর মূলধন, ভাগ, মোট) → Approve |
| Payout requests | User, Admin | Request → Approve → পাঠানো হয়েছে |
| Platform accounts | Admin | Platform-এর আয়-ব্যয়, তহবিল (general income/expense page এখানে একীভূত হবে) |
| Bank reconciliation | Admin | `1010`-এর balance বনাম bank statement |
| Trial balance / General ledger | Admin | Account আর dimension দিয়ে filter |
| My statement | User | Member অনুযায়ী লেনদেন আর চলমান balance |

---

## ৮. ধাপে ধাপে কাজ

### Phase 1: ভিত্তি
- [ ] Migration: `ledger_accounts`, `journal_entries`, `journal_lines`, `cycle_investments`, `cycle_investment_charges`, `event_incomes`, `business_transactions`, `fund_cycle_transactions`, `fund_cycle_events.cycle_investment_id`
- [ ] Account seeder (§২)
- [ ] `LedgerService`, `JournalEntryBuilder`। Debit = credit, বদলানো বন্ধ, idempotency, lock, platform তহবিলের guard
- [ ] Pest test: ভারসাম্যহীন entry, earmark, platform ঋণাত্মক হলে block, reversal, idempotency

### Phase 2: Shadow mode
- [ ] §৪.১, ৪.২, ৪.৮-এর এখনকার সব ঘটনায় Poster বসানো। Balance তখনও পুরোনো formula থেকে
- [ ] এখনকার প্রতিটা event-এর জন্য `cycle_investments(type=event)` তৈরি করা
- [ ] `ledger:backfill`: পুরোনো সব record তারিখ অনুযায়ী replay
- [ ] `ledger:reconcile`: পুরোনো formula আর journal পাশাপাশি তুলনা। পার্থক্য থাকলে কারণ লিখে `adjustment` entry
- [ ] Reconcile প্রতিদিন schedule করা

### Phase 3: Balance journal থেকে পড়া
- [ ] Member balance (৫টা duplicated formula মুছে ফেলা)
- [ ] Treasury → platform তহবিল আর সদস্যদের দায় আলাদা করে দেখানো
- [ ] Cycle withdrawal budget → `balance(1010, cycle)`
- [ ] Event details, member-দের cycle page
- [ ] `max(0, …)` সরানো, ঋণাত্মক হলে warning

### Phase 4: নতুন flow
- [ ] Charge বা rent বসানো (event আর business)
- [ ] Event-এর অন্যান্য আয়
- [ ] Cycle-এর নিজের আয়-ব্যয়
- [ ] Event close (checklist সহ)
- [ ] Business investment (পুরো lifecycle)
- [ ] Refund, bKash fee, bKash settlement
- [ ] Cycle settlement
- [ ] Payout, member exit

### Phase 5: Report
- [ ] Trial balance, General ledger, Platform P&L, Cycle result, Project P&L
- [ ] User statement, PDF বা CSV export

### Phase 6: পরিচ্ছন্নতা
- [ ] পুরোনো formula, অব্যবহৃত field আর method মুছে ফেলা (audit §৫)
- [ ] প্রতিটা `kind`-এর নিয়ম লিখে রাখা

---

## ৯. পূর্ণ উদাহরণ

শুরুর অবস্থা:
- Cycle #2, unit-এর দাম ১,০০,০০০
- User A → member A1 (২ unit), User B → member B1 (৩ unit)

| # | ঘটনা | Entry | ফল |
|---|---|---|---|
| 1 | A deposit ২,০৫,০০০, B deposit ৩,০৫,০০০ | Dr 1010(—) ৫,১০,০০০ / Cr 2010 (A, B) | |
| 2 | দুজনেরই fee ৫,০০০ | Dr 2010 (A, B) ১০,০০০ / Cr 6010 | Platform +১০,০০০ |
| 3 | Allocation | Dr 2010 ৫,০০,০০০ / Cr 2020 (A1 ২ লাখ, B1 ৩ লাখ); Dr 1010(c2) / Cr 1010(—) ৫,০০,০০০ | Cycle নগদ ৫,০০,০০০ |
| **E1** | **আমের মেলা** | | |
| 4 | তোলা | Dr 1030 / Cr 1010(c2) ১,৫০,০০০ | |
| 5 | খরচ | Dr 5110 / Cr 1030 ১,৪০,০০০ | |
| 6 | bKash বিক্রি | Dr 1020 / Cr 4110 ১,২০,০০০ | |
| 7 | নগদ বিক্রি | Dr 1030 / Cr 4110 ৮০,০০০ | |
| 7ক | ব্যবহৃত box নগদে বিক্রি | Dr 1030 / Cr 4150 ২,০০০ | |
| 8 | bKash fee | Dr 5310 / Cr 1020 ১,৮০০ | |
| 9 | bKash থেকে bank-এ | Dr 1010(c2) / Cr 1020 ১,১৮,২০০ | |
| 10 | নগদ bank-এ | Dr 1010(c2) / Cr 1030 ৯২,০০০ | |
| 11 | Platform service charge ৫,০০০ + asset rent ৩,০০০ | Dr 5510 ৫,০০০ / Cr 6030; Dr 5520 ৩,০০০ / Cr 6040; Dr 1010(—) / Cr 1010(c2) ৮,০০০ | Platform +৮,০০০ |
| 12 | Close | Dr 4110 ২,০০,০০০ + 4150 ২,০০০ / Cr 5110 ১,৪০,০০০ + 5310 ১,৮০০ + 5510 ৫,০০০ + 5520 ৩,০০০ + **2030 ৫২,২০০** | ফেরতযোগ্য ৫২,২০০ |
| **B1** | **রহিম ট্রেডার্স** (platform ব্যবহার হয়নি) | | |
| 13 | বিনিয়োগ | Dr 1210 / Cr 1010(c2) ৩,০০,০০০ | |
| 14 | লাভ এলো | Dr 1010(c2) / Cr 4310 ৪৫,০০০ | |
| 15 | মূলধন ফেরত | Dr 1010(c2) / Cr 1210 ৩,০০,০০০ | |
| 16 | Close (platform বা asset ব্যবহার হয়নি, তাই charge নেই) | Dr 4310 / Cr **2030 ৪৫,০০০** | ফেরতযোগ্য ৪৫,০০০ |
| **C** | **Cycle-এর নিজের** | | |
| 16ক | Cycle-এর চুক্তির কাগজপত্রের খরচ | Dr 5610 / Cr 1010(c2) ১,২০০ | |
| **P** | **Platform** | | |
| 17 | SMS bill | Dr 7020 / Cr 1010(—) ১,৫০০ | Platform −১,৫০০ |
| **S** | **Settlement** | | |
| 18 | Cycle-level close | Dr 2030(c2) / Cr 5610 ১,২০০ | |
| 19 | Cycle-এর লাভ = ৫২,২০০ + ৪৫,০০০ − ১,২০০ = ৯৬,০০০। Platform-এর ভাগ ০। ভাগ ২:৩ (A1 ৩৮,৪০০, B1 ৫৭,৬০০) | Dr 2020 ৫,০০,০০০ + Dr 2030 ৯৬,০০০ / Cr 2010 (A ২,৩৮,৪০০, B ৩,৫৭,৬০০); Dr 1010(—) / Cr 1010(c2) ৫,৯৬,০০০ | Cycle নগদ ০ ✓ |

**মিলিয়ে দেখা:**
- Cycle-এর bank: ৫,০০,০০০ − ১,৫০,০০০ + ১,১৮,২০০ + ৯২,০০০ − ৮,০০০ − ৩,০০,০০০ + ৪৫,০০০ + ৩,০০,০০০ − ১,২০০ − ৫,৯৬,০০০ = **০** ✓
- Platform-এর তহবিল: ১০,০০০ + ৮,০০০ − ১,৫০০ = **১৬,৫০০**
- Joint bank: ৫,১০,০০০ + E1-এর নিট নগদ ৬০,২০০ + ৪৫,০০০ − ১,২০০ − ১,৫০০ = **৬,১২,৫০০**
  = A ২,৩৮,৪০০ + B ৩,৫৭,৬০০ + Platform ১৬,৫০০ ✓

**ক্ষতি হলে কী হবে:** ধরুন B1-এ লাভ আসেনি, আর মূলধন ফেরত এসেছে মাত্র ২,৬০,০০০।
- Entry: Dr 1010(c2) ২,৬০,০০০ + Dr 5410 ৪০,০০০ / Cr 1210 ৩,০০,০০০
- Close: **Dr 2030 ৪০,০০০** / Cr 5410
- Cycle-এর ফলাফল = E1 +৫২,২০০ + B1 −৪০,০০০ − cycle-এর খরচ ১,২০০ = **+১১,০০০**, যা ২:৩ অনুপাতে ভাগ হবে
- Cycle-এর ফলাফল ঋণাত্মক হলে মূলধন থেকে একই অনুপাতে কাটা যাবে
- Platform cycle-এর লাভ বা ক্ষতির কোনো ভাগ নেয় না। তার আয় শুধু sub-business-এর charge আর rent থেকে
