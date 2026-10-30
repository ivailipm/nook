# nook: visual design spec

nook is a personal household hub, one person, used on a phone in the shop and on a laptop at a desk. The look is a moody, warm, slightly handmade notebook: dark gradient backgrounds that change per section, serif headings with one italic coloured word, handwritten notes in the margins, deals pinned as taped polaroids, and subscriptions printed as a till receipt.

The look reference is `reference/look-and-feel.png`. The working prototype is `prototype/Main.dc.html`. Its inline styles are the source of truth for exact values when this file and the prototype disagree.

## 1. Fonts (Google Fonts)

| Role | Family | Weights | Used for |
| --- | --- | --- | --- |
| Display | **Fraunces** (variable, opsz) | 500, 600, 700 + italic 500/600 | Screen titles, card titles, the greeting, polaroid product/deal text, receipt total, the `nook` wordmark (italic 600) |
| Body | **DM Sans** | 400, 500, 600, 700 | Everything you read: rows, labels, buttons, chips, metadata |
| Script | **Caveat** | 500, 700 | Handwritten annotations: subtitles under titles, "until Sat" on polaroids, "don't miss these!", "saved €… since cancelling …" |
| Mono | **IBM Plex Mono** | 400, 500, 600 | Receipt, prices, amounts, counts in the sidebar, date inputs |

```
https://fonts.googleapis.com/css2?family=Caveat:wght@500;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=IBM+Plex+Mono:wght@400;500;600&display=swap
```

Type scale:
- Greeting: Fraunces 600, 38px mobile / 52px desktop, line-height 1.02, letter-spacing -0.02em. The name is on its own line in italic 500 peach, with a hand-drawn squiggle SVG under it.
- Screen title (h1): Fraunces 600, 34px mobile / 46px desktop, line-height 1.05. The last word or part of it is italic 500 in the section accent: "Promo *board*", "Shopping *list*", "Small *things*", "Subscrip*tions*".
- Eyebrow above titles: DM Sans 700, 11px, uppercase, letter-spacing 0.14em, cream at 72% ("GROCERIES", "TO-DO", "MONEY", "THURSDAY · 29 OCTOBER").
- Script subtitle under titles: Caveat 20–21px, cream at 80% ("snapped from this week's folders", "one at a time is plenty", "where the money quietly goes", "three small things and you're done").
- Card title (h2): Fraunces 600, 19–21px.
- Body/rows: DM Sans 600 14–15px for item names, 12px cream-72% for metadata.
- Chips/buttons: DM Sans 600–700, 13–15px.

## 2. Colour

### Text and lines (on all dark grounds)
| Token | Value | Use |
| --- | --- | --- |
| `cream` | `#f3ead8` | Primary text and icons |
| `cream-muted` | `rgba(243,234,216,0.72)` | Metadata, eyebrows, inactive nav (≥4.5:1 on every ground) |
| `cream-faint` | `rgba(243,234,216,0.6)` | Placeholders, unchecked checkbox borders |
| `hairline` | `rgba(255,255,255,0.07)` | Row dividers inside cards |
| `card-border` | `rgba(255,255,255,0.10–0.12)` | Card outlines |
| `card-bg` | `rgba(0,0,0,0.24–0.28)` | Translucent cards over the gradient |
| `input-border` | `rgba(243,234,216,0.28–0.30)` | Inputs |
| `paper` | `#f6f1e7` | Polaroid frames, toasts, the TRIAL sticker |
| `receipt` | `#f7f2e6` on `#2a241c` ink, muted `#5b5246`, dashed rules `#b9ad98` | The subscription receipt |
| `sheet-bg` | `#1d1714` | Bottom sheets and dialogs |
| `scrim` | `rgba(8,5,4,0.62)` | Behind sheets |

### Section grounds (each screen has its own gradient)
| Section | Background | Accent (italic word, primary button) | Text on accent |
| --- | --- | --- | --- |
| Home | `radial-gradient(120% 55% at 100% 0%, #7a2a33 0%, transparent 60%), linear-gradient(180deg, #4a1520 0%, #2a0c12 55%, #190a0d 100%)` (burgundy) | peach `#f0b48a` | `#2a140c` |
| Groceries + Promo board | `radial-gradient(110% 55% at 0% 0%, #2f6b48 0%, transparent 60%), linear-gradient(180deg, #17402c 0%, #0f2a1e 55%, #0a1a13 100%)` (forest) | gold `#e8c46a` | `#2a2008` |
| To-do | `radial-gradient(110% 55% at 100% 0%, #6b5326 0%, transparent 60%), linear-gradient(180deg, #3a2b14 0%, #241a0c 55%, #160f07 100%)` (warm brown) | apricot `#f2c88a` | `#2a1a06` |
| Subscriptions | `radial-gradient(90% 45% at 100% 0%, #8a5a3a 0%, transparent 55%), linear-gradient(180deg, #11393c 0%, #0b2729 55%, #071a1b 100%)` (deep teal, copper glow) | peach `#f0b48a` | `#2a140c` |

