# nook: feature spec

Everything below is clickable in the prototype (`prototype/Main.dc.html`; the `renderVals()` and helper methods at the bottom hold the exact logic). The prototype keeps data in memory with sample data; the real app persists it per user.

## Data model

```
GroceryItem   id, name, qty (free text: "2", "1kg", "300g"), store, category, done (bool), promo_id (nullable), timestamps
Promo         id, store, product (nullable), deal (free text: "2+1 free", "−30%", "€3.49"), note (nullable, e.g. "€2.09 instead of €2.99"),
              valid_from (date, nullable), valid_until (date, REQUIRED), photo_path (nullable), tone (0–5, polaroid colour), timestamps
Task          id, title, tag (school | home | internship), due_date (nullable), priority (high | med | low | null),
              repeat_weekday (0–6, nullable = not recurring), done (bool), completed_at, timestamps
Subscription  id, name, price (decimal), cycle (monthly | yearly), next_payment_date, category (Health | Entertainment | Storage | Education | Other),
              is_trial (bool), trial_reminder (bool), cancelled_at (date, nullable), timestamps
Settings      morning_nudge (bool, default on), push subscription
```
Stores: Aldi, Lidl, Delhaize, Colruyt, Other. Grocery categories: Fruit & veg, Dairy, Pantry, Household.

## 1. Home (dashboard)

- Eyebrow with today's date. Below it: "Good evening, *Ivayla*" (morning/afternoon/evening by the hour) and a Caveat line counting today's open tasks in words: "three small things and you're done". With nothing left it reads "all clear today. go enjoy your evening".
- Chips: "N to buy" (opens Groceries), "N deals ending" (promos ending within 3 days; opens the Promo board), "€X / mo" (opens Subscriptions).
- **Today** card: tasks due today or overdue, open ones first, each tickable inline, plus an "All tasks →" link.
- **Groceries** card: the first 5 open items, tickable. A promo badge shows when one is linked (tapping it opens the promo sheet); otherwise the store name. Has a "Promo board →" link.
- **Deals ending soon**: polaroids of active promos ending within 3 days (4 on mobile, 5 on desktop). Tapping one opens the promo sheet.
- **Coming up**: subscriptions paying or trials ending in the next 14 days, with the date and amount ("then €12.99" for trials).
- The bell button toggles the morning nudge.

## 2. Groceries list

- Segmented switch: List | Promo board.
- **Quick add** parses one line:
  - a leading number with an optional unit (`2`, `2x`, `1kg`, `300g`, `1l`) is the quantity
  - `@lid` picks a store by prefix
  - `#dai` picks a category by prefix
  - the rest is the name, first letter capitalised
  - defaults: store Lidl, category Pantry
  - Enter adds the item and keeps focus.
- **Auto-link**: when an item is added, look for an active promo whose product contains the item's first word (≥3 letters), preferring the same store. If found, link it, adopt the promo's store, and toast "Added, and linked to the Colruyt deal: 2+1 free".
- Group by **Store** or **Category**: one card per group with "N left" (or "all done"). Open items come first. Each row's sub-line shows the qty plus the other grouping (the category when grouped by store, and vice versa).
- Row: a 44px checkbox hit area, the name, the sub-line, a promo badge (button → promo sheet) and a remove ×.
- Ticked items stay in place, struck through, until **Clear N ticked**.
- The full add form (from the FAB / Add) has name, qty, store chips and category chips.

## 3. Promo board

- Snap card → add-promo sheet.
- Store filter chips (All, Aldi, Lidl, Delhaize, Colruyt).
- Polaroid grid of **active** promos, sorted by soonest `valid_until`. The handwritten label reads "ends today!" / "ends tomorrow" (red), "until Sat" or "until 10 Nov". Linked promos show "on list".
- **Auto-cleanup**: a promo with `valid_until < today` is expired. It leaves the board and appears in the **archive** (toggle button "N expired this week · archive" ↔ "← Back to the board"), greyscale and faded. Linked list items lose their badge. Do this with a daily scheduled job plus a query scope, so nothing depends on the UI.
- **Promo sheet** (from a polaroid or a list badge):
  - a big polaroid with the photo (object-fit contain)
  - store and validity line, note, and "On your list: Coffee beans (1kg)" or "Not on your list yet"
  - linked: **Got it · tick it off** (ticks the item, closes) and **Unlink**
  - not linked and active: **Add to my list**, which creates an item named after the product (trailing size stripped) with the promo linked
  - always: **Delete promo**, which also unlinks items
