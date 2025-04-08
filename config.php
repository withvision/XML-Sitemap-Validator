<?php
/**
 * XML Sitemap Validator
 *
 * Ein Tool zur Überprüfung von sitemap.xml Dateien nach gängigen SEO Standards
 * PHP 7.4+ & MySQL/MariaDB [PDO, cURL, SimpleXML, JSON, mbstring]
 *
 * @author   Simon Pokorny <coding@dlx-media.com>
 * @github	 https://github.com/withvision/XML-Sitemap-Validator
 * @web		 https://www.simon-pokorny.com
 */

return [
    // Datenbankverbindung
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'username' => getenv('DB_USER') ?: 'usernamehere',
        'password' => getenv('DB_PASS') ?: 'passwordhere',
        'database' => getenv('DB_NAME') ?: 'databasenamehere'
    ],
    
    // Validator-Einstellungen
    'validator' => [
        // Cache für Sitemaps (für Debugging) aktivieren/deaktivieren
        'enable_cache' => true,
        
        // Benutzerdefinierter User-Agent für alle HTTP-Anfragen
        'user_agent' => 'XML Sitemap Validator (https://github.com/withvision/XML-Sitemap-Validator)',
        
        // Anzahl der URL-Stichproben (zufällige URLs aus der Sitemap)
        'sample_urls_count' => 5,
        
        // Timeout für HTTP-Anfragen in Sekunden
        'http_timeout' => 10,
        
        // Ratenbegrenzung (Anfragen pro Stunde pro IP)
        'rate_limit' => 20
    ],
    
    // Sicherheitseinstellungen
    'security' => [
        // Liste erlaubter Hostnamen (leer lassen für keine Einschränkung)
        'allowed_hosts' => [],
        
        // Maximale Größe der Sitemap-Datei in Bytes (150 MB)
        'max_filesize' => 157286400,
        
        // Auf SSL-Zertifikate prüfen
        'verify_ssl' => true
    ]
];