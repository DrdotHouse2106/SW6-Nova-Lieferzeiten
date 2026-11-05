# Nova Lieferzeiten – dynamische Monats-Lieferzeiten für Shopware 6.7

[![Spendier mir einen Kaffee](https://img.shields.io/badge/☕_Spendier_mir_einen_Kaffee-PayPal-0070ba?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/DrdotHouse)

*[English version](README.en.md)*

Shopware-6-Plugin (`MonthlyDeliveryPlugin`), das für Produkte bestimmter Lieferanten einen **fixen monatlichen Versandtermin** und den dazugehörigen **Bestellschluss** berechnet. Beides wird angezeigt auf der Produktdetailseite, im Warenkorb, im Off-Canvas-Warenkorb, im Checkout, auf der Abschlussseite, in der Bestellhistorie und in E-Mails.

Für **jeden Lieferanten kann eine eigene Regel** hinterlegt werden (Lieferprofile).

---

## Inhalt

1. [Berechnungslogik](#1-berechnungslogik)
2. [Lieferprofile je Lieferant](#2-lieferprofile-je-lieferant)
3. [Aktivierung und WaWi-Schutz (ERPNext / WeClapp)](#3-aktivierung-und-wawi-schutz-erpnext--weclapp)
4. [Storefront-Integration](#4-storefront-integration)
5. [E-Mail-Vorlagen](#5-e-mail-vorlagen)
6. [Installation](#6-installation)
7. [Konfiguration](#7-konfiguration)
8. [HTTP-Cache](#8-http-cache)
9. [Entwicklung und Tests](#9-entwicklung-und-tests)
10. [Code-Struktur](#10-code-struktur)
11. [Gestaltung (CSS-Klassen)](#11-gestaltung-css-klassen)

---

## 1. Berechnungslogik

Standardregel (Profil `standard`):

| Kürzel | Bedeutung | Standard |
|---|---|---|
| **L** | Eintreffdatum beim Shop | letzter Mittwoch des Monats |
| **D** | Bestellschluss für Kunden | L − 7 Tage (= vorletzter Mittwoch), 23:59:59 Uhr |
| **V** | Versanddatum an den Kunden | Montag nach L (= L + 5 Tage) |

Entscheidung:

- `now <= D` → Versand **diesen** Monat (V und D dieses Monats)
- `now > D` → Versand im **Folgemonat** (V und D des Folgemonats)

Beispiel Oktober 2026 (Mittwoche: 7., 14., 21., 28.):

| Bestellung am | Bestellschluss | Eintreffen | Versand |
|---|---|---|---|
| 07.10.2026 | Mi, 21.10.2026 23:59:59 | 28.10.2026 | **Mo, 02.11.2026** |
| 22.10.2026 00:00 | Mi, 18.11.2026 23:59:59 | 25.11.2026 | **Mo, 30.11.2026** |
| 01.12.2026 | Mi, 23.12.2026 23:59:59 | 30.12.2026 | **Mo, 04.01.2027** |

Die Berechnung erfolgt in der konfigurierten Shop-Zeitzone (Standard `Europe/Berlin`). Sie steckt in `DeliveryCalculatorService` und hat keine Abhängigkeiten zu Shopware, ist also vollständig unit-getestet.

## 2. Lieferprofile je Lieferant

Unter **Einstellungen → Erweiterungen → Monats-Lieferprofile** legen Sie beliebig viele Profile an:

| Feld | Bedeutung | Beispiel |
|---|---|---|
| Name | Anzeigename (z. B. Lieferant) | „Lieferant Müller GmbH“ |
| Technischer Name | eindeutiger Schlüssel, z. B. für die WaWi | `mueller` |
| Aktiv | inaktiv = fixer Termin wird für zugeordnete Produkte ausgeblendet | – |
| Eintreffen (Vorkommen) | erster, zweiter, dritter, vierter, vorletzter oder letzter … | letzter |
| Eintreffen (Wochentag) | Montag … Sonntag | Mittwoch |
| Bestellschluss: Tage vorher | 0–27 Tage vor dem Eintreffen | 7 |
| Bestellschluss: Uhrzeit | HH:MM oder HH:MM:SS | 23:59:59 |
| Versand: Wochentag | nächster Wochentag **nach** dem Eintreffen | Montag |
| Monate ohne Lieferung | z. B. `8, 12` (Betriebsferien) – diese Monate werden übersprungen | – |

Bei der Installation wird automatisch das Profil `standard` mit der Regel aus Abschnitt 1 angelegt.

**Welches Profil gilt für ein Produkt?** Die erste Fundstelle gewinnt:

1. Zusatzfeld **am Produkt**: `custom_supplier_monthly_delivery_profile`
2. Zusatzfeld **am Hersteller/Lieferanten**: `custom_supplier_monthly_delivery_manufacturer_profile`
3. **Standard-Lieferprofil** aus der Plugin-Konfiguration
4. Eingebaute Standardregel (letzter / vorletzter Mittwoch / Montag)

So können Sie das Profil einmal am Hersteller hinterlegen und es bei Bedarf an einzelnen Produkten überschreiben. Im Zusatzfeld darf die **ID** (Auswahl im Admin) oder der **technische Name** des Profils stehen. Den technischen Namen kann eine WaWi einfach per API schreiben.

## 3. Aktivierung und WaWi-Schutz (ERPNext / WeClapp)

- Das Plugin **liest und schreibt niemals** `product.deliveryTime`, `product.restockTime` oder andere Standard-Lieferfelder. Ein WaWi-Sync kann diese Felder also weiterhin frei überschreiben, ohne dass sich das auf den fixen Termin auswirkt.
- **Aktiviert** wird das Plugin für ein Produkt **ausschließlich** über das Zusatzfeld
  `customFields.custom_supplier_monthly_delivery == true`
  (Admin: Produkt → Reiter „Spezifikationen“ → Zusatzfelder → „Monatliche Lieferanten-Lieferung“).
- Varianten erben das Zusatzfeld vom Hauptprodukt, wenn es an der Variante nicht gesetzt ist.
- Bei Updates führt Shopware Zusatzfelder per JSON zusammen. Schreibt die WaWi eigene Zusatzfelder, bleiben die Plugin-Felder erhalten. Die WaWi darf das Feld aber auch selbst setzen, z. B. `custom_supplier_monthly_delivery: true` und `custom_supplier_monthly_delivery_profile: "mueller"`.
- Der Termin wird **nur in der Anzeige** ersetzt. Die Shopware-Lieferzeit bleibt im Datenbestand unverändert. Für inaktive, ausverkaufte Abverkaufs- und Vorbestell-Artikel greift weiterhin die Standardanzeige.

> **Hinweis:** Einige WaWi-Connectoren senden bei jedem Sync das komplette `customFields`-Objekt mit `null`-Werten. Prüfen Sie in diesem Fall, ob die Plugin-Felder vom Mapping ausgeschlossen sind, oder pflegen Sie das Profil am Hersteller.

## 4. Storefront-Integration

| Ort | Template | Ausgabe |
|---|---|---|
| Produktdetailseite (klassisch und CMS-Buy-Box) | `component/delivery-information.html.twig` | „Nächster fixer Versandtermin: **Montag, 02.11.2026**“ + „(Bestellschluss für diese Lieferung: Mittwoch, 21.10.2026, 23:59 Uhr)“ |
| Warenkorb, Off-Canvas, Checkout-Bestätigung, Abschlussseite, Bestellhistorie | `component/line-item/type/product.html.twig` | Versandtermin und Bestellschluss direkt am Artikel |
| Standard-Lieferdatum am Artikel | `component/line-item/element/delivery-date.html.twig` | wird für betroffene Artikel ausgeblendet |
| Checkout-Bestätigung | `page/checkout/confirm/index.html.twig` | Hinweisbox mit allen fixen Versandterminen der Bestellung |
| Abschlussseite (order_confirmation) | `page/checkout/finish/index.html.twig` | Hinweisbox mit den zugesagten Versandterminen |
| Produkt-Listing (optional) | `component/product/card/box-standard.html.twig` | Versandtermin in der Produktkachel |

Die Ausgabe selbst steckt im wiederverwendbaren Partial `@MonthlyDeliveryPlugin/storefront/component/monthly-delivery/info.html.twig`. Die Texte sind Textbausteine (`monthlyDelivery.*`) und lassen sich unter **Einstellungen → Textbausteine** anpassen.

**Hinweis zu den genannten Templates:** Ab Shopware 6.6 (auch 6.7) liegt die Lieferzeit-Ausgabe nicht mehr in `buy-widget-form.html.twig`, sondern in `component/delivery-information.html.twig`. Dieses Template wird sowohl vom klassischen Buy-Widget als auch von der CMS-Buy-Box genutzt. Die Warenkorb-Positionen werden über `component/line-item/*` statt über `cart-item-children.html.twig` gerendert. Alle überschriebenen Templates und Blöcke sind gegen Shopware 6.7.15 geprüft.

Digitale Produkte, inaktive Produkte, ausverkaufte Abverkaufsartikel und Vorbestellungen behalten auf der Produktseite die Standardanzeige. Die Microdata-Angabe `availability = BackOrder` wird – wie im Shopware-Original – nur ausgegeben, wenn strukturierte Daten nicht als JSON-LD erzeugt werden (Feature `JSON_LD_DATA`).

### Verfügbare Daten

- Produkt: `product.extensions.monthlyDelivery`
- Produktdetailseite zusätzlich: `page.extensions.monthlyDelivery`
- Warenkorb- und Bestellpositionen: `lineItem.payload.monthlyDelivery`

**Store-API (z. B. für eine App):** `monthlyDelivery` ist als Laufzeitfeld mit `ApiAware` an der Produkt-Definition registriert (`ProductMonthlyDeliveryExtension`). Dadurch steht es in allen Store-API-Antworten mit Produkten unter `extensions.monthlyDelivery` – Produktliste, Produktdetail, Suche, Listing, Cross-Selling. Ohne diese Registrierung entfernt Shopware unbekannte Extensions aus Store-API-Antworten. Das Feld wird nicht gespeichert und erzeugt keine Datenbankspalte. Warenkorb-Antworten enthalten den Termin wie bisher in `lineItems[].payload.monthlyDelivery`.

Inhalt:

```json
{
  "profile": "standard",
  "profileName": "Standard (letzter Mittwoch)",
  "shipDate": "2026-11-02",
  "shipDateFormatted": "02.11.2026",
  "shipWeekday": 1,
  "deadline": "2026-10-21T23:59:59+02:00",
  "deadlineDate": "2026-10-21",
  "deadlineFormatted": "21.10.2026",
  "deadlineTime": "23:59",
  "deadlineWeekday": 3,
  "arrivalDate": "2026-10-28",
  "arrivalDateFormatted": "28.10.2026",
  "arrivalWeekday": 3
}
```

Der Payload wird bei jeder Warenkorb-Berechnung neu ermittelt. Beim Absenden der Bestellung wird er in `order_line_item.payload` gespeichert. Die Bestellung enthält also dauerhaft den **zum Bestellzeitpunkt zugesagten** Termin. Wer kurz vor dem Bestellschluss in den Warenkorb legt und erst danach bestellt, bekommt den korrekten neuen Termin angezeigt und gespeichert.

## 5. E-Mail-Vorlagen

E-Mail-Vorlagen liegen in der Datenbank und werden deshalb nicht automatisch überschrieben. Fügen Sie unter **Einstellungen → E-Mail-Templates → Bestellbestätigung** an der gewünschten Stelle ein:

**HTML – Kopfbereich (Zusammenfassung):**

```twig
{% set monthlyDeliveryDates = {} %}
{% for lineItem in order.lineItems %}
    {% if lineItem.payload.monthlyDelivery is defined and lineItem.payload.monthlyDelivery %}
        {% set md = lineItem.payload.monthlyDelivery %}
        {% set monthlyDeliveryDates = monthlyDeliveryDates|merge({ (md.shipDate): md.shipDateFormatted }) %}
    {% endif %}
{% endfor %}
{% if monthlyDeliveryDates is not empty %}
    <p style="padding:10px;background:#eef6fc;border:1px solid #b8daf2;">
        <strong>Fixer Versandtermin:</strong>
        Die Artikel mit monatlicher Lieferung versenden wir am
        {% for date in monthlyDeliveryDates %}<strong>Montag, {{ date }}</strong>{% if not loop.last %} bzw. {% endif %}{% endfor %}.
    </p>
{% endif %}
```

**HTML – je Position (in der Positionsschleife `{% for lineItem in nestedItems %}`):**

```twig
{% if lineItem.payload.monthlyDelivery is defined and lineItem.payload.monthlyDelivery %}
    <br><small>Versand am {{ lineItem.payload.monthlyDelivery.shipDateFormatted }}</small>
{% endif %}
```

**Text-Version:**

```twig
{% for lineItem in order.lineItems %}{% if lineItem.payload.monthlyDelivery is defined and lineItem.payload.monthlyDelivery %}
{{ lineItem.label }}: Versand am {{ lineItem.payload.monthlyDelivery.shipDateFormatted }}
{% endif %}{% endfor %}
```

> Hinweis: Im obigen Beispiel steht „Montag“ fest im Text. Wenn Profile mit anderem Versand-Wochentag genutzt werden, passen Sie den Text an. Der Wochentag steht als Zahl in `shipWeekday` (1 = Montag … 7 = Sonntag).

## 6. Installation

```bash
# Plugin nach custom/plugins/MonthlyDeliveryPlugin kopieren (oder per Composer installieren), dann:
bin/console plugin:refresh
bin/console plugin:install --activate MonthlyDeliveryPlugin

bin/console cache:clear
```

Das Admin-Modul (Lieferprofile) ist **fertig gebaut** enthalten (`src/Resources/public/administration`, gebaut mit Shopware 6.7.15) – kein `bin/build-administration.sh` nötig. Ein Neubau ist nur erforderlich, wenn die Quelltexte unter `src/Resources/app/administration` geändert werden. Alternativ kann das Plugin als ZIP über *Erweiterungen → Meine Erweiterungen → Erweiterung hochladen* installiert werden (`git archive --format=zip --prefix=MonthlyDeliveryPlugin/ -o MonthlyDeliveryPlugin.zip HEAD`).

Danach:

1. **Einstellungen → Erweiterungen → Monats-Lieferprofile**: Profile je Lieferant anlegen (Profil `standard` ist vorhanden).
2. Optional am **Hersteller** im Zusatzfeld „Lieferprofil für alle Produkte dieses Herstellers/Lieferanten“ das Profil wählen.
3. Am **Produkt** das Zusatzfeld „Fixer monatlicher Versandtermin aktiv“ einschalten und optional ein Profil wählen.
4. Bei Bedarf die E-Mail-Vorlagen erweitern (siehe Abschnitt 5).

Deinstallation ohne „Daten behalten“ entfernt Zusatzfelder und Profiltabelle.

## 7. Konfiguration

**Einstellungen → Erweiterungen → Meine Erweiterungen → Dynamische Monats-Lieferzeiten → Konfigurieren** (pro Verkaufskanal möglich):

| Option | Standard | Beschreibung |
|---|---|---|
| Standard-Lieferprofil | leer | Profil, das gilt, wenn weder Produkt noch Hersteller eines haben |
| Zeitzone für den Bestellschluss | Europe/Berlin | Grundlage für „heute“ und 23:59:59 Uhr |
| HTTP-Cache-Lebensdauer begrenzen | an | siehe Abschnitt 8 |
| Bestellschluss anzeigen | an | zweite Zeile „(Bestellschluss …)“ |
| Name des Lieferprofils anzeigen | aus | z. B. „Lieferant: Müller GmbH“ |
| Versandtermin in Produkt-Listings anzeigen | aus | Anzeige in der Produktkachel |
| Zusammenfassung auf Bestätigungs- und Abschlussseite | an | Hinweisbox im Checkout |

## 8. HTTP-Cache

Produktseiten werden von Shopware gecacht. Damit nach dem Bestellschluss nicht weiter der alte Termin ausgeliefert wird, setzt das Plugin auf Seiten mit fixem Versandtermin die Cache-Lebensdauer (`s-maxage`) höchstens **bis zum frühesten Bestellschluss** auf der Seite. Bei einem externen Reverse-Proxy (Varnish, Fastly) muss dieser `s-maxage` respektieren. Das ist bei der Shopware-Standardkonfiguration der Fall.

Wenn Sie ein Lieferprofil ändern, leeren Sie anschließend den Cache (**Einstellungen → Caches & Indizes**), damit gecachte Seiten sofort den neuen Termin zeigen.

## 9. Entwicklung und Tests

Die Berechnungslogik wird ohne Shopware-Installation getestet (PHPUnit 11):

```bash
cd tests
composer install
vendor/bin/phpunit -c ../phpunit.xml.dist
```

Ein Test sichert die Schlüssel ab, die die App aus `monthlyDelivery` liest (`testPayloadContainsAllKeysUsedByTheApp`).

Innerhalb einer Shopware-Installation nutzt `tests/bootstrap.php` automatisch den Autoloader des Shops.

## 10. Code-Struktur

```
composer.json
src/
├── MonthlyDeliveryPlugin.php                     Plugin-Klasse (Install/Update/Uninstall)
├── Checkout/Cart/MonthlyDeliveryCartProcessor.php  Termin → Line-Item-Payload (→ Bestellung)
├── Core/Content/DeliveryProfile/                 Entity „dmd_delivery_profile“ (Lieferprofile)
├── Core/Content/Product/ProductMonthlyDeliveryExtension.php  Store-API-Feld „monthlyDelivery“ (Laufzeitfeld)
├── Migration/                                    Tabelle + Standardprofil
├── Service/
│   ├── CustomFieldInstaller.php                  Zusatzfelder an Produkt und Hersteller
│   ├── DeliveryCalculatorService.php             reine Berechnung L / D / V
│   ├── DeliveryProfileResolver.php               Aktivierung + Profil-Ermittlung
│   └── MonthlyDeliveryService.php                Fassade für Storefront und Warenkorb
├── Struct/                                       DeliveryRule, DeliverySchedule
├── Subscriber/
│   ├── ProductPageSubscriber.php                 Termin an Produkte/Produktseite hängen
│   └── HttpCacheSubscriber.php                   Cache-Lebensdauer bis zum Bestellschluss
└── Resources/
    ├── app/administration/                       Admin-Modul „Monats-Lieferprofile“
    ├── config/                                   services.xml, config.xml
    ├── snippet/                                  Storefront-Textbausteine (de-DE, en-GB)
    └── views/storefront/                         Twig-Overlays
tests/Unit/DeliveryCalculatorServiceTest.php
```

## 11. Gestaltung (CSS-Klassen)

Das Plugin bringt kein eigenes CSS mit und nutzt Bootstrap-Hilfsklassen. Für die Gestaltung im Theme (z. B. Nova Theme) sind diese Klassen stabil:

| Klasse | Element |
|---|---|
| `.monthly-delivery-info` | Container der Terminanzeige |
| `.monthly-delivery-info--detail` / `--line-item` / `--listing` | Variante: Produktseite, Warenkorb-/Bestellposition, Produktkachel |
| `.monthly-delivery-ship-date` | Zeile „Nächster fixer Versandtermin …“ (zusätzlich `.delivery-information`) |
| `.monthly-delivery-deadline` | Zeile „Bestellschluss …“ |
| `.monthly-delivery-profile` | Name des Lieferprofils (optional) |
| `.line-item-monthly-delivery` | Wrapper an der Warenkorb-/Bestellposition |
| `.monthly-delivery-summary` | Hinweisbox auf Bestätigungs- und Abschlussseite |

Auf der Produktseite steckt die Anzeige zusätzlich in Shopwares `.product-delivery-information`.

## Häufige Probleme und Lösungen

Hier sammeln wir Hürden, die bei Installation und Betrieb tatsächlich aufgetreten sind. **Neue Probleme bitte ergänzen** (oder als Issue melden), damit andere nicht lange suchen müssen.

### Plugin lässt sich nicht installieren („requires shopware/core ~6.6“)

**Ursache:** Version 1.x ist nur für Shopware 6.6

**Lösung:** Für Shopware 6.7 Version **2.0.0** oder neuer verwenden

### Kein Versandtermin beim Produkt

**Ursache:** Plugin greift nur, wenn das Zusatzfeld `custom_supplier_monthly_delivery` am Produkt (bzw. Hauptprodukt) **aktiv** ist

**Lösung:** Zusatzfeld im Produkt setzen (oder per WaWi). Profil über Produkt-, Hersteller- oder Standardprofil prüfen

### Abverkaufs-/Vorbestell-/inaktive Artikel zeigen die normale Lieferzeit

**Ursache:** Gewollt: Für diese Fälle bleibt die Standardanzeige auf der Produktseite

**Lösung:** –

### Admin-Menü „Monats-Lieferprofile“ fehlt

**Ursache:** Assets nicht installiert oder Browser-Cache

**Lösung:** `bin/console assets:install`, `bin/console cache:clear`, Administration neu laden. Die Admin-Dateien sind fertig gebaut enthalten

### Termin nach Profiländerung veraltet

**Ursache:** Seiten liegen im HTTP-Cache

**Lösung:** `bin/console cache:clear`

### App zeigt keinen Termin

**Ursache:** Store-API liefert `extensions.monthlyDelivery` erst ab Version 2.0.0

**Lösung:** Plugin auf 2.0.0 aktualisieren; in der App muss `monthlyDelivery.enabled` gesetzt sein

### Datum in der falschen Zeitzone

**Ursache:** Zeitzone in der Plugin-Konfiguration

**Lösung:** Konfiguration → Zeitzone (Standard `Europe/Berlin`)

### WaWi überschreibt die Lieferzeit

**Ursache:** Das Plugin liest/schreibt `deliveryTime`/`restockTime` absichtlich nicht

**Lösung:** Gewollt – die Anzeige ersetzt nur die Darstellung in der Storefront

## Entstehung

Dieses Plugin ist mit Unterstützung von KI entstanden (Claude von Anthropic). Die Anforderungen kommen aus dem täglichen Betrieb mehrerer Shopware-Shops; der Code wurde mit KI geschrieben und überarbeitet. Geprüft wird er mit automatischen Tests und statischer Analyse (soweit im Repository vorhanden), mit regelmäßigen Sicherheitsprüfungen des Codes und im Echtbetrieb in den eigenen Shops. Trotzdem gilt: Nutzung auf eigene Verantwortung und ohne Garantie (siehe Lizenz). Fehler und Verbesserungsvorschläge bitte als Issue melden. Mehr zum Hintergrund: [SW6-Nova-Plugins](https://github.com/DrdotHouse2106/SW6-Nova-Plugins).

## Lizenz

MIT – siehe [LICENSE](LICENSE).

## ☕ Unterstützen

Wenn dir dieses Projekt Arbeit erspart, freue ich mich über einen Kaffee – freiwillig, ohne
Support-Anspruch, einfach eine nette Geste:

[![Spendier mir einen Kaffee](https://img.shields.io/badge/☕_Spendier_mir_einen_Kaffee-PayPal-0070ba?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/DrdotHouse)

### Für Unternehmen: Unterstützung für die Weiterentwicklung

Ihr setzt das Projekt im Betrieb ein und möchtet die Weiterentwicklung unterstützen? Gern auch
**mit Rechnung** – einmalig oder **monatlich**. Auf Wunsch nenne ich euch hier unter
„Unterstützer“ mit Firmenname und Link.

Bitte beachtet: Ich bin kein Profi-Entwickler, das Projekt ist mein Hobby. Eine Unterstützung
ist deshalb kein Supportvertrag – Support, Reaktionszeiten oder Gewährleistung kann ich nicht
anbieten. Wünsche und Verbesserungsvorschläge nehme ich aber gerne mit auf.

Kontakt: [info@francetec.de](mailto:info@francetec.de)

#### Unterstützer

*Noch keine – über euren Namen hier würde ich mich freuen.*