The FAB and "Add something" button are always peach `#f0b48a`.

### Status
| Meaning | Colour | Notes |
| --- | --- | --- |
| Overdue / high priority | `#f4a394` | Always with a word ("overdue · was yesterday", "High") |
| Due today / medium priority | `#f2c88a` | |
| Ends tomorrow or today (on paper) | `#a8361f` | Handwritten on polaroids, TRIAL sticker |
| "on list" mark (on paper) | `#2c5a3e` outline pill | |
| Destructive button | text `#f4a394`, border `rgba(240,150,130,0.6)` | Outline only |

### Polaroid tones (inner photo area when there is no photo)
| # | Background | Text | Tape |
| --- | --- | --- | --- |
| 0 | `#3b2a22` espresso | `#f3ead8` | `#e6c76a` |
| 1 | `#cfdccf` sage | `#24332a` | `#9fb0c6` |
| 2 | `#efe9df` cream | `#1f2b4a` | `#e9a98a` |
| 3 | `#f0cf7a` mustard | `#3a2a08` | `#a9bfa3` |
| 4 | `#d5deea` pale blue | `#1f2b4a` | `#9fb0c6` |
| 5 | `#4d6b4a` moss | `#f3ead8` | `#e6c76a` |

### Subscription categories (chart and avatar rings)
Health `#f0b48a` · Entertainment `#efe6d2` · Storage `#7fa2a6` · Education `#c9a2d8` · Other `#e2b65a`. The legend always shows the name, % and amount, so colour is never the only label.

## 3. Shape, space, depth

- Radii: cards 18px, inputs and quick-add 16px, buttons 10–12px, small avatars 10px, chips/pills/FAB 999px, polaroid frame 3px, checkboxes 4–6px (groceries) or round (tasks).
- Spacing: 4px base. Mobile screen padding 20px 18px with 120px bottom padding to clear the nav; desktop 44px 48px. Gap between sections 18px mobile / 24px desktop; gap inside grids 14–16px.
- Touch targets ≥44px (checkbox hit areas are 44×44 even though the box is 22px).
- Shadows only on things that float or are "physical": polaroids `0 12px 24px rgba(0,0,0,.4)`, receipt drop-shadow `0 14px 22px rgba(0,0,0,.45)`, nav and FAB, sheets, toasts. Cards themselves are flat and translucent.
- Focus ring: 2px solid peach `#f0b48a`, offset 2px, on everything interactive.

## 4. Components

**Floating nav (mobile, < 900px).** An absolute pill 14px from the left, right and bottom edges, 64px tall, radius 22px. Background `rgba(18,12,10,0.78)` with `backdrop-filter: blur(14px)`, a 1px border at white 10% and a shadow. Five columns: Home, Groceries, [FAB], To-do, Subs. Each tab is a 20px line icon over a 10px label. Active is cream 700 weight; inactive is cream at 62%. The FAB is a 54px peach circle raised 30px above the bar, with a 4px dark ring. What it opens depends on the screen: Home opens the chooser, Groceries opens add item, Promos opens add promo, To-do opens add task and Subs opens add subscription.

**Sidebar (desktop, ≥ 900px).** 236px wide, background black at 28% with a right hairline. At the top is the `nook` wordmark (Fraunces italic 600, 30px). Below it are 44px nav rows with an icon, a label and a mono count on the right; the active row gets a cream 12% background. The nav has separate entries for Home, Groceries, Promo board, To-do and Subscriptions. At the bottom are an "Add something" peach pill and the date in Caveat.

**Card.** Translucent `card-bg`, 1px `card-border`, 18px radius, 16px padding. Fraunces title on the left, with a 12px muted meta or a peach "→" link on the right. Rows are separated by hairlines.

**Checkbox.** Grocery: a 22px rounded square with a 2px border at cream 60%; checked is filled with the section accent and a dark check. Task: the same but round. Home cards use a smaller 18px version. Checked rows get a line-through at 55% opacity and stay in place until "Clear ticked".

