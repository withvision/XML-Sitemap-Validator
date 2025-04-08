# 🧭  XML Sitemap Validator
Ein einfacher PHP-basierter Validator zur Überprüfung von XML-Sitemaps.

![XML Sitemap Validator Screenshot](screenshot.png)

## Funktionen

- **Umfassende Validierung**: Prüft XML-Sitemaps auf Konformität mit den Google-Spezifikationen
- **Detaillierte Analyse**: Überprüft HTTP-Status, XML-Struktur, UTF-8-Kodierung, Größe, und mehr
- **URL-Stichproben**: Überprüft zufällig ausgewählte URLs aus der Sitemap auf Erreichbarkeit und Indexierbarkeit
- **Unterstützung für Sitemap-Erweiterungen**: Erkennt und analysiert Image-, Video-, News-, Mobile- und hreflang-Erweiterungen
- **Bewertungssystem**: Bewertet die Qualität der Sitemap mit einem Punktesystem (A+ bis F)
- **Empfehlungssystem**: Gibt konkrete Vorschläge zur Verbesserung der Sitemap
- **Sitemap-Generator**: Erstellt Sitemap-Vorlagen für verschiedene Anwendungsfälle
- **Rate-Limiting**: Schutz vor Überlastung durch Begrenzung der Anfragen pro IP-Adresse

## Anforderungen

- PHP 7.4 oder höher
- MySQL/MariaDB Datenbank
- PHP-Erweiterungen: PDO, cURL, SimpleXML, JSON, mbstring
- Webserver mit .htaccess-Unterstützung (Apache empfohlen)

## Installation

1. Laden Sie die Dateien auf Ihren Webserver hoch
2. Rufen Sie `setup.php` über Ihren Webbrowser auf
3. Folgen Sie den Anweisungen im Setup-Assistenten:
   - Systemvoraussetzungen werden geprüft
   - Datenbankverbindung einrichten
   - Konfigurationsoptionen festlegen
   - Sicherheitseinstellungen anpassen
4. Nach Abschluss des Setups können Sie den Validator unter der Hauptseite aufrufen
5. Aus Sicherheitsgründen sollten Sie die setup.php nach erfolgreicher Installation löschen

## Verwendung

1. Geben Sie die URL einer XML-Sitemap ein (unterstützt werden .xml, .xml.gz und dynamisch generierte Sitemaps)
2. Das Tool analysiert die Sitemap und zeigt detaillierte Ergebnisse an
3. Folgen Sie den Empfehlungen zur Optimierung Ihrer Sitemap
4. Nutzen Sie den Sitemap-Generator, um neue optimierte Sitemaps zu erstellen

## Konfiguration

Die Hauptkonfiguration erfolgt über die `config.php` Datei:

- **Datenbank-Einstellungen**: Verbindungsparameter für MySQL
- **Validator-Einstellungen**: Cache-Optionen, User-Agent, HTTP-Timeout, etc.
- **Sicherheitseinstellungen**: Hostnamen-Einschränkungen, maximale Dateigröße, etc.

## Sicherheitshinweise

- Die Anwendung erstellt automatisch .htaccess-Dateien zum Schutz sensibler Daten
- Stellen Sie sicher, dass Ihr Webserver .htaccess-Einstellungen berücksichtigt
- Die config.php sollte außerhalb des Webroot-Verzeichnisses platziert werden (wenn möglich)
- Achten Sie darauf, dass die Datenbankbenutzer über minimale Berechtigungen verfügen

## Technische Details

- **Caching-System**: Zwischenspeicherung von heruntergeladenen Sitemaps für Debugging-Zwecke
- **Datenbank-Schema**: Speicherung von Validierungsergebnissen und Rate-Limit-Informationen
- **HTTP-Komprimierung**: Erkennung und Verarbeitung komprimierter Sitemaps (gzip, deflate)
- **Robots.txt-Integration**: Überprüfung auf korrekte Einbindung in die robots.txt-Datei

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz - siehe die [LICENSE](LICENSE) Datei für Details.

## Beitragen

Beiträge sind willkommen! Bitte erstellen Sie einen Pull-Request oder eröffnen Sie ein Issue für Fehlerberichte oder Funktionswünsche.

## Autoren

- [Simon Pokorny](https://www.simon-pokorny.com)
- [DLx-Media.com](https://dlx-media.com)

## Danksagungen

- Danke an alle, die zum Projekt beigetragen haben

## 📂 Projektstruktur

```
├── config.php                     # Datenbankverbindung
├── index.php                      # Einstiegspunkt / Formular
├── setup.php                      # Erstkonfiguration / Datenbank-Tabelle
├── form_template.php              # HTML-Formular
├── results_template.php           # Ausgabe der Prüf-Ergebnisse
├── rate_limit_template.php        # Rate-Limit-Meldung
```

## 📌 Hinweise

- Das Tool prüft auf HTTP-Statuscodes und auf diverse SEO-Kriterien.
