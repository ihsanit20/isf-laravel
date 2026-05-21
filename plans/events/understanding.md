# Events Domain Understanding

This note captures what I understand about the event system you want to build under a fund cycle.

## Core Idea

- A fund cycle can contain one or more events.
- Each event represents a real-world pre-order campaign.
- The cycle acts as the financial source and accountability container for that event.
- Money allocated under a cycle can be used to run the event, procure goods, handle packaging, and support delivery.

## Event Meaning

An event is not just a notice or a simple post. It is a campaign with business rules.

Example:

- Event name: `রূপালী আমের মেলা`
- Purpose: collect pre-orders for a product campaign
- Source of capital: the related fund cycle

## What an Event Should Contain

I understand that each event should be able to store:

- title and short title
- detailed description
- banner or cover image
- event status
- order opening time
- order closing time
- expected delivery date
- public visibility state
- notes and internal remarks

## Package System

Each event should support multiple packages.

A package is a sellable unit, such as:

- 2 kg box
- 5 kg box
- premium bundle
- family bundle

Each package should support:

- package name
- package price
- package description
- advance payment percentage
- minimum and maximum order quantity
- stock or quota
- active/inactive state

The advance payment percentage is important because order confirmation depends on payment.

## Delivery Hubs or Pickup Points

I understand that an event should allow multiple delivery hubs or pickup points.

Each hub may include:

- hub name
- address
- area
- contact person
- phone number
- active/inactive state

When a customer places an order, they should be able to select a hub or pickup point.

## Public Ordering Flow

This event system should also be visible on another public-facing site.

Public users should be able to:

- browse published events
- view event details
- choose a package
- select quantity
- select a pickup point or hub
- enter name, phone, and delivery-related info
- see the advance payment requirement
- complete payment
- receive an order number by SMS
- track the order using that order number

## Payment and Confirmation

I understand the order should not be treated as confirmed until the advance payment is completed.

Important points:

- advance payment is the confirmation trigger
- payment confirmation can be manual or gateway-based later
- confirmed orders receive an order number
- SMS should be sent after confirmation
- order state should be trackable after confirmation

## Order Tracking

The public should be able to track an order using the order number.

A simple tracking flow should eventually show:

- pending payment
- confirmed
- processing
- ready for pickup
- out for delivery
- delivered
- cancelled

## Admin Side

Inside this ISF project, the admin side should manage:

- fund cycle selection
- event creation and publishing
- package creation
- pickup point setup
- order monitoring
- payment confirmation
- SMS logging
- event lifecycle management

## Reality Check

To keep this realistic and manageable, I think the first version should avoid overbuilding.

The minimum useful flow is:

- create event under a cycle
- add packages
- add pickup points
- define order window
- publish event publicly
- accept pre-orders
- confirm orders after advance payment
- send SMS order number
- allow tracking

## What I Understand You Want

In short, you want a cycle-backed pre-order system where:

- the fund cycle supplies the capital
- the event runs under that cycle
- the public site shows the event
- the public can place orders
- advance payment confirms the order
- the system sends an SMS order number
- the user can track the order later

## Suggested Next Phase

After this understanding is locked, the next practical steps are:

1. define the database structure
2. define the admin workflow
3. define the public site workflow
4. then build the event module step by step
