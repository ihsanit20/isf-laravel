# Events Dev Plan

This document describes the development plan for the event-based pre-order system.

## 1. Product Direction

The public pre-order site will be a separate brand and a separate domain.

The backend API will stay in the existing Laravel application.

Recommended split:

- Admin panel: Laravel app
- Backend/API: Laravel app
- Public customer site: Nuxt.js or a similar separate frontend

This split keeps the admin side stable and lets the public site evolve independently.

## 2. High-Level Goal

Build a cycle-backed event pre-order system where:

- a fund cycle provides the capital base
- one cycle can contain multiple events
- each event can have multiple packages
- customers can place pre-orders from the public site
- advance payment confirms the order
- the system sends an order number by SMS
- customers can track their order later

## 3. Core Modules

### Backend / Laravel API

The Laravel backend should handle:

- fund cycle data
- event data
- package data
- pickup hub data
- order creation
- payment confirmation
- order tracking data
- SMS logging
- admin actions

### Public Frontend / Nuxt

The public site should handle:

- public event listing
- event details
- package selection
- pickup point selection
- customer order form
- payment instructions
- order confirmation screen
- order tracking screen

### Admin Panel / Laravel

The admin panel should handle:

- create and manage events under a cycle
- create and manage packages
- create and manage pickup points
- publish or unpublish events
- review incoming orders
- confirm payments
- monitor delivery progress
- view logs and summaries

## 4. Recommended Build Order

### Step 1: Freeze the architecture

Decide and lock the following:

- public site will be separate from admin
- Laravel will expose API endpoints
- Nuxt will consume those API endpoints
- database tables will stay simple and cycle-based

### Step 2: Build the minimal database structure

Start with only the tables needed for the first version:

- fund_cycles
- fund_cycle_events
- event_packages
- event_hubs
- event_orders
- event_order_items
- event_payments
- event_order_status_histories

### Step 3: Build API contracts first

Before UI work, define the API responses for:

- public event list
- event details
- package list
- pickup point list
- order create
- order payment confirm
- order tracking

### Step 4: Build admin event management

Admin should be able to:

- create an event under a selected fund cycle
- add packages with price and advance percent
- add pickup hubs
- set order opening and closing time
- set expected delivery date
- publish the event

### Step 5: Build public ordering flow

The public site should allow a customer to:

- browse events
- open one event
- select a package
- select quantity
- choose a pickup hub
- enter phone and delivery details
- submit an order

### Step 6: Add payment confirmation flow

After order submission:

- keep the order pending until payment is completed
- verify the payment
- mark the order confirmed
- generate order number
- send SMS

### Step 7: Add tracking flow

Create a simple tracking flow where a customer can:

- enter order number
- enter phone number
- view current order status
- see the current progress timeline

### Step 8: Add operational controls

Add simple controls for:

- stock or quota protection
- oversell prevention
- manual payment verification
- cancellation handling
- SMS log review

### Step 9: Pilot one event

Before expanding, launch one real event as a pilot.

The pilot should use:

- one fund cycle
- one event
- a small number of packages
- a few pickup points
- manual payment verification first

## 5. Detailed Module Plan

### 5.1 Fund Cycle

The fund cycle is the financial container.

It should store:

- cycle name
- dates
- unit amount
- slots
- status

The event system must stay linked to the cycle.

### 5.2 Event

Each event is a pre-order campaign under one cycle.

It should store:

- event title
- description
- banner image
- status
- order open time
- order close time
- expected delivery date
- visibility state
- notes

### 5.3 Package

Each event can have many packages.

A package should store:

- name
- description
- price
- advance payment percent
- minimum quantity
- maximum quantity
- stock quantity
- active state

### 5.4 Pickup Hub

Each event can have multiple pickup hubs.

A hub should store:

- name
- area
- address
- contact person
- phone number
- active state

### 5.5 Order

An order should store:

- order number
- event reference
- package reference
- customer name
- customer phone
- address or delivery note
- pickup hub
- total amount
- advance amount
- status
- timestamps

### 5.6 Payment

Payment should remain simple in the first version.

It should store:

- order reference
- amount
- method
- reference number
- status
- verification timestamps
- verifier user

### 5.7 Tracking History

Order status changes should be saved in a history table.

This makes tracking and audit easier later.

## 6. Frontend Stack Decision

Because the public site will be on a separate domain and separate brand, a separate frontend is a good fit.

Recommended stack:

- Nuxt.js for the public site
- Laravel for API and admin

Why this is a good fit:

- better separation of concerns
- easier brand isolation
- good SEO support
- easy public content rendering
- clean API-based architecture

## 7. MVP Scope

The first version should stay small and practical.

MVP should include:

- one cycle to many events
- event create/edit/publish
- package create/edit
- pickup hub create/edit
- public event browse
- public order placement
- manual payment confirmation
- SMS order number
- simple tracking page

Do not add complex features in the first release, such as:

- coupons
- refunds
- gateway automation
- delivery slot optimization
- customer accounts with full profile systems
- complex inventory forecasting

## 8. Later Expansion

After the MVP is stable, the system can expand with:

- online payment gateway integration
- customer account history
- refund workflow
- promo codes
- partial payment support
- delivery slot selection
- order analytics
- event sales reports

## 9. Delivery Strategy

Recommended delivery order:

1. database and API contracts
2. admin event setup
3. public event listing
4. public ordering flow
5. payment confirmation
6. SMS confirmation
7. tracking page
8. pilot launch

## 10. Success Criteria

The first version is successful if:

- admins can create and publish an event
- customers can place a pre-order from the public site
- payment can be verified
- order number is generated and sent by SMS
- order can be tracked later
- the system remains simple and stable

## 11. Final Note

The guiding rule is simplicity.

Keep the event system cycle-based, public-facing, and easy to operate.
The public site should feel like a separate brand, but the backend logic should remain centralized in Laravel.