- **Add promo** form:
  - photo/screenshot upload (camera on mobile via `accept="image/*"`) with a preview
  - store (required)
  - valid from (default today)
  - **valid until (required, default today + 5)**
  - product, deal or price
  - "Link to a list item" chips (open items without a promo, plus None)
  - Saving jumps to the board and toasts the validity.
- **Android Share Target**: the web app manifest declares
  ```json
  "share_target": { "action": "/promos/share", "method": "POST", "enctype": "multipart/form-data",
                    "params": { "title": "title", "text": "text", "files": [{ "name": "photo", "accept": ["image/*"] }] } }
  ```
  Sharing a screenshot from any Android app opens the add-promo form with the photo already attached. On iOS the upload button is the path.
- **Later**: a "Read from photo" button (shown disabled now, sparkles icon). It will send the image to the Claude API and pre-fill store, product, price and end date. Fields filled this way stay marked until the user confirms them.

## 4. To-do

- **Quick add** parses one line:
  - `today` / `tomorrow` / a weekday name (next occurrence) set the due date
  - `!high` `!med` `!low` set the priority
  - `#school` `#home` `#internship` set the tag
  - `every tue` sets weekly recurrence (and the due date to the next Tuesday if none was given)
- Tag filter chips: All, school, home, internship.
- Groups: **Overdue** (red title), **Today**, **Upcoming** (sorted by date; no-date last), **Done**, each with a count.
- Row: a round checkbox, the title, and a meta line with the priority (shape + word: high = square, medium = dot, low = ring), "due today" / "overdue · was yesterday" / "due Fri", a repeat icon + "every Thursday", and a tag pill.
- **Recurring**: completing a recurring task marks it done and creates the next one 7 days later. Toast: "Done. Next one: Thu 5 Nov".
- The full add form has the title, due chips (Today, Tomorrow, a weekday, Next week, No date), priority chips, tag chips, and repeat (Once / Every week).
- **Morning nudge** card with a toggle: a web push notification at 08:00 listing today's and overdue tasks (scheduled job + Web Push / VAPID).

## 5. Subscriptions

- **Receipt**: active subscriptions sorted by next payment, with the monthly equivalent (yearly ÷ 12). Trials show €0.00 with "free trial · ends Mon 2 Nov". Total / month, plus ≈ a year (× 12).
- **Trial card**: shown for a trial converting within 7 days.
  - **Remind me** (the day before) toggles the reminder; it becomes "Reminder set · Sun 1 Nov".
  - **Keep it** turns it into a paid subscription.
  - **Cancel trial** marks it cancelled.
  - Every trial gets push reminders **3 days and 1 day** before it converts.
- **By category**: a stacked bar plus legend (name, % of the monthly total, amount) for paying subscriptions only. Below it: "saved €X since cancelling A, B", where savings = monthly price × months since cancelling (min 1).
- **All subscriptions**: rows with a letter avatar ringed in the category colour, the name, meta ("Entertainment · monthly · next Tue") and price/cycle. A Cancel / Resume button toggles `cancelled_at`; cancelled rows go to the bottom, struck through. "+ Add" opens the form.
- Add form: name, price (decimal, accepts a comma), billing (Monthly / Yearly), category chips, next payment date (labelled "Trial ends" when the trial toggle is on), and a free-trial toggle.

## 6. Cross-cutting

- **PWA**: installable (manifest, icons, theme colour `#190a0d`), service worker, app shell cached so the grocery list opens in a shop with bad signal.
- **Notifications** (web push): morning nudge 08:00; trial reminders 3 days and 1 day before; optional "a deal on your list ends today" at 08:00.
- Toasts confirm every create, link, cancel and recurring rollover.
- Accessibility: real buttons and labels, `aria-pressed` on toggles and chips, `aria-label` on icon buttons, ≥44px targets, a visible focus ring, and state never shown by colour alone.
- Locale: Belgian context, euros, dates like "Sun 1 Nov", week starts Monday.