**Chips.** 34–36px pills. Off: black 28% background, white 16% border, cream text. On: cream background with near-black `#1d1714` text. Used for group-by, store filters, tag filters and every form choice.

**Segmented switch (List | Promo board).** A black 30% pill track with two buttons. The active one is cream with dark green text. The inactive label shows a count ("Promo board · 6", "List · 6").

**Quick-add bar.** One rounded field with a transparent input (16px text so iOS doesn't zoom) and an accent "+ Add" button. A 12px syntax hint sits under it.

**Promo badge on a list item.** A pill with a tag icon and the deal text ("2+1 free", "−30% · Delhaize" on desktop). It uses the gold accent in Groceries and soft peach `#f6d9c4` with `#4a1d10` text on Home. It's a button that opens the promo sheet, and it greys out when the item is ticked.

**Polaroid (promo card).** `#f6f1e7` frame with 8–9px padding and a fat bottom margin. It is rotated by a small varying angle (−2.2°, 1.8°, −1.2°, 2.4°, −1.8°, 1.2°, cycling). A washi-tape strip (54–58×17px, tone colour at 88% opacity, rotated a few degrees) sits over the top edge. Inside is a square photo area: either the uploaded photo (object-fit cover) or a tone block with the STORE in 9px caps, the product in Fraunces 600 and the deal in Fraunces 700 22px. Under the photo is a Caveat note: "until Sat" in dark brown, "ends tomorrow!" in `#a8361f`, or "expired 27 Oct". An outlined green "on list" pill shows when it's linked. Expired polaroids are greyscale at 55% opacity. The board is a 2-column grid on mobile and `auto-fill minmax(180px, 1fr)` on desktop.

**Snap card.** A full-width card with a 40px gold camera tile, then "Snap or share a deal" and "Screenshot a folder page, then Share → nook. Or upload it here." Tapping it opens the add-promo sheet.

**Receipt.** Off-white paper rotated −1°. From top to bottom it shows:
- `* * * NOOK · OCTOBER * * *` and `THIS MONTH`
- the total in Fraunces 700 46px
- `≈ €575.52 a year`
- rows separated by dashed rules: the name in mono 600, a meta line ("monthly · next Sun 1 Nov"; trials say "free trial · ends …" in red) and the amount on the right
- a solid rule, then `TOTAL / MONTH`, then `THANK YOU · COME AGAIN`

The bottom edge is a zig-zag tear (an SVG with 20 teeth).

**Trial card.** A dark card with a "TRIAL" paper sticker in the top-right corner (rotated 4°, red border). Title: "Duolingo Super turns paid on Monday". Detail: "Free until Mon 2 Nov, then €12.99 / month." Buttons: Remind me Sunday (peach filled), Keep it (outline) and Cancel trial (outline).

**By-category card.** A 12px stacked bar with 2px gaps between segments and 4px rounded ends. Below it is a legend: colour square, name, %, and the amount in mono. A Caveat peach line follows: "saved €15.98 since cancelling Disney+".

**Bottom sheet / dialog.** On mobile it slides up from the bottom (radius 24 24 0 0, max-height 88%, grab handle). On desktop it's a centred 480px dialog with radius 22. Both use `#1d1714`, a Fraunces 22px title and a round close button, over the scrim.

**Toast.** A `#f6f1e7` pill with dark text above the nav (mobile) or bottom-centre (desktop). It auto-hides after about 2.6s.

## 5. Layout

- One codebase. The breakpoint is **900px**: below it you get the floating nav and single-column cards; above it, the sidebar and multi-column grids with content capped at 1120px.
- Home desktop: greeting, then chips, then a 2-column grid (Today | Groceries), then Deals ending soon as a polaroid row, then Coming up.
- Groceries desktop: group cards in `auto-fill minmax(320px,1fr)`.
- To-do desktop: task groups on the left, the morning-nudge card on the right.
- Subscriptions desktop: the receipt on the left (1fr), with trial, category and all-subscriptions cards on the right (1.1fr).

## 6. Icons

Line icons, 24px grid, 1.75 stroke, round caps and joins, currentColor. Use **Lucide**: house, shopping-bag, tag, list, receipt, camera, bell, repeat, archive, x, plus, check, sparkles. No emoji anywhere in the UI.

## 7. Copy voice

Short, warm, lowercase-friendly in the handwritten bits ("don't miss these!", "all clear today. go enjoy your evening"). Sentence case in UI labels. Money: `€3.49` with a dot decimal. Dates: relative when near ("today", "tomorrow", "Sat"), otherwise "10 Nov". Long form is "Sun 1 Nov".
