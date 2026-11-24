# AJ LUXE AUTO — Garage & Spare Parts Management System

One combined system for the garage and the parts shop, built with **Laravel 12 + Livewire 3 + Tailwind CSS + MySQL**.

## Website redesign, garage tariff & demo stock (latest)
- **Garage paint price list** from the AJ tariff → Services (codes PNT-…): `php artisan db:seed --class=GarageTariffSeeder` (safe to re-run; also part of the normal seeder)
- **/garage** page: services and prices, paint price list (workshop / spray booth, our painter / your own painter), a calculator that follows the garage rules, booking by WhatsApp
- **Website**: department bar, "Find parts for your car" (make → model), scrolling offers banner, category and "hot right now" rows, AUTODOC-style results with specifications and Buy, oil viscosity / tyre size quick filters, brand filter
- **Parts**: new "Type" (subcategory) and "Specifications" (one "Name: value" per line) fields, also in the CSV import
- **Demo stock** (about 45 parts with pictures, SKU DEMO-…): `php artisan db:seed --class=DemoShopSeeder` — remove it with `php artisan demo:remove`

## Online shop
- **Public website** at the domain root: home page with the best live deal, offers, parts in stock, garage services, contact
- **Shop**: search, category / car make filters, deals only, in stock only, sorting; product pages; live prices and stock from the Parts Catalogue
- **Website discounts** per part (never below the minimum selling price); "Show on website" and a customer description on each part
- **Customer accounts** (separate login, phone or email): sign up, log in, cart, checkout (collect or delivery; Lipa Namba, bank or pay on collection), order tracking, cancel while pending
- **Online Orders** for staff: confirm (creates a spare parts sale and takes stock), ready, collected/delivered, cancel (voids the sale, returns stock)
- The management system dashboard is now at **/dashboard**; staff log in at **/login**

## Phase 5 — Reports, dashboard & data import
- **Reports** (admin/manager) with date presets, CSV export and print: sales summary (garage vs counter, labour/parts/other),
  profit (garage & counter, returns, garage profit by type of work), money in & out by payment method with daily cash-up,
  customers owing and suppliers owed (aged 0–30 / 31–60 / 61–90 / 90+), stock value, best sellers, re-order list and non-moving stock,
  mechanics (jobs, labour, value), services & jobs (top services, turnaround, vehicle makes)
- **Dashboard**: 30-day sales chart, gross profit this month, money in today by method, top parts, quick actions
- **Import Data** (admin): upload CSVs downloaded from Google Sheets — customers, vehicles, suppliers, services, parts with opening stock;
  columns matched automatically, preview, skip or update existing records, row-by-row problem list, blank templates
- **Job cards**: labour, parts and other items are now picked into one list (switch tabs freely) and saved together

## Phase 4 — Parts shop: POS, stock, purchasing, returns
- **POS / New Sale**: tap or search parts (Enter adds an exact SKU), cart with stock limits, walk-in or saved customer,
  part payment on account (saved customers only), 80mm thermal receipt or A4 invoice; managers can change prices and give discounts
- **Void a counter sale** (admin/manager): stock returns, money taken recorded as refunded
- **Stock Register**: stock levels with value at cost and selling price, low / out-of-stock filters, full movement history,
  stock adjustments (set counted qty, add, remove — reason required)
- **Purchase orders**: draft → ordered → part received → received; "add all low-stock parts"; receiving updates stock and buying price;
  supplier payments; printable PO
- **Returns**: customer returns (credit note — stock back in, invoice credited, refund recorded if already paid) and
  returns to supplier (debit note — stock out, PO credited); printable notes

## Phase 3 — Job cards, invoices & payments
- **Job cards**: open from customer search, pick or add a vehicle, mechanic, priority, odometer, fuel, complaint, optional advance
- **Labour, parts and other lines**: parts come out of stock immediately and go back if removed or the job is cancelled;
  garage selling price can differ from the shop price; **cost & profit** visible to admin/manager only
- **Status workflow** Open → In Progress → Waiting for Parts → Quality Check → Ready → Delivered, with history;
  an empty job cannot be Ready/Delivered, Delivered needs an invoice, double-click safe buttons
- **Invoices**: generated once per job (button disabled until items exist), advances carried over, items locked afterwards
- **Payments**: Cash, M-Pesa, bank… with reference; overpayment blocked; receipts
- **Printing (A4)**: job card (workshop copy), proforma (no signatures), tax invoice (with signatures), receipt —
  logo, tagline, CRDB / Lipa Namba payment details, amount in words
- Customer profile shows job and invoice history; dashboard shows vehicles in the workshop

## Phase 1 — Foundation
- Sign-in with **username or email**, "keep me signed in", lock-out after 5 wrong attempts
- **Roles**: Administrator, Manager, Mechanic, Sales Staff — each sees only their menus
- **Users**: create, edit, deactivate, reset password (temporary password shown once; user must change it)
- **Settings**: business profile, logo upload, currency, VAT %, payment details, invoice footer, document number prefixes
- **Lists**: editable dropdowns (car makes, payment modes incl. M-Pesa, part categories, …)
- **Audit log** of every important action
- **Database** for the whole system: customers, vehicles, parts, services, mechanics, job cards (with cost & profit),
  invoices, counter sales, payments, purchase orders, supplier payments, returns and stock movements
- Money everywhere shown as **TZS 364,000**; timezone Africa/Dar_es_Salaam

## All phases complete
Foundation · Masters · Job cards & invoicing · Parts shop · Reports & import. See DEPLOY.md to put it on the VPS.

## Install
See **DEPLOY.md** for step-by-step VPS instructions.

Local development: `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && php artisan serve`
(after changing styles: `npm install && npm run build`).

## Where things are
| What | Where |
|---|---|
| Screens (Livewire) | `app/Livewire/*` + `resources/views/livewire/*` |
| Layout & sidebar menu | `resources/views/components/layouts/app.blade.php` |
| Money format, settings, numbering, audit | `app/Support/*` |
| Database tables | `database/migrations/*` |
| Default admin, lists, services | `database/seeders/DatabaseSeeder.php` |
| Brand colours | `tailwind.config.js` (brand = red, ink = black/greys) |
