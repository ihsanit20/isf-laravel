# Events Database Plan

This is a minimal and simple database plan for the event pre-order system.

## 1. fund_cycles

Stores the parent cycle that funds the event.

Suggested columns:

- id
- name
- status
- unit_amount
- start_date
- lock_date
- maturity_date
- settlement_date
- slots
- notes
- created_by_user_id
- timestamps

## 2. fund_cycle_events

Stores events under a fund cycle.

Suggested columns:

- id
- fund_cycle_id
- title
- slug
- description
- banner_image_path
- status
- order_open_at
- order_close_at
- expected_delivery_date
- public_visible
- notes
- created_by_user_id
- timestamps

## 3. event_packages

Stores sellable packages for each event.

Suggested columns:

- id
- fund_cycle_event_id
- name
- description
- price
- advance_payment_percent
- min_quantity
- max_quantity
- stock_quantity
- is_active
- timestamps

## 4. event_hubs

Stores delivery hubs or pickup points for each event.

Suggested columns:

- id
- fund_cycle_event_id
- name
- area
- address
- contact_person
- phone
- is_active
- timestamps

## 5. event_orders

Stores customer orders from the public site.

Suggested columns:

- id
- fund_cycle_event_id
- order_number
- customer_name
- customer_phone
- customer_address
- pickup_hub_id
- status
- total_amount
- advance_amount
- confirmed_at
- timestamps

## 6. event_order_items

Stores package items inside each order.

Suggested columns:

- id
- event_order_id
- event_package_id
- quantity
- unit_price
- line_total
- timestamps

## 7. event_payments

Stores advance payment records for orders.

Suggested columns:

- id
- event_order_id
- amount
- payment_method
- payment_status
- transaction_reference
- paid_at
- verified_by_user_id
- timestamps

## 8. event_order_status_histories

Stores order status changes for tracking.

Suggested columns:

- id
- event_order_id
- status
- note
- changed_by_user_id
- changed_at
- timestamps

## Minimal Rule Set

- Keep all event data linked to a fund cycle.
- Keep packages and hubs under each event.
- Keep order, payment, and status history separate.
- Do not hard-delete financial or order records.
- Use status fields instead of complex logic in the first version.

## MVP Focus

For the first version, the most important tables are:

- fund_cycles
- fund_cycle_events
- event_packages
- event_hubs
- event_orders
- event_order_items
- event_payments
- event_order_status_histories

## Notes

This plan is intentionally simple. If needed later, it can be expanded with:

- refunds
- coupons
- gateway callbacks
- delivery slots
- inventory logs
- customer accounts
