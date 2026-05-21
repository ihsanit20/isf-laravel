# Events Project Plan

This plan is based on the current understanding of the event system under a fund cycle.

## 1. Goal

Build a cycle-backed pre-order system where:

- a fund cycle provides the financial base
- events run under that cycle
- packages are created for pre-order sales
- customers can place orders from a public site
- advance payment confirms the order
- order number is sent by SMS
- users can track orders later

## 2. Phase 1: Domain and Data Design

Define the core model structure for:

- fund cycles
- events
- packages
- pickup points / delivery hubs
- orders
- payments
- tracking/status history

Define status flow such as:

- draft
- published
- ordering_open
- ordering_closed
- fulfilled
- cancelled

## 3. Phase 2: Admin Event Management

Add admin tools to:

- create an event under a fund cycle
- edit event details
- publish or unpublish an event
- create and manage packages
- set package price
- set package advance payment percentage
- set package stock or quota
- create multiple pickup points or hubs
- set order open and close time
- set expected delivery date

## 4. Phase 3: Public Ordering Site

Build a public-facing site where users can:

- browse published events
- view event details
- select a package
- choose quantity
- select a pickup point
- enter name, phone, and delivery-related details
- see advance payment requirements
- place a pre-order

## 5. Phase 4: Payment Confirmation and Order Number

When payment is completed:

- confirm the order
- generate a unique order number
- send the order number by SMS
- make the order available for tracking

## 6. Phase 5: Order Tracking

Provide a simple tracking flow using:

- order number
- phone number

Trackable order states should include:

- pending payment
- confirmed
- processing
- ready for pickup
- out for delivery
- delivered
- cancelled

## 7. Phase 6: Operational Controls

Add controls for:

- stock/quota protection
- oversell prevention
- manual payment verification
- SMS logging
- admin order monitoring
- event lifecycle tracking

## 8. Phase 7: Reporting and Expansion

Later, add:

- event-wise sales summaries
- payment collection summaries
- hub performance reports
- fund cycle utilization reports
- refund handling
- gateway payments
- coupon or discount support

## 9. MVP Scope

The first version should stay simple:

- one fund cycle can contain many events
- each event can have many packages
- each event can have many pickup points
- public order placement is available
- advance payment confirms the order
- SMS order number is sent
- tracking is available

## 10. Suggested Build Order

1. finalize database structure
2. build admin event workflow
3. build public event/order flow
4. add payment confirmation and SMS
5. add tracking and operational controls
