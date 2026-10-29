# 2.0.3
- Delivery profiles are now also validated when written via the Admin API (deadline time, excluded months, occurrence); invalid values are rejected
- An invalid stored profile is logged once per PHP process instead of on every product load

# 2.0.2
- New plugin icon in Nova style
- Repository renamed to SW6-Nova-Lieferzeiten

# 2.0.1
- New display name "Nova Delivery Dates" (technical name MonthlyDeliveryPlugin unchanged – no reinstall needed)

# 2.0.0
- Shopware 6.7: minimum version is now 6.7 (Composer `~6.7.0`); all template overrides and blocks checked against Shopware 6.7.15
- Store API: `monthlyDelivery` registered as an `ApiAware` runtime field on the product definition – the date is now included in all Store API product responses (e.g. for the app) under `extensions.monthlyDelivery`; previously Shopware filtered the extension out
- Product page: `availability` microdata only without JSON-LD (feature `JSON_LD_DATA`), as in the Shopware original; digital products keep the default display
- Technical: PHPStan level 8 clean, test for the payload keys used by the app

# 1.0.1
- Admin: ACL permissions for delivery profiles (view/edit/create/delete) under Settings → Roles, so the module can be granted to roles without full admin access

# 1.0.0
- First release: fixed monthly shipping dates and order deadlines per supplier (delivery profiles)
- Display on product detail page, cart, off-canvas cart, checkout, finish page and order history
- Promised date is stored in the order line item (for emails and customer account)
- Activation via custom field only, ERP fields delivery time/restock time stay untouched
- HTTP cache lifetime limited until the next order deadline
