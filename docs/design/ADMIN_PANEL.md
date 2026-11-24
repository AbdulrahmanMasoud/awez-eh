# Admin panel design

The owner approved this design on 2026-11-24. Every admin step follows it. A change to the look or the layout starts here, with the owner's approval.

- Design canvas (owner's account): https://claude.ai/artifact/A3AnSS8e71MDq9qgUuCCAL, artboard "الرئيسية – لوحة الأدمن" (the approved one; the "نسخة بسيطة" artboard beside it was rejected).
- Static reference in the repository: `docs/design/admin-home.html` (open it in a browser). It is a picture of the target, not code to copy: the panel is built with Filament's own components, themed to match.
- All numbers and names in the reference are demo data.

## Panel

- Filament panel `admin` at `/admin`, Arabic by default, RTL. English is available from the user menu.
- A custom Filament theme (`php artisan make:filament-theme`) carries the colors, the font and the radii below. Filament's components are used as they are; no hand-built replacements for tables, forms, modals or notifications.
- Dark mode is off at first (`->darkMode(false)`); it may come later as its own step.
- The sidebar is collapsible on desktop and becomes Filament's drawer on phones. Wide tables scroll sideways inside their card.

## Brand tokens

| Token | Value | Use |
|---|---|---|
| Primary | `#0B6B37` (hover/dark `#08522A`, light `#E8F4EC`) | primary buttons, active nav item, links, chart bars |
| Accent | `#FFC93C` | avatar, highlights on the dark stat card, "featured" marks |
| Danger / attention | `#C2410C` (text on light `#9A3412`) | review badge, reject, "no reply" |
| Ink | `#14201A` | text, the dark stat card |
| Muted | `#5C6B63` | secondary text (passes 4.5:1 on white and on the background) |
| Background | `#F5F7F4` | page background |
| Surface / border | `#FFFFFF` / `#E3E9E4` | cards, sidebar, top bar |
| Info | `#3A4A8C` on `#E9ECF6` | marketplace items, the "viewing" stage |

- Filament color map: `primary` from the green, `warning` from the yellow, `danger` from the orange, `gray` tinted toward the ink, `info` from the blue.
- Font: Readex Pro (Google Fonts, weights 300–700) through `->font('Readex Pro')`.
- Radii: cards 16px, buttons and inputs 10–12px, badges 6px, pills 999px.
- Icons: one stroke icon set (Heroicons outline, Filament's default), 18px in the sidebar. No emoji anywhere.
- Status badges are tinted pills: negotiation yellow, closed green, viewing blue, new lead gray, reported orange.

## Navigation (sidebar, in this order)

1. الرئيسية (dashboard)
2. المراجعة, with a badge counting everything `pending_review` (orange)
3. Group "المحتوى": العقارات، الصنايعية والخدمات، المطاعم والمحلات، المستعمل، دليل المدينة
4. Group "النشاط": الطلبات والردود، الحجوزات والمعاينات، العمولات والعملاء، التمييز والباقات
5. Group "النظام": المستخدمين والصلاحيات، الإعدادات

An entry appears only when its resource exists; Filament Shield hides what the admin's role cannot see.

## Top bar

- Global search (Filament global search) across listings, users (by name or phone number), requests and places. Results show masked phone numbers.
- Primary action "إضافة عقار" (create a brokered property).
- Database notifications (bell) and the user menu (name, role, language, sign out).

## Dashboard (الرئيسية), top to bottom

1. **Header:** greeting with the admin's first name, today's date in Africa/Cairo, and "عندك N حاجة مستنية قرارك". A period filter (النهارده / الأسبوع / الشهر, default الشهر) drives the widgets below (Filament dashboard filters).
2. **Stats overview, 5 cards:** pending review (dark card, with the age of the oldest item), leads (with change against the previous period), expected commission (Money, from open brokered deals), published listings (split by kind), new users (with the share that confirmed a phone number).
3. **Row of two:**
   - "محتاج قرارك": a table widget of `pending_review` items, oldest first, filter tabs by kind with counts, 4–5 rows and a link to the full review page. Each row: thumbnail or kind icon, title, kind, owner, price if any, age, flags (brokered, verification request, reported). Actions: approve (one click, through the Action) and reject (modal with a required reason, sent to the owner).
   - Leads chart: bars for the last 8 weeks, the current week highlighted, with totals for chats and booked viewings below it.
4. **Brokered deals table:** property, client (masked phone number), stage badge, price, commission, last activity. Link to all deals.
5. **Row of three:** requests without a reply (oldest first, with a button that notifies matching providers), districts with the most demand (bars), and the activity log ("مين عمل إيه", from `spatie/laravel-activitylog`).

Each widget lands with the step that builds its domain; until then it is left out, never filled with fake data.

## Rules that come with it

- Phone numbers are masked everywhere in the panel (`PhoneNumber` string form). The full number appears only on the record's own page, for roles allowed to see it.
- Money shows as `٤٢٬٥٠٠ ج.م` style through the Money cast and the active locale. One digit style across the panel, chosen by the owner (open question; until then Filament's locale formatting).
- Approving and rejecting go through the moderation Actions and are recorded in the activity log.
- Touch targets are at least 44px; every icon-only button has an `aria-label`; text meets 4.5:1 contrast.
