# 🧭  XML Sitemap Validator
Ein einfacher PHP-basierter Validator zur Überprüfung von XML-Sitemaps. Das Tool prüft online, ob die sitemap.xml oder sitemap.xml.gz erreichbar und valide ist und gibt den Status sowie Attribute dazu aus. Es wird ein Webserver mit PHP, MySQL, cURL und gzip-Unterstützung benötigt.

## 🚀 Features

- Formularbasierte Eingabe einer Sitemap-URL
- Automatischer Abruf und Parsing der Sitemap (auch `.gz` wird unterstützt)
- HTTP-Statuscode-Prüfung
- Analyse der XML Sitemap mit Bewertung
- Übersichtliche Darstellung der Ergebnisse
- Einfache Rate-Limitierung
- setup.php zur einfachen installation

## 🔧 Systemanforderungen

- PHP 8 oder höher
- MySQL/MariaDB-Datenbank
- Webserver mit Unterstützung für PHP (Apache, Nginx, etc.)
- cURL-Erweiterung für PHP

## 🛠️ Installation

1. **Repository klonen oder Dateien hochladen**

```bash
git clone https://github.com/withvision/XML-Sitemap-Validator.git
cd XML-Sitemap-Validator
```

2. **Installation**

Führe das `setup.php` Script im Browser aus, und folge der installation um die benötigte Tabelle anzulegen und die grundlegenden Einstellungen vorzunehmen:

```Webbrowser:
https://deinedomain.tld/setup.php
```

3. **Konfiguration anpassen**

Die einstellungen können in der `config.php` Datei angepasst werden:

```php
        'host' => getenv('DB_HOST') ?: 'localhost',
        'username' => getenv('DB_USER') ?: 'usernamehere',
        'password' => getenv('DB_PASS') ?: 'passwordhere',
        'database' => getenv('DB_NAME') ?: 'databasenamehere'
```

4. **Nutzung**

Nach der Installation einfach die `index.php` in einem Browser deiner Wahl aufrufen. Schon kannst du deine erste Sitemap prüfen!

## 📂 Projektstruktur

```
├── config.php              # Datenbankverbindung
├── index.php              # Einstiegspunkt / Formular
├── setup.php              # Erstkonfiguration / Datenbank-Tabelle
├── form_template.php      # HTML-Formular
├── results_template.php   # Ausgabe der Prüf-Ergebnisse
├── rate_limit_template.php# Rate-Limit-Meldung
```

## 📌 Hinweise

- Das Tool prüft auf HTTP-Statuscodes und auf diverse SEO-Kriterien.
