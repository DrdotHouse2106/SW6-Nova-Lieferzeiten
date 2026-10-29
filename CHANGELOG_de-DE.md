# 2.0.3
- Lieferprofile werden jetzt auch beim Speichern über die Admin-API (WaWi, Skripte) geprüft – Bestellschluss-Uhrzeit (HH:MM oder HH:MM:SS), ausgeschlossene Monate (nur 1–12, nicht alle zwölf) und Vorkommen (kein 0). Ungültige Werte werden mit einer Fehlermeldung abgelehnt statt gespeichert
- Ist ein bereits gespeichertes Profil ungültig, wird das nur noch einmal je PHP-Prozess geloggt statt bei jedem Produktladen

# 2.0.2
- Neues Plugin-Icon im Nova-Stil (Erweiterungen → Meine Erweiterungen)
- Repository umbenannt in SW6-Nova-Lieferzeiten – alte Adresse leitet weiter

# 2.0.1
- Neuer Anzeigename „Nova Lieferzeiten“ (technischer Name MonthlyDeliveryPlugin bleibt – keine Neuinstallation nötig)

# 2.0.0
- Shopware 6.7: Mindestversion jetzt 6.7 (Composer `~6.7.0`); alle Template-Overrides und Blöcke gegen Shopware 6.7.15 geprüft
- Store-API: `monthlyDelivery` als Laufzeitfeld mit `ApiAware` an der Produkt-Definition registriert – der Termin steht jetzt in allen Store-API-Antworten mit Produkten (z. B. für die App) unter `extensions.monthlyDelivery`; vorher wurde die Extension von Shopware herausgefiltert
- Produktseite: Microdata `availability` nur noch ohne JSON-LD (Feature `JSON_LD_DATA`), wie im Shopware-Original; digitale Produkte behalten die Standardanzeige
- Technik: PHPStan Level 8 fehlerfrei, Test für die von der App genutzten Payload-Schlüssel

# 1.0.1
- Admin: ACL-Berechtigungen für Lieferprofile (Anzeigen/Bearbeiten/Anlegen/Löschen) unter Einstellungen → Rollen; das Modul ist damit auch für Rollen ohne Vollzugriff gezielt freigebbar

# 1.0.0
- Erste Version: fixe monatliche Versandtermine und Bestellschluss je Lieferant (Lieferprofile)
- Anzeige auf Produktdetailseite, im Warenkorb, Off-Canvas, Checkout, auf der Abschlussseite und in der Bestellhistorie
- Speicherung des zugesagten Termins in der Bestellposition (für E-Mails und Kundenkonto)
- Aktivierung ausschließlich per Zusatzfeld, keine Änderung der WaWi-Felder Lieferzeit/Wiederauffüllzeit
- HTTP-Cache-Lebensdauer bis zum nächsten Bestellschluss begrenzt
