# AR Community 0.2.0-beta.9.9

- Bare Woo My Account endpoint redirect to `/app/muj-ucet/ar-community/` for signed-in members only; other Woo endpoints unaffected.
- Friends page: Poppins, responsive autocomplete after one character, two invitation modes, explicit selected contact, no unfiltered directory listing.
- Outgoing pending requests shown for 30 days, auto-expire after 30 days.
- Server-verified sender→recipient direction, per-recipient inbox checks and email only for new requests.
- Recipient receives in-app notification; live polling active every ~12 seconds when visible.
- Reference network flags now derive from current viewer's own lifetime customer attribution / affiliate descendants, not globally active partners.

Safety: does not alter Woo orders, Fresh Affiliate links, Stripe, or community accepted relations. Test on staging with two separate accounts.
