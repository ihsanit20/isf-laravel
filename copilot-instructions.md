# ISF Workspace Instructions

This workspace contains two apps:

- `laravel/` for the backend, admin panel, web portal, database, and APIs
- `flutter/` for the member mobile app

Project domain:

- This software is for Al-Ihsan Savings Fund (ISF)
- It is a savings-focused group fund management system
- The first release should stay simple and focus on savings collection, deposit verification, ledgers, reports, and member transparency
- Do not introduce investment, profit-sharing, or complex loan logic unless explicitly requested

Core domain rules:

- Treat `users` as login accounts
- Treat `members` as fund memberships
- One user can manage multiple members, including family memberships
- All financial records must be member-based, not user-based
- User roles should stay simple and use enum-style values such as `super_admin`, `admin`, and `member`
- Prefer simple schemas, simple workflows, and explicit status fields over abstract or generic architectures

Database and backend guidance:

- Keep database design practical and MVP-friendly
- Prefer clear table names and straightforward columns
- Avoid premature normalization or complex polymorphic designs unless there is a real need
- Do not hard-delete financial records
- Preserve auditability for deposits, approvals, settlements, and ledger entries
- Prefer append-only financial history patterns; use adjustments instead of editing past approved money records when possible
- Keep approval flows simple

Laravel guidance:

- Follow Laravel conventions for controllers, requests, models, policies, migrations, and tests
- Treat any auth or scaffold work as current Laravel starter kit work unless the repo explicitly shows a specific package or structure
- Use Form Request validation for non-trivial input validation
- Keep business logic out of controllers when it starts growing; use service classes only when there is clear reuse or complexity
- Prefer route names, policy checks, and Eloquent relationships that reflect the ISF domain directly
- When changing schema, also consider factories, seeders, validation, and tests if relevant
- Prefer simple enums or constants for statuses and roles when they are fixed

Flutter guidance:

- Use Flutter for the member-facing app first
- Keep the mobile app focused on member tasks: login, dashboard, dues, deposits, statements, and notifications
- Avoid adding admin-heavy workflows to Flutter unless explicitly requested
- Keep widget structure simple and maintainable
- Use clear state flow and avoid unnecessary packages

UI and product guidance:

- Prioritize clarity, trust, and transparency over decorative complexity
- Show member balances, dues, deposit status, and ledger history clearly
- Financial values should always be easy to verify
- Use simple Bengali-friendly labels and flows when building user-facing ISF features

Working style for this repository:

- Prefer minimal changes that fit the current architecture
- Do not add new dependencies unless they are justified
- Do not rewrite generated starter code unless needed for the task
- When implementing a feature, keep Laravel and Flutter concerns separated
- If a requested feature is better handled in a later phase, say so and keep the MVP scope controlled

When making implementation decisions, optimize for:

1. traceable financial data
2. simple maintenance
3. clear member workflows
4. low operational risk
5. room to extend later without overbuilding now
