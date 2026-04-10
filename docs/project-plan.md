# ISF Project Plan

## Purpose

This document is the working business plan for the ISF project. It captures the agreed financial model, scope boundaries, and implementation direction so future planning and development stay aligned.

## Current Operating Model

ISF will run on a fund-cycle-based savings allocation and investment lifecycle.

- Deposits are user-based.
- Members are created under a user.
- A user can deposit any amount at any time.
- Deposited funds first remain as the user's unallocated balance.
- Member-oriented charges may also be fulfilled from the same unallocated balance.
- Allocations are made from the user's unallocated balance.
- Allocations are recorded against members, not against users.
- Allocations are tied to a Fund Cycle slot.
- Extra-fund expenses must be tracked separately from fund-cycle investment money.

## Core Entities

### User

The account holder. A user deposits funds and owns the unallocated balance.

### Member

A participation entity under a user. Fund cycle slot allocations are made in the member's name.

### Fund Cycle

A named financial cycle with a start period and end period. It is the primary container for allocation, locking, investment, maturity, and settlement. Multiple fund cycles may be active at the same time.

Example:

- Fund cycle name: Project Ghee
- Period: January to March

### Fund Cycle Slot

A month-based allocation window inside a fund cycle. For the January to March example, the slots are January, February, and March.

### Investment Record

The investment entry created for a locked fund cycle. It captures the investment window, capital amount, and operational notes needed to track what happened to the pooled funds.

### Settlement

The final member-wise outcome of a matured fund cycle. It records the current value, profit, entitlement, or payable amount for each participating member.

### Charge

A member-oriented payable item outside the fund-cycle investment flow. The initial source model is `Member` through a morph relation. It can represent registration fee, tour fee, restaurant fee, picnic fee, or other extra charges. A posted charge may be settled from the user's unallocated deposit balance and counted as a minus item in deposit summaries.

### Extra Fund Expense

The record of how charge-collected money was spent. It must preserve category, amount, purpose, payee or recipient, supporting reference, approver identity, and timestamps.

## Financial Flow

1. A user deposits funds at any time.
2. The system stores those funds as user-level unallocated balance.
3. The same unallocated balance may also be used to settle posted member-oriented charges.
4. The user allocates part of the remaining balance to one or more members.
5. Each allocation is tied to a specific fund cycle slot.
6. When the fund cycle period ends, its slots become locked.
7. Locked fund cycle funds move to the investment phase.
8. After maturity, current value or return is calculated member-wise based on allocated participation in that fund cycle.
9. Members receive settlement according to their recorded participation or unit value.

## Non-Investment Fund Flow

### 1. Charges

- A member may carry one or more charges outside the fund-cycle allocation flow.
- A charge can represent registration fee, tour fee, restaurant fee, picnic fee, or another approved extra charge.
- A charge stays attached to the member record through a morph source, initially using `Member`.
- Only posted charges should reduce the available deposit balance.
- The system should preserve whether a charge is pending, posted, waived, or cancelled.

### 2. Extra Fund Expenses

- Charge-collected money should be spendable only through explicit expense records.
- Every expense should be linked to a funding source such as a charge category or other approved extra-fund source.
- Expenses should support reimbursement, vendor payment, event spending, and operational cost use cases.
- Expense records should never overwrite historical collection entries.

## Lifecycle Stages

### 1. Deposit Phase

Deposits remain independent from fund cycles. Funds are available as user-level unallocated balance.

### 2. Allocation Phase

Users allocate available balance to specific member entries within fund cycle slots.

### 3. Lock Phase

Once the fund cycle end date passes, all related slots are locked. No further allocation or modification is allowed.

### 4. Investment Phase

The fund cycle pool is invested for a defined tenure.

### 5. Maturity and Settlement Phase

After investment maturity, the system calculates member-wise current value, profit, or entitlement and records settlement.

## Business Rules

- User and member are separate entities.
- Deposits belong to the user.
- Unallocated balance is maintained at the user level.
- The unallocated balance is the source for fund-cycle allocations and posted member-oriented charges.
- Slot participation belongs to the member inside a specific fund cycle.
- Fund cycle allocations must not exceed the user's available unallocated balance.
- Locked fund cycle slots cannot be edited.
- Charges must remain separate from fund-cycle allocations in reporting.
- Extra-fund collections must be tracked by member source and purpose.
- Extra-fund expenses must be linked to a source pool and must not be mixed into fund-cycle investment returns.
- If balance is insufficient, a charge should remain pending instead of creating a negative balance.
- Financial history should remain auditable and append-only where practical.
- Multiple fund cycles can run in parallel.
- Investment and settlement must remain traceable at the fund-cycle level.

## Record-Keeping Plan For Extra Funds

Use ledger-style records rather than derived booleans.

- Keep deposit submissions as the source of incoming cash to the user balance.
- Add a balance ledger that records every increase and decrease against the user's unallocated balance.
- Represent posted charge settlement as its own transaction type in the balance ledger.
- Keep separate aggregate reporting for fund-cycle allocations and charges.
- For expenses, create explicit expense records plus, when needed, expense allocation rows so a single expense can be split across multiple funding sources.

Suggested transaction categories:

- deposit_verified_credit
- fund_cycle_allocation_debit
- charge_debit
- manual_adjustment_credit
- manual_adjustment_debit

Suggested expense categories:

- event_cost
- welfare_support
- administration
- bank_charge
- refund
- other

Suggested source pools for expense tracking:

- charge_pool

## MVP Scope Guidance

Initial implementation should prioritize:

- user deposits and user unallocated balance tracking
- minimal member-oriented charges setup and settlement tracking
- extra-fund expense recording and reporting
- member creation and member-wise allocations
- fund cycle and slot setup
- slot locking after fund cycle end
- fund-cycle-level investment record keeping
- member-wise maturity value and settlement visibility

## Planning Notes

When implementation starts, break the work into these areas:

- database design
- balance ledger design
- charges workflow and settlement rules
- extra-fund expense management and reporting
- admin fund cycle management
- deposit ledger and balance calculation
- member allocation workflow
- lock and maturity rules
- member-facing balance and allocation visibility
