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

// Fehlermeldungen deaktivieren im Produktivbetrieb (nur für Entwicklung aktivieren)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sicher einstellen
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self' https://cdnjs.cloudflare.com; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; script-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com");

// Konfiguration laden (außerhalb des Webroot-Verzeichnisses)
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    $config = require_once $configPath;
} else {
    die("Konfigurationsdatei config.php nicht gefunden!");
}

// Cache-Verzeichnis für Sitemaps einrichten, falls aktiviert
$cache_dir = null;
if ($config['validator']['enable_cache']) {
    $cache_dir = __DIR__ . '/cache';
    if (!file_exists($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
}

/**
 * Datenbank-Verbindung herstellen
 * 
 * @param array $config Datenbank-Konfiguration
 * @return PDO Datenbank-Verbindung
 * @throws Exception Bei Verbindungsfehler
 */
function connectDB($config) {
    try {
        $db = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $db;
    } catch (PDOException $e) {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
    }
}

/**
 * Tabellen erstellen, falls nicht vorhanden
 * 
 * @param PDO $db Datenbank-Verbindung
 */
function createTablesIfNotExist($db) {
    // Sitemap-Validierungen Tabelle
    $sql = "CREATE TABLE IF NOT EXISTS sitemap_validations (
        id VARCHAR(32) PRIMARY KEY,
        url VARCHAR(255) NOT NULL,
        date_checked DATETIME NOT NULL,
        http_status INT,
        valid_xml BOOLEAN,
        encoding_utf8 BOOLEAN,
        valid_root_element BOOLEAN,
        url_count INT,
        unique_url_count INT,
        has_lastmod BOOLEAN,
        has_changefreq BOOLEAN,
        has_priority BOOLEAN,
        has_invalid_lastmod BOOLEAN,
        has_invalid_changefreq BOOLEAN,
        has_invalid_priority BOOLEAN,
        valid_mime_type BOOLEAN,
        content_type VARCHAR(100),
        http_compressed BOOLEAN,
        filesize BIGINT,
        is_sitemap_index BOOLEAN,
        is_compressed BOOLEAN,
        load_time FLOAT,
        has_image_extension BOOLEAN,
        has_video_extension BOOLEAN,
        has_news_extension BOOLEAN,
        has_mobile_extension BOOLEAN,
        has_alternate_links BOOLEAN,
        image_extension_count INT DEFAULT 0,
        video_extension_count INT DEFAULT 0,
        news_extension_count INT DEFAULT 0,
        mobile_extension_count INT DEFAULT 0,
        alternate_links_count INT DEFAULT 0,
        sitemap_score FLOAT DEFAULT 0,
        sitemap_grade JSON,
        robots_txt_reference BOOLEAN,
        robots_txt_accessible BOOLEAN,
        url_sample_status JSON,
        validation_results LONGTEXT,
        errors LONGTEXT
    )";
    
    // Rate-Limiting Tabelle
    $sql2 = "CREATE TABLE IF NOT EXISTS rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        request_time DATETIME NOT NULL,
        INDEX (ip, request_time)
    )";
    
    try {
        $db->exec($sql);
        $db->exec($sql2);
    } catch (PDOException $e) {
        die("Fehler beim Erstellen der Tabellen: " . $e->getMessage());
    }
}

/**
 * Zufällige ID generieren
 * 
 * @return string Zufällige ID
 */
function generateRandomID() {
    return bin2hex(random_bytes(16));
}

/**
 * Prüfen, ob die Rate-Begrenzung für die IP überschritten wurde
 * 
 * @param string $ip IP-Adresse
 * @param PDO $db Datenbank-Verbindung
 * @param int $limit Maximal erlaubte Anfragen pro Stunde
 * @return bool True wenn Rate-Limit ok, False wenn überschritten
 */
function checkRateLimit($ip, $db, $limit) {
    try {
        // Alte Einträge löschen (älter als 1 Stunde)
        $cleanupSql = "DELETE FROM rate_limits WHERE request_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $db->exec($cleanupSql);
        
        // Anzahl der Anfragen in der letzten Stunde prüfen
        $sql = "SELECT COUNT(*) AS count FROM rate_limits WHERE ip = :ip AND request_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $db->prepare($sql);
        $stmt->execute([':ip' => $ip]);
        $result = $stmt->fetch();
        
        if ($result['count'] >= $limit) {
            return false; // Rate-Limit überschritten
        }
        
        // Neue Anfrage eintragen
        $sql = "INSERT INTO rate_limits (ip, request_time) VALUES (:ip, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([':ip' => $ip]);
        
        return true;
    } catch (PDOException $e) {
        // Im Fehlerfall erlauben (kein Block)
        error_log("Fehler beim Rate-Limiting: " . $e->getMessage());
        return true;
    }
}

/**
 * Validiert eine eingegebene Sitemap-URL
 * 
 * @param string $url Die zu validierende URL
 * @param array $config Konfiguration
 * @return bool|string True wenn gültig, Fehlermeldung wenn ungültig
 */
function validateSitemapUrl($url, $config) {
    // Grundlegende URL-Validierung
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "Die angegebene URL ist ungültig.";
    }
    
    // Host-Whitelist prüfen, falls konfiguriert
    if (!empty($config['security']['allowed_hosts'])) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, $config['security']['allowed_hosts'])) {
            return "Dieser Host ist nicht erlaubt.";
        }
    }
    
    return true;
}

/**
 * Prüfen, ob eine URL in der robots.txt blockiert ist
 * 
 * @param string $url URL
 * @param string $robotsTxt Inhalt der robots.txt
 * @return bool True wenn blockiert, False wenn erlaubt
 */
function isBlockedByRobotsTxt($url, $robotsTxt) {
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '/';
    
    // User-Agent extrahieren (wir suchen nach * oder Googlebot)
    preg_match_all('/User-agent:\s*([^\r\n]+)[\r\n]+([^U].*?)(?=User-agent:|$)/is', $robotsTxt, $matches, PREG_SET_ORDER);
    
    $isBlocked = false;
    if ($matches) {
        foreach ($matches as $match) {
            $userAgent = trim($match[1]);
            $rules = $match[2] ?? '';
            
            // Nur für Wildcard (*) oder Googlebot prüfen
            if ($userAgent === '*' || strtolower($userAgent) === 'googlebot') {
                // Disallow-Regeln extrahieren
                preg_match_all('/Disallow:\s*([^\r\n]+)/i', $rules, $disallowMatches);
                if (isset($disallowMatches[1])) {
                    foreach ($disallowMatches[1] as $disallowPath) {
                        $disallowPath = trim($disallowPath);
                        
                        // Prüfen, ob die URL vom Disallow betroffen ist
                        if (!empty($disallowPath) && $disallowPath !== '/' && strpos($path, $disallowPath) === 0) {
                            $isBlocked = true;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    return $isBlocked;
}

/**
 * Prüfen, ob die Sitemap in der robots.txt referenziert ist
 * 
 * @param string $sitemapUrl URL der Sitemap
 * @param string $robotsTxt Inhalt der robots.txt
 * @param array $config Konfiguration
 * @return bool True wenn referenziert, False wenn nicht
 */
function isSitemapReferencedInRobotsTxt($sitemapUrl, $robotsTxt, $config) {
    // Sitemap-Einträge extrahieren
    preg_match_all('/Sitemap:\s*([^\r\n]+)/i', $robotsTxt, $matches);
    
    // Direkte Übereinstimmung prüfen
    if (isset($matches[1])) {
        foreach ($matches[1] as $referencedSitemap) {
            $referencedSitemap = trim($referencedSitemap);
            if ($referencedSitemap === $sitemapUrl) {
                return true;
            }
        }
    }
    
    // Für Sitemap-Dateien, die Teil eines Sitemap-Index sind:
    // Extrahiere Domain und prüfe, ob eine Sitemap-Index-Datei für diese Domain in robots.txt steht
    $parsedUrl = parse_url($sitemapUrl);
    $domain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    
    if (isset($matches[1])) {
        foreach ($matches[1] as $referencedSitemap) {
            $referencedSitemap = trim($referencedSitemap);
            // Prüfe, ob es sich um eine Sitemap-Index-Datei handelt
            if (strpos($referencedSitemap, $domain) === 0) {
                // Versuche, die Index-Datei zu laden und zu prüfen, ob die aktuelle Sitemap enthalten ist
                try {
                    $ch = curl_init($referencedSitemap);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, $config['validator']['user_agent']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, $config['validator']['http_timeout']);
                    
                    // SSL-Überprüfung entsprechend der Konfiguration
                    if ($config['security']['verify_ssl']) {
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    } else {
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    }
                    
                    $indexContent = curl_exec($ch);
                    curl_close($ch);
                    
                    if ($indexContent) {
                        // XXE-Schutz aktivieren
                        libxml_use_internal_errors(true);
                        
                        // XML-Parser mit sicheren Optionen erstellen
                        $parser = xml_parser_create();
                        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
                        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
                        
                        $xml = simplexml_load_string($indexContent);
                        if ($xml && $xml->getName() === 'sitemapindex') {
                            foreach ($xml->sitemap as $sitemap) {
                                if (isset($sitemap->loc) && (string)$sitemap->loc === $sitemapUrl) {
                                    return true;
                                }
                            }
                        }
                        
                        libxml_clear_errors();
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler beim Laden der Index-Datei
                    error_log("Fehler beim Prüfen der Sitemap-Index: " . $e->getMessage());
                }
            }
        }
    }
    
    return false;
}

/**
 * Robots.txt abrufen
 * 
 * @param string $url URL der Webseite
 * @param array $config Konfiguration
 * @return string|false Inhalt der robots.txt oder false wenn nicht gefunden
 */
function getRobotsTxt($url, $config) {
    $parsed = parse_url($url);
    $robotsUrl = $parsed['scheme'] . '://' . $parsed['host'] . '/robots.txt';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $robotsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['validator']['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['validator']['http_timeout']);
    
    // SSL-Überprüfung entsprechend der Konfiguration
    if ($config['security']['verify_ssl']) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return $response;
    }
    
    return false;
}

/**
 * Prüft, ob eine Webseite noindex-Direktiven enthält (Meta-Tags oder HTTP-Header)
 * 
 * @param string $url URL der Webseite
 * @param array $config Konfiguration
 * @return array Ergebnisse der Prüfung
 */
function checkNoIndexStatus($url, $config) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['validator']['http_timeout']);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['validator']['user_agent']);
    
    // SSL-Überprüfung entsprechend der Konfiguration
    if ($config['security']['verify_ssl']) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Header und Body trennen
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    $result = [
        'http_status' => $httpCode,
        'has_noindex_meta' => false,
        'has_noindex_header' => false
    ];
    
    // Prüfen auf X-Robots-Tag in HTTP-Header
    if (preg_match('/X-Robots-Tag:.*noindex/i', $header)) {
        $result['has_noindex_header'] = true;
    }
    
    // Prüfen auf robots meta tag im HTML
    if (preg_match('/<meta\s+name=["\']robots["\']\s+content=["\'][^"\']*noindex[^"\']*["\']/i', $body) ||
        preg_match('/<meta\s+content=["\'][^"\']*noindex[^"\']*["\']\s+name=["\']robots["\']/i', $body) ||
        preg_match('/<meta\s+name=["\']googlebot["\']\s+content=["\'][^"\']*noindex[^"\']*["\']/i', $body) ||
        preg_match('/<meta\s+content=["\'][^"\']*noindex[^"\']*["\']\s+name=["\']googlebot["\']/i', $body)) {
        $result['has_noindex_meta'] = true;
    }
    
    return $result;
}

/**
 * Prüft parallel mehrere URLs auf ihren Status
 * 
 * @param array $urlSample Array mit URLs
 * @param string $robotsTxt Inhalt der robots.txt
 * @param array $config Konfiguration
 * @return array Array mit Statusinfos für jede URL
 */
function checkUrlsSampleAsync($urlSample, $robotsTxt, $config) {
    $mh = curl_multi_init();
    $handles = [];
    $urlStatusSample = [];
    
    // Curl-Handles für alle URLs erstellen
    foreach ($urlSample as $index => $url) {
        $handles[$index] = curl_init($url);
        curl_setopt($handles[$index], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handles[$index], CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handles[$index], CURLOPT_HEADER, true);
        curl_setopt($handles[$index], CURLOPT_NOBODY, false);
        curl_setopt($handles[$index], CURLOPT_TIMEOUT, $config['validator']['http_timeout']);
        curl_setopt($handles[$index], CURLOPT_USERAGENT, $config['validator']['user_agent']);
        
        // SSL-Überprüfung entsprechend der Konfiguration
        if ($config['security']['verify_ssl']) {
            curl_setopt($handles[$index], CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($handles[$index], CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($handles[$index], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handles[$index], CURLOPT_SSL_VERIFYHOST, 0);
        }
        
        curl_multi_add_handle($mh, $handles[$index]);
        
        // Statusobjekt mit URL initialisieren
        $urlStatusSample[$index] = [
            'url' => $url,
            'http_status' => null,
            'blocked_by_robots' => isBlockedByRobotsTxt($url, $robotsTxt),
            'has_noindex_meta' => false,
            'has_noindex_header' => false
        ];
    }
    
    // Anfragen parallel ausführen
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh); // Kleine Pause um CPU-Last zu reduzieren
    } while ($running);
    
    // Ergebnisse verarbeiten
    foreach ($handles as $index => $handle) {
        $response = curl_multi_getcontent($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Ergebnisse speichern
        $urlStatusSample[$index]['http_status'] = $httpCode;
        
        // Prüfen auf noindex-Direktiven
        if (preg_match('/X-Robots-Tag:.*noindex/i', $header)) {
            $urlStatusSample[$index]['has_noindex_header'] = true;
        }
        
        if (preg_match('/<meta\s+name=["\']robots["\']\s+content=["\'][^"\']*noindex[^"\']*["\']/i', $body) ||
            preg_match('/<meta\s+content=["\'][^"\']*noindex[^"\']*["\']\s+name=["\']robots["\']/i', $body) ||
            preg_match('/<meta\s+name=["\']googlebot["\']\s+content=["\'][^"\']*noindex[^"\']*["\']/i', $body) ||
            preg_match('/<meta\s+content=["\'][^"\']*noindex[^"\']*["\']\s+name=["\']googlebot["\']/i', $body)) {
            $urlStatusSample[$index]['has_noindex_meta'] = true;
        }
        
        // Handle freigeben
        curl_multi_remove_handle($mh, $handle);
        curl_close($handle);
    }
    
    // Multi-Handle schließen
    curl_multi_close($mh);
    
    // Ergebnisse zurückgeben (reindexieren)
    return array_values($urlStatusSample);
}

/**
 * Berechnet einen Qualitäts-Score für die Sitemap
 * 
 * @param array $results Ergebnisse der Sitemap-Validierung
 * @return array Score und Bewertung
 */
function calculateSitemapScore($results) {
    $score = 0;
    $maxScore = 0;
    
    // Grundlegende Anforderungen (Pflicht) - Stärkere Gewichtung
    if ($results['http_status'] == 200) $score += 12; // Erhöht von 10
    $maxScore += 12;
    
    if ($results['valid_xml']) $score += 18; // Erhöht von 15
    $maxScore += 18;
    
    if ($results['encoding_utf8']) $score += 6; // Erhöht von 5
    $maxScore += 6;
    
    if ($results['valid_root_element']) $score += 12; // Erhöht von 10
    $maxScore += 12;
    
    // Neu: MIME-Type Überprüfung
    if (isset($results['valid_mime_type'])) {
        if ($results['valid_mime_type']) $score += 8;
        $maxScore += 8;
    }
    
    // Größe und Performance
    if ($results['filesize'] <= 50 * 1024 * 1024) $score += 5;
    $maxScore += 5;
    
    if ($results['url_count'] <= 50000) $score += 5;
    $maxScore += 5;
    
    if ($results['unique_url_count'] == $results['url_count']) $score += 5;
    $maxScore += 5;
    
    if ($results['load_time'] < 1) $score += 5;
    $maxScore += 5;
    
    // Komprimierung (differenzierter)
    if ($results['is_compressed']) $score += 4; // Als .xml.gz gespeichert
    elseif (isset($results['http_compressed']) && $results['http_compressed']) $score += 2; // HTTP-Komprimierung
    $maxScore += 4;
    
    // SEO Best Practices
    if ($results['robots_txt_reference']) $score += 7;
    $maxScore += 7;
    
    if ($results['has_lastmod']) $score += 5;
    $maxScore += 5;
    
    // Neu: lastmod-Fehler bestrafen
    if (isset($results['has_invalid_lastmod']) && $results['has_invalid_lastmod']) {
        $score -= 10; // Abzug für ungültige lastmod-Formate
    }
    
    if ($results['has_priority']) $score += 3;
    $maxScore += 3;
    
    // Neu: Priority-Fehler bestrafen
    if (isset($results['has_invalid_priority']) && $results['has_invalid_priority']) {
        $score -= 3; // Abzug für ungültige priority-Werte
    }
    
    if ($results['has_changefreq']) $score += 2;
    $maxScore += 2;
    
    // Neu: changefreq-Fehler bestrafen
    if (isset($results['has_invalid_changefreq']) && $results['has_invalid_changefreq']) {
        $score -= 2; // Abzug für ungültige changefreq-Werte
    }
    
    // Reiche Inhalte
    $hasAnyExtension = $results['has_image_extension'] || 
                      $results['has_video_extension'] || 
                      $results['has_news_extension'] || 
                      $results['has_mobile_extension'] || 
                      $results['has_alternate_links'];
    
    if ($hasAnyExtension) $score += 5;
    $maxScore += 5;
    
    // URL-Stichproben-Prüfungen - Punkte für erreichbare URLs in der Stichprobe
    $urlSampleStatus = is_string($results['url_sample_status']) ? 
                      json_decode($results['url_sample_status'], true) : 
                      $results['url_sample_status'];
                      
    if (!empty($urlSampleStatus) && is_array($urlSampleStatus)) {
        $validUrlCount = 0;
        foreach ($urlSampleStatus as $status) {
            if ($status['http_status'] == 200 && 
                !$status['blocked_by_robots'] && 
                !$status['has_noindex_meta'] && 
                !$status['has_noindex_header']) {
                $validUrlCount++;
            }
        }
        
        // Punkte basierend auf dem Prozentsatz gültiger URLs
        $samplePercent = ($validUrlCount / count($urlSampleStatus)) * 100;
        if ($samplePercent >= 90) $score += 10;
        elseif ($samplePercent >= 75) $score += 7;
        elseif ($samplePercent >= 50) $score += 5;
        elseif ($samplePercent > 0) $score += 2;
        $maxScore += 10;
    }
    
    // Endgültigen Prozentsatz berechnen
    $finalScore = ($score / $maxScore) * 100;
    // Begrenzung des Scores auf 0-100 (falls Abzüge unter 0 führen würden)
    $finalScore = max(0, min(100, $finalScore));
    
    return [
        'score' => round($finalScore, 1),
        'grade' => getScoreGrade($finalScore)
    ];
}

/**
 * Bestimmt die Note basierend auf dem Score-Prozentsatz
 * 
 * @param float $score Score in Prozent
 * @return array Note mit Farbe und Text
 */
function getScoreGrade($score) {
    if ($score >= 95) return ['grade' => 'A+', 'color' => 'success', 'text' => 'Hervorragend'];
    if ($score >= 90) return ['grade' => 'A', 'color' => 'success', 'text' => 'Sehr gut'];
    if ($score >= 85) return ['grade' => 'A-', 'color' => 'success', 'text' => 'Gut'];
    if ($score >= 80) return ['grade' => 'B+', 'color' => 'success', 'text' => 'Gut'];
    if ($score >= 75) return ['grade' => 'B', 'color' => 'primary', 'text' => 'Gut'];
    if ($score >= 70) return ['grade' => 'B-', 'color' => 'primary', 'text' => 'Befriedigend'];
    if ($score >= 65) return ['grade' => 'C+', 'color' => 'primary', 'text' => 'Befriedigend'];
    if ($score >= 60) return ['grade' => 'C', 'color' => 'warning', 'text' => 'Ausreichend'];
    if ($score >= 55) return ['grade' => 'C-', 'color' => 'warning', 'text' => 'Ausreichend'];
    if ($score >= 50) return ['grade' => 'D+', 'color' => 'warning', 'text' => 'Mangelhaft'];
    if ($score >= 45) return ['grade' => 'D', 'color' => 'danger', 'text' => 'Mangelhaft'];
    return ['grade' => 'F', 'color' => 'danger', 'text' => 'Ungenügend'];
}

/**
 * Kategorisiert Sitemap-Probleme in kritische Fehler, Warnungen und Informationen
 * 
 * @param array $results Ergebnisse der Sitemap-Validierung
 * @return array Kategorisierte Probleme
 */
function categorizeSitemapIssues($results) {
    $issues = [
        'critical' => [], // Kritische Fehler, die behoben werden müssen
        'warnings' => [], // Warnungen, die beachtet werden sollten
        'info' => [],     // Informationen, die nützlich sein könnten
    ];
    
    // Kritische Fehler prüfen
    if ($results['http_status'] !== 200) {
        $issues['critical'][] = "HTTP-Status ist nicht 200 OK, sondern: {$results['http_status']}";
    }
    
    if (!$results['valid_xml']) {
        $issues['critical'][] = "Die Sitemap enthält kein gültiges XML";
    }
    
    if (!$results['valid_root_element']) {
        $issues['critical'][] = "Ungültiges Root-Element oder Namespace";
    }
    
    // Neu: MIME-Type-Fehler als kritisch einstufen
    if (isset($results['valid_mime_type']) && !$results['valid_mime_type']) {
        $issues['critical'][] = "Die Sitemap wird mit falschem MIME-Type ausgeliefert: " . ($results['content_type'] ?? 'Unbekannt');
    }
    
    // Warnungen prüfen
    if (!$results['encoding_utf8']) {
        $issues['warnings'][] = "Die Sitemap verwendet keine UTF-8-Kodierung";
    }
    
    if ($results['url_count'] > 50000) {
        $issues['warnings'][] = "Die Sitemap enthält mehr als 50.000 URLs ({$results['url_count']})";
    }
    
    if ($results['unique_url_count'] < $results['url_count']) {
        $dupCount = $results['url_count'] - $results['unique_url_count'];
        $issues['warnings'][] = "Die Sitemap enthält {$dupCount} doppelte URLs";
    }
    
    if ($results['filesize'] > 52428800) { // 50 MB
        $sizeMB = round($results['filesize'] / (1024 * 1024), 2);
        $issues['warnings'][] = "Die Sitemap-Größe ({$sizeMB} MB) überschreitet die empfohlene Maximalgröße von 50 MB";
    }
    
    if (!$results['robots_txt_reference'] && $results['robots_txt_accessible']) {
        $issues['warnings'][] = "Die Sitemap wird nicht in der robots.txt referenziert";
    }
    
    // Neu: Lastmod-Fehler prüfen
    if (isset($results['has_invalid_lastmod']) && $results['has_invalid_lastmod']) {
        $issues['critical'][] = "Die Sitemap enthält ungültige lastmod-Datumsformate";
    }
    
    // Neu: Changefreq-Fehler prüfen
    if (isset($results['has_invalid_changefreq']) && $results['has_invalid_changefreq']) {
        $issues['warnings'][] = "Die Sitemap enthält ungültige changefreq-Werte";
    }
    
    // Neu: Priority-Fehler prüfen
    if (isset($results['has_invalid_priority']) && $results['has_invalid_priority']) {
        $issues['warnings'][] = "Die Sitemap enthält ungültige priority-Werte (außerhalb von 0.0 - 1.0)";
    }
    
    // Informationen sammeln
    if (!$results['has_lastmod']) {
        $issues['info'][] = "Die Sitemap enthält keine lastmod-Attribute";
    }
    
    if (!$results['has_priority']) {
        $issues['info'][] = "Die Sitemap enthält keine priority-Attribute";
    }
    
    if (!$results['has_changefreq']) {
        $issues['info'][] = "Die Sitemap enthält keine changefreq-Attribute";
    }
    
    if (!$results['is_compressed']) {
        if (isset($results['http_compressed']) && $results['http_compressed']) {
            $issues['info'][] = "Die Sitemap ist nicht als .xml.gz Datei gespeichert, wird aber über HTTP komprimiert übertragen";
        } else {
            $issues['info'][] = "Die Sitemap ist nicht komprimiert (weder als .xml.gz noch per HTTP)";
        }
    }
    
    // URL-Stichproben prüfen
    $urlSampleStatus = is_string($results['url_sample_status']) ? 
                      json_decode($results['url_sample_status'], true) : 
                      $results['url_sample_status'];
                      
    if (!empty($urlSampleStatus) && is_array($urlSampleStatus)) {
        $errorUrls = [];
        
        foreach ($urlSampleStatus as $status) {
            if ($status['http_status'] !== 200) {
                $errorUrls[] = [
                    'url' => $status['url'],
                    'issue' => "HTTP-Status {$status['http_status']}"
                ];
            } else if ($status['blocked_by_robots']) {
                $errorUrls[] = [
                    'url' => $status['url'],
                    'issue' => "Durch robots.txt blockiert"
                ];
            } else if ($status['has_noindex_meta'] || $status['has_noindex_header']) {
                $errorUrls[] = [
                    'url' => $status['url'],
                    'issue' => "Hat noindex-Direktive"
                ];
            }
        }
        
        if (!empty($errorUrls)) {
            $issues['warnings'][] = "Probleme mit URLs in der Stichprobe gefunden";
            $issues['url_samples'] = $errorUrls;
        }
    }
    
    return $issues;
}

/**
 * Generiert Empfehlungen zur Verbesserung der Sitemap basierend auf der Analyse
 * 
 * @param array $results Ergebnisse der Sitemap-Validierung
 * @return array Liste der Empfehlungen
 */
function generateSitemapRecommendations($results) {
    $recommendations = [];
    
    // Grundlegende Empfehlungen
    if (!$results['valid_xml'] || !$results['valid_root_element']) {
        $recommendations[] = [
            'title' => 'Gültiges XML-Format',
            'description' => 'Stellen Sie sicher, dass Ihre Sitemap gültiges XML ist und das richtige Root-Element (`urlset` oder `sitemapindex`) mit dem korrekten Namespace verwendet.',
            'example' => '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://www.example.com/</loc>
    <lastmod>2025-04-01</lastmod>
  </url>
</urlset>'
        ];
    }
    
    // MIME-Type Empfehlung
    if (isset($results['valid_mime_type']) && !$results['valid_mime_type']) {
        $recommendations[] = [
            'title' => 'Korrekten MIME-Type konfigurieren',
            'description' => 'Stellen Sie sicher, dass Ihr Server die Sitemap mit dem korrekten Content-Type ausliefert: application/xml oder text/xml für XML-Dateien, application/gzip für komprimierte Dateien.',
            'example' => '# Für Apache (.htaccess):
AddType application/xml .xml
AddType application/gzip .gz

# Für Nginx (nginx.conf):
types {
    application/xml xml;
    application/gzip gz;
}'
        ];
    }
    
    // Größen- und Performance-Empfehlungen
    if ($results['url_count'] > 50000) {
        $recommendations[] = [
            'title' => 'Aufteilen der Sitemap',
            'description' => 'Ihre Sitemap überschreitet das Limit von 50.000 URLs. Teilen Sie diese in mehrere Sitemap-Dateien auf und verwenden Sie einen Sitemap-Index.',
            'example' => '<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://www.example.com/sitemap1.xml</loc>
    <lastmod>2025-04-01</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://www.example.com/sitemap2.xml</loc>
    <lastmod>2025-04-01</lastmod>
  </sitemap>
</sitemapindex>'
        ];
    }
    
    if ($results['filesize'] > 50 * 1024 * 1024 && !$results['is_compressed']) {
        if (isset($results['http_compressed']) && $results['http_compressed']) {
            $recommendations[] = [
                'title' => 'Sitemap als .xml.gz speichern',
                'description' => 'Ihre Sitemap wird zwar über HTTP komprimiert übertragen, aber um die Kompatibilität mit allen Suchmaschinen zu gewährleisten, sollten Sie die Sitemap auch als .xml.gz Datei speichern.',
                'example' => '# Komprimierung mit gzip in PHP:
$sitemapContent = generate_sitemap_content();
file_put_contents("sitemap.xml.gz", gzencode($sitemapContent, 9));

# Oder über die Kommandozeile:
gzip -9 sitemap.xml'
            ];
        } else {
            $recommendations[] = [
                'title' => 'Komprimierung der Sitemap',
                'description' => 'Ihre Sitemap ist größer als empfohlen. Verwenden Sie gzip-Komprimierung (.xml.gz) oder aktivieren Sie die HTTP-Komprimierung auf Ihrem Server für bessere Performance.',
                'example' => '# Komprimierung mit gzip in PHP:
$sitemapContent = generate_sitemap_content();
file_put_contents("sitemap.xml.gz", gzencode($sitemapContent, 9));

# Oder für HTTP-Komprimierung in Apache (.htaccess):
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/xml application/xml
</IfModule>'
            ];
        }
    }
    
    // Attributfehler
    if (isset($results['has_invalid_lastmod']) && $results['has_invalid_lastmod']) {
        $recommendations[] = [
            'title' => 'Korrekte lastmod-Datumsformate verwenden',
            'description' => 'Korrigieren Sie die ungültigen lastmod-Datumsformate. Verwenden Sie das ISO 8601-Format (YYYY-MM-DD).',
            'example' => '<url>
  <loc>https://www.example.com/page</loc>
  <lastmod>2025-04-01</lastmod>
</url>

<!-- Oder mit Uhrzeit -->
<url>
  <loc>https://www.example.com/page</loc>
  <lastmod>2025-04-01T12:30:00+00:00</lastmod>
</url>'
        ];
    }
    
    if (isset($results['has_invalid_changefreq']) && $results['has_invalid_changefreq']) {
        $recommendations[] = [
            'title' => 'Gültige changefreq-Werte verwenden',
            'description' => 'Verwenden Sie nur gültige changefreq-Werte: always, hourly, daily, weekly, monthly, yearly, never.',
            'example' => '<url>
  <loc>https://www.example.com/home</loc>
  <changefreq>daily</changefreq>
</url>

<url>
  <loc>https://www.example.com/about</loc>
  <changefreq>monthly</changefreq>
</url>'
        ];
    }
    
    if (isset($results['has_invalid_priority']) && $results['has_invalid_priority']) {
        $recommendations[] = [
            'title' => 'Gültige priority-Werte verwenden',
            'description' => 'Priority-Werte müssen zwischen 0.0 und 1.0 liegen. Der Standardwert ist 0.5.',
            'example' => '<url>
  <loc>https://www.example.com/</loc>
  <priority>1.0</priority>
</url>

<url>
  <loc>https://www.example.com/category</loc>
  <priority>0.8</priority>
</url>

<url>
  <loc>https://www.example.com/product</loc>
  <priority>0.6</priority>
</url>'
        ];
    }
    
    // SEO-Best-Practices
    if (!$results['has_lastmod']) {
        $recommendations[] = [
            'title' => 'Hinzufügen von Lastmod-Datumsangaben',
            'description' => 'Lastmod-Daten helfen Suchmaschinen zu erkennen, wann Inhalte aktualisiert wurden. Fügen Sie für jede URL ein lastmod-Element hinzu.',
            'example' => '<url>
  <loc>https://www.example.com/page</loc>
  <lastmod>2025-04-01</lastmod>
</url>'
        ];
    }
    
    if (!$results['robots_txt_reference']) {
        $recommendations[] = [
            'title' => 'Sitemap in robots.txt eintragen',
            'description' => 'Fügen Sie Ihre Sitemap in die robots.txt-Datei ein, damit Suchmaschinen sie einfacher finden können.',
            'example' => '# In robots.txt:
User-agent: *
Allow: /

Sitemap: https://www.example.com/sitemap.xml'
        ];
    }
    
    // Erweiterungsempfehlungen basierend auf der Art der Website
    if (!$results['has_image_extension'] && !$results['has_video_extension']) {
        $recommendations[] = [
            'title' => 'Erwägen Sie spezielle Erweiterungen',
            'description' => 'Wenn Ihre Website Bilder oder Videos enthält, können Sie spezielle Erweiterungen verwenden, um diese besser zu indexieren.',
            'example' => '<!-- Beispiel für Image-Erweiterung -->
<url>
  <loc>https://www.example.com/page-with-images</loc>
  <image:image>
    <image:loc>https://www.example.com/images/example.jpg</image:loc>
    <image:title>Beispielbild Titel</image:title>
    <image:caption>Eine Beschreibung des Bildes</image:caption>
  </image:image>
</url>

<!-- Beispiel für Video-Erweiterung -->
<url>
  <loc>https://www.example.com/page-with-video</loc>
  <video:video>
    <video:thumbnail_loc>https://www.example.com/thumbs/video.jpg</video:thumbnail_loc>
    <video:title>Video Titel</video:title>
    <video:description>Beschreibung des Videos</video:description>
    <video:content_loc>https://www.example.com/videos/myvideo.mp4</video:content_loc>
    <video:duration>120</video:duration>
  </video:video>
</url>'
        ];
    }
    
    if (!$results['has_alternate_links'] && strpos($results['url'], '.com') !== false) {
        $recommendations[] = [
            'title' => 'Internationale Websites: Hreflang-Attribute hinzufügen',
            'description' => 'Wenn Ihre Website mehrere Sprachversionen hat, nutzen Sie hreflang-Attribute, um Suchmaschinen die richtige Version für jede Sprache und Region zu zeigen.',
            'example' => '<url>
  <loc>https://www.example.com/english-page</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://www.example.com/english-page" />
  <xhtml:link rel="alternate" hreflang="de" href="https://www.example.com/de/deutsche-seite" />
  <xhtml:link rel="alternate" hreflang="fr" href="https://www.example.com/fr/page-francais" />
</url>'
        ];
    }
    
    return $recommendations;
}

/**
 * Diese Funktion generiert ein Sitemap-XML-Template basierend auf dem angegebenen Typ
 * 
 * @param string $baseUrl Basis-URL der Website
 * @param string $type Art der Sitemap (basic, index, image, hreflang)
 * @return string XML-Template für die Sitemap
 */
function generateSitemapTemplate($baseUrl, $type = 'basic') {
    $xmlTemplate = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    
    switch ($type) {
        case 'index':
            $xmlTemplate .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            $xmlTemplate .= '  <sitemap>' . "\n";
            $xmlTemplate .= '    <loc>' . htmlspecialchars($baseUrl) . '/sitemap1.xml</loc>' . "\n";
            $xmlTemplate .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xmlTemplate .= '  </sitemap>' . "\n";
            $xmlTemplate .= '  <sitemap>' . "\n";
            $xmlTemplate .= '    <loc>' . htmlspecialchars($baseUrl) . '/sitemap2.xml</loc>' . "\n";
            $xmlTemplate .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xmlTemplate .= '  </sitemap>' . "\n";
            $xmlTemplate .= '</sitemapindex>';
            break;
            
        case 'image':
            $xmlTemplate .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
            $xmlTemplate .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
            $xmlTemplate .= '  <url>' . "\n";
            $xmlTemplate .= '    <loc>' . htmlspecialchars($baseUrl) . '/page-with-images</loc>' . "\n";
            $xmlTemplate .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xmlTemplate .= '    <image:image>' . "\n";
            $xmlTemplate .= '      <image:loc>' . htmlspecialchars($baseUrl) . '/images/example1.jpg</image:loc>' . "\n";
            $xmlTemplate .= '      <image:title>Beispielbild 1</image:title>' . "\n";
            $xmlTemplate .= '    </image:image>' . "\n";
            $xmlTemplate .= '    <image:image>' . "\n";
            $xmlTemplate .= '      <image:loc>' . htmlspecialchars($baseUrl) . '/images/example2.jpg</image:loc>' . "\n";
            $xmlTemplate .= '      <image:title>Beispielbild 2</image:title>' . "\n";
            $xmlTemplate .= '    </image:image>' . "\n";
            $xmlTemplate .= '  </url>' . "\n";
            $xmlTemplate .= '</urlset>';
            break;
            
        case 'hreflang':
            $xmlTemplate .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
            $xmlTemplate .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
            $xmlTemplate .= '  <url>' . "\n";
            $xmlTemplate .= '    <loc>' . htmlspecialchars($baseUrl) . '</loc>' . "\n";
            $xmlTemplate .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xmlTemplate .= '    <xhtml:link rel="alternate" hreflang="de" href="' . htmlspecialchars($baseUrl) . '" />' . "\n";
            $xmlTemplate .= '    <xhtml:link rel="alternate" hreflang="en" href="' . htmlspecialchars($baseUrl) . '/en/" />' . "\n";
            $xmlTemplate .= '    <xhtml:link rel="alternate" hreflang="fr" href="' . htmlspecialchars($baseUrl) . '/fr/" />' . "\n";
            $xmlTemplate .= '  </url>' . "\n";
            $xmlTemplate .= '</urlset>';
            break;
            
        case 'basic':
        default:
            $xmlTemplate .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            $xmlTemplate .= '  <url>' . "\n";
            $xmlTemplate .= '    <loc>' . htmlspecialchars($baseUrl) . '</loc>' . "\n";
            $xmlTemplate .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xmlTemplate .= '    <changefreq>weekly</changefreq>' . "\n";
            $xmlTemplate .= '    <priority>1.0</priority>' . "\n";
            $xmlTemplate .= '  </url>' . "\n";
            $xmlTemplate .= '  <url>' . "\n";
            $xmlTemplate .= '    <loc>' . htmlspecialchars($baseUrl) . '/beispiel-seite</loc>' . "\n";
            $xmlTemplate .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xmlTemplate .= '    <changefreq>monthly</changefreq>' . "\n";
            $xmlTemplate .= '    <priority>0.8</priority>' . "\n";
            $xmlTemplate .= '  </url>' . "\n";
            $xmlTemplate .= '</urlset>';
            break;
    }
    
    return $xmlTemplate;
}

/**
 * Sitemap validieren
 * 
 * @param string $url URL der Sitemap
 * @param string|null $cacheDir Verzeichnis zum Cachen der Sitemap
 * @param array $config Konfiguration
 * @param string|null $id ID für den Cache-Dateinamen
 * @return array Ergebnisse der Validierung
 */
function validateSitemap($url, $cacheDir, $config, $id = null) {
    $results = [
        'url' => $url,
        'date_checked' => date('Y-m-d H:i:s'),
        'http_status' => null,
        'valid_xml' => false,
        'encoding_utf8' => false,
        'valid_root_element' => false,
        'url_count' => 0,
        'unique_url_count' => 0,
        'has_lastmod' => false,
        'has_changefreq' => false,
        'has_priority' => false,
        'has_invalid_lastmod' => false,
        'has_invalid_changefreq' => false,
        'has_invalid_priority' => false,
        'valid_mime_type' => false,
        'content_type' => null,
        'http_compressed' => false,
        'filesize' => 0,
        'is_sitemap_index' => false,
        'is_compressed' => false,
        'load_time' => 0,
        'has_image_extension' => false,
        'has_video_extension' => false,
        'has_news_extension' => false,
        'has_mobile_extension' => false,
        'has_alternate_links' => false,
        // Neue Felder für Erweiterungszähler
        'image_extension_count' => 0,
        'video_extension_count' => 0,
        'news_extension_count' => 0,
        'mobile_extension_count' => 0,
        'alternate_links_count' => 0,
        'robots_txt_reference' => false,
        'robots_txt_accessible' => false,
        'url_sample_status' => [],
        'validation_results' => [],
        'errors' => []
    ];
    
    // Prüfen, ob es sich um eine komprimierte Sitemap handelt
    $results['is_compressed'] = (pathinfo($url, PATHINFO_EXTENSION) === 'gz');
    
    // Sitemap-URL abrufen mit cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Akzeptiere alle verfügbaren Encodings
    curl_setopt($ch, CURLOPT_USERAGENT, $config['validator']['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['validator']['http_timeout']);
    
    // SSL-Überprüfung entsprechend der Konfiguration
    if ($config['security']['verify_ssl']) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    
    // Eingeschränkte Protokolle (nur HTTP und HTTPS)
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    
    // Ladezeit-Messung starten
    $startTime = microtime(true);
    
    $response = curl_exec($ch);
    
    // Ladezeit berechnen
    $endTime = microtime(true);
    $results['load_time'] = round($endTime - $startTime, 3); // in Sekunden mit 3 Nachkommastellen
    
    // HTTP-Status prüfen
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $results['http_status'] = $httpCode;
    
    // MIME-Type überprüfen
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $results['content_type'] = $contentType;
    
    // Valide MIME-Types prüfen
    $validXmlMimeTypes = ['text/xml', 'application/xml', 'application/xhtml+xml', 'text/html']; // HTML kann auch XML enthalten
    $validGzipMimeTypes = ['application/gzip', 'application/x-gzip', 'application/octet-stream']; // octet-stream kann auch gzip sein
    
    $results['valid_mime_type'] = false;
    if ($results['is_compressed']) {
        // Für komprimierte Dateien
        foreach ($validGzipMimeTypes as $mimeType) {
            if (strpos($contentType, $mimeType) !== false) {
                $results['valid_mime_type'] = true;
                break;
            }
        }
        
        if (!$results['valid_mime_type']) {
            $results['errors'][] = "Ungültiger MIME-Type für komprimierte Sitemap: " . $contentType . ". Erwartet wurde einer von: " . implode(', ', $validGzipMimeTypes);
        }
    } else {
        // Für XML-Dateien
        foreach ($validXmlMimeTypes as $mimeType) {
            if (strpos($contentType, $mimeType) !== false) {
                $results['valid_mime_type'] = true;
                break;
            }
        }
        
        if (!$results['valid_mime_type']) {
            $results['errors'][] = "Ungültiger MIME-Type für XML-Sitemap: " . $contentType . ". Erwartet wurde einer von: " . implode(', ', $validXmlMimeTypes);
        }
    }
    
    if ($httpCode !== 200) {
        $results['errors'][] = "HTTP-Status ist nicht 200 OK, sondern: $httpCode";
        curl_close($ch);
        return $results;
    }
    
    // Header und Body trennen
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);  
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Zusätzlich prüfen, ob die Sitemap mit HTTP-Komprimierung übermittelt wurde
    $results['http_compressed'] = false;

    // Verbesserte Header-Erkennung mit case-insensitiver Regex
    if (preg_match('/content-encoding:\s*(gzip|deflate|br)/i', $header)) {
        $results['http_compressed'] = true;
        // Speichere den Content-Encoding-Header für die Anzeige
        if (preg_match('/content-encoding:\s*(.*?)(\r\n|\n)/i', $header, $matches)) {
        $results['content_encoding_header'] = trim($matches[1]);
    }
} 
// Auch die Transfer-Encoding prüfen (manchmal stattdessen verwendet)
else if (preg_match('/transfer-encoding:\s*(gzip|deflate|chunked)/i', $header)) {
    $results['http_compressed'] = true;
    if (preg_match('/transfer-encoding:\s*(.*?)(\r\n|\n)/i', $header, $matches)) {
        $results['content_encoding_header'] = trim($matches[1]);
    }
}

// Alternative Methode: Prüfen, ob cURL automatisch dekomprimiert hat
    $originalSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    if ($originalSize > 0 && $downloadSize > 0 && $originalSize != $downloadSize) {
        $results['http_compressed'] = true;
}
    
    curl_close($ch);
    
    // Wenn es eine .gz-Datei ist und der Inhalt noch nicht dekomprimiert wurde, manuell dekomprimieren
    if ($results['is_compressed'] && strpos($body, '<?xml') === false) {
        $decompressedBody = gzdecode($body);
        if ($decompressedBody !== false) {
            $body = $decompressedBody;
        } else {
            $results['errors'][] = "Konnte die komprimierte Sitemap nicht dekomprimieren.";
            return $results;
        }
    }
    
    // Sitemap-Inhalt für Debugging zwischenspeichern
    if ($cacheDir !== null) {
        // Verwende die übergebene ID (oder generiere einen Hash, falls keine ID vorhanden ist)
        $cacheFileName = $cacheDir . '/' . (isset($id) ? $id : md5($url)) . '.xml';
        file_put_contents($cacheFileName, $body);
    }
    
    // Dateigröße prüfen
    $results['filesize'] = strlen($body);
    if ($results['filesize'] > $config['security']['max_filesize']) {
        $results['errors'][] = "Sitemap überschreitet die maximale Größe von " . 
                               round($config['security']['max_filesize'] / (1024 * 1024), 1) . " MB";
    }
    
    // Content-Type überprüfen (aus HTTP-Header)
    $encodingFromHeader = false;
    if (preg_match('/Content-Type: .*charset=([^\s;]+)/i', $header, $matches)) {
        $charset = strtoupper($matches[1]);
        $encodingFromHeader = ($charset === 'UTF-8');
    }
    
    // XML-Deklaration überprüfen auf Encoding
    $encodingFromXml = false;
    if (preg_match('/<\?xml[^>]+encoding="([^"]+)"/i', $body, $matches)) {
        $charset = strtoupper($matches[1]);
        $encodingFromXml = ($charset === 'UTF-8');
    }
    
    // Wenn entweder der Header oder die XML-Deklaration UTF-8 angeben, gilt es als UTF-8-kodiert
    $results['encoding_utf8'] = $encodingFromHeader || $encodingFromXml;
    
    if (!$results['encoding_utf8']) {
        $results['errors'][] = "Encoding ist nicht UTF-8";
    }
    
    // XML laden und validieren
    libxml_use_internal_errors(true);
    
    // XXE-Angriffe verhindern (neue Methode)
    $parser = xml_parser_create();
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    
    $xml = simplexml_load_string($body);
    
    if ($xml === false) {
        $xmlErrors = libxml_get_errors();
        foreach ($xmlErrors as $error) {
            $results['errors'][] = "XML-Fehler: " . $error->message;
        }
        libxml_clear_errors();
        return $results;
    }
    
    $results['valid_xml'] = true;
    
    // Root-Element prüfen
    $rootName = $xml->getName();
    $namespace = $xml->getNamespaces(false);
    $allNamespaces = $xml->getNamespaces(true);
    
    // Erweiterungen prüfen
    $results['has_image_extension'] = isset($allNamespaces['image']) || 
                                     in_array('http://www.google.com/schemas/sitemap-image/1.1', $allNamespaces);
    $results['has_video_extension'] = isset($allNamespaces['video']) || 
                                     in_array('http://www.google.com/schemas/sitemap-video/1.1', $allNamespaces);
    $results['has_news_extension'] = isset($allNamespaces['news']) || 
                                    in_array('http://www.google.com/schemas/sitemap-news/0.9', $allNamespaces);
    $results['has_mobile_extension'] = isset($allNamespaces['mobile']) || 
                                      in_array('http://www.google.com/schemas/sitemap-mobile/1.0', $allNamespaces);
    
    if ($rootName === 'urlset') {
        $results['valid_root_element'] = isset($namespace['']) && 
                                        $namespace[''] === 'http://www.sitemaps.org/schemas/sitemap/0.9';
        if (!$results['valid_root_element']) {
            $results['errors'][] = "Ungültiges Root-Element oder Namespace";
        }
        
        // URLs zählen und prüfen
        $urlElements = $xml->url;
        $urlCount = count($urlElements);
        $results['url_count'] = $urlCount;
        
        if ($urlCount > 50000) {
            $results['errors'][] = "Sitemap enthält mehr als 50.000 URLs ($urlCount)";
        }
        
        // Eindeutige URLs prüfen
        $uniqueUrls = [];
        $hasAlternateLinks = false;
        
        // Zähler für Erweiterungen initialisieren
        $imageCount = 0;
        $videoCount = 0;
        $newsCount = 0;
        $mobileCount = 0;
        $alternateCount = 0;
        
        // Prüfvariablen für Attributfehler initialisieren
        $hasInvalidLastmod = false;
        $hasInvalidChangefreq = false;
        $hasInvalidPriority = false;
        $validChangefreqs = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        
        foreach ($urlElements as $urlElement) {
            if (isset($urlElement->loc)) {
                $loc = (string)$urlElement->loc;
                $uniqueUrls[$loc] = true;
                
                // URL-Format prüfen
                if (!filter_var($loc, FILTER_VALIDATE_URL)) {
                    $results['errors'][] = "Ungültige URL: $loc";
                }
                
                // Optionale Elemente prüfen
                if (isset($urlElement->lastmod)) {
                    $results['has_lastmod'] = true;
                    
                    // Strenge Prüfung auf ISO 8601 Datum Format (YYYY-MM-DD) oder mit Zeitzone
                    $lastmod = (string)$urlElement->lastmod;
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})?)?$/', $lastmod)) {
                        $hasInvalidLastmod = true;
                        $results['errors'][] = "Ungültiges lastmod-Format: $lastmod - Sollte ISO 8601 Format sein (YYYY-MM-DD)";
                    }
                }
                
                if (isset($urlElement->changefreq)) {
                    $results['has_changefreq'] = true;
                    
                    // Prüfen, ob changefreq einen gültigen Wert hat
                    $changefreq = (string)$urlElement->changefreq;
                    if (!in_array(strtolower($changefreq), $validChangefreqs)) {
                        $hasInvalidChangefreq = true;
                        $results['errors'][] = "Ungültiger changefreq-Wert: $changefreq - Erlaubt sind: " . implode(', ', $validChangefreqs);
                    }
                }
                
                if (isset($urlElement->priority)) {
                    $results['has_priority'] = true;
                    
                    // Prüfen, ob priority zwischen 0.0 und 1.0 liegt
                    $priority = (float)$urlElement->priority;
                    if ($priority < 0.0 || $priority > 1.0) {
                        $hasInvalidPriority = true;
                        $results['errors'][] = "Ungültiger priority-Wert: $priority - Muss zwischen 0.0 und 1.0 liegen";
                    }
                }
                
                // Bilder-Erweiterungen zählen
                if ($results['has_image_extension']) {
                    $imageElements = $urlElement->children('http://www.google.com/schemas/sitemap-image/1.1');
                    if (isset($imageElements->image)) {
                        $imageCount += count($imageElements->image);
                    }
                }
                
                // Video-Erweiterungen zählen
                if ($results['has_video_extension']) {
                    $videoElements = $urlElement->children('http://www.google.com/schemas/sitemap-video/1.1');
                    if (isset($videoElements->video)) {
                        $videoCount += count($videoElements->video);
                    }
                }
                
                // News-Erweiterungen zählen
                if ($results['has_news_extension']) {
                    $newsElements = $urlElement->children('http://www.google.com/schemas/sitemap-news/0.9');
                    if (isset($newsElements->news)) {
                        $newsCount += count($newsElements->news);
                    }
                }
                
                // Mobile-Erweiterungen zählen
                if ($results['has_mobile_extension']) {
                    $mobileElements = $urlElement->children('http://www.google.com/schemas/sitemap-mobile/1.0');
                    if (isset($mobileElements->mobile)) {
                        $mobileCount += count($mobileElements->mobile);
                    }
                }
                
                // Prüfen auf alternative Sprachversionen (hreflang)
                $linkElements = $urlElement->children('http://www.w3.org/1999/xhtml');
                if (isset($linkElements->link)) {
                    foreach ($linkElements->link as $link) {
                        $attributes = $link->attributes();
                        if (isset($attributes['rel']) && (string)$attributes['rel'] === 'alternate' && isset($attributes['hreflang'])) {
                            $hasAlternateLinks = true;
                            $alternateCount++;
                        }
                    }
                }
            } else {
                $results['errors'][] = "URL-Element ohne <loc> gefunden";
            }
        }
        // Zählwerte für Erweiterungen setzen
        $results['has_alternate_links'] = $hasAlternateLinks;
        $results['image_extension_count'] = $imageCount;
        $results['video_extension_count'] = $videoCount;
        $results['news_extension_count'] = $newsCount;
        $results['mobile_extension_count'] = $mobileCount;
        $results['alternate_links_count'] = $alternateCount;
        
        // Attributfehler setzen
        $results['has_invalid_lastmod'] = $hasInvalidLastmod;
        $results['has_invalid_changefreq'] = $hasInvalidChangefreq;
        $results['has_invalid_priority'] = $hasInvalidPriority;
        
        $results['unique_url_count'] = count($uniqueUrls);
        if ($results['unique_url_count'] < $results['url_count']) {
            $results['errors'][] = "Sitemap enthält doppelte URLs";
        }
        
        // Robots.txt-Prüfung
        $robotsTxt = getRobotsTxt($url, $config);
        if ($robotsTxt !== false) {
            $results['robots_txt_accessible'] = true;
            $results['robots_txt_reference'] = isSitemapReferencedInRobotsTxt($url, $robotsTxt, $config);
            
            if (!$results['robots_txt_reference']) {
                $results['errors'][] = "Die Sitemap wird nicht in der robots.txt referenziert.";
            }
            
            // Zufällige URL-Auswahl für die Stichprobe
            $urlArray = array_keys($uniqueUrls);
            if (count($urlArray) <= $config['validator']['sample_urls_count']) {
                $urlSample = $urlArray; // Alle URLs nehmen, wenn es weniger als konfiguriert sind
            } else {
                // Zufällige Auswahl von konfigurierten URLs
                shuffle($urlArray);
                $urlSample = array_slice($urlArray, 0, $config['validator']['sample_urls_count']);
            }
            
            // Stichprobe asynchron prüfen
            $urlStatusSample = checkUrlsSampleAsync($urlSample, $robotsTxt, $config);
            $results['url_sample_status'] = $urlStatusSample;
            
            // Prüfen auf Fehler in den Stichproben
            foreach ($urlStatusSample as $status) {
                if ($status['http_status'] !== 200) {
                    $results['errors'][] = "URLs in der Sitemap nicht erreichbar (HTTP ".$status['http_status']."): ".$status['url'];
                }
                
                if ($status['blocked_by_robots']) {
                    $results['errors'][] = "URLs in der Sitemap durch robots.txt blockiert: ".$status['url'];
                }
                
                if (isset($status['has_noindex_meta']) && $status['has_noindex_meta'] || 
                    isset($status['has_noindex_header']) && $status['has_noindex_header']) {
                    $results['errors'][] = "URLs in der Sitemap mit noindex-Direktive: ".$status['url'];
                }
            }
        } else {
            $results['robots_txt_accessible'] = false;
            $results['errors'][] = "robots.txt konnte nicht abgerufen werden.";
        }
        
    } elseif ($rootName === 'sitemapindex') {
        $results['is_sitemap_index'] = true;
        $results['valid_root_element'] = isset($namespace['']) && 
                                        $namespace[''] === 'http://www.sitemaps.org/schemas/sitemap/0.9';
        
        if (!$results['valid_root_element']) {
            $results['errors'][] = "Ungültiges Root-Element oder Namespace für Sitemap-Index";
        }
        
        // Sitemap-Einträge zählen
        $sitemapElements = $xml->sitemap;
        $sitemapCount = count($sitemapElements);
        $results['url_count'] = $sitemapCount;
        
        if ($sitemapCount > 50000) {
            $results['errors'][] = "Sitemap-Index enthält mehr als 50.000 Sitemap-Einträge ($sitemapCount)";
        }
        
        // Sitemap-URLs prüfen
        $uniqueSitemaps = [];
        foreach ($sitemapElements as $sitemapElement) {
            if (isset($sitemapElement->loc)) {
                $loc = (string)$sitemapElement->loc;
                $uniqueSitemaps[$loc] = true;
                
                // URL-Format prüfen
                if (!filter_var($loc, FILTER_VALIDATE_URL)) {
                    $results['errors'][] = "Ungültige Sitemap-URL: $loc";
                }
                
                if (isset($sitemapElement->lastmod)) {
                    $results['has_lastmod'] = true;
                }
            } else {
                $results['errors'][] = "Sitemap-Element ohne <loc> gefunden";
            }
        }
        
        $results['unique_url_count'] = count($uniqueSitemaps);
        if ($results['unique_url_count'] < $results['url_count']) {
            $results['errors'][] = "Sitemap-Index enthält doppelte Sitemap-URLs";
        }
        
        // Robots.txt-Prüfung
        $robotsTxt = getRobotsTxt($url, $config);
        if ($robotsTxt !== false) {
            $results['robots_txt_accessible'] = true;
            $results['robots_txt_reference'] = isSitemapReferencedInRobotsTxt($url, $robotsTxt, $config);
            
            if (!$results['robots_txt_reference']) {
                $results['errors'][] = "Die Sitemap wird nicht in der robots.txt referenziert.";
            }
        } else {
            $results['robots_txt_accessible'] = false;
            $results['errors'][] = "robots.txt konnte nicht abgerufen werden.";
        }
    } else {
        $results['errors'][] = "Unerwartetes Root-Element: $rootName (erwartet: urlset oder sitemapindex)";
    }
    
    // Sitemap-Score berechnen
    $sitemapScore = calculateSitemapScore($results);
    $results['sitemap_score'] = $sitemapScore['score'];
    $results['sitemap_grade'] = $sitemapScore['grade'];
    
    return $results;
}

/**
 * Ergebnisse in Datenbank speichern
 * 
 * @param PDO $db Datenbank-Verbindung
 * @param string $id ID für den Datensatz
 * @param array $results Ergebnisse der Validierung
 * @return bool True bei Erfolg
 */
function saveResults($db, $id, $results) {
    error_log("Versuche Ergebnisse zu speichern für ID: " . $id);
    $sql = "INSERT INTO sitemap_validations (
        id, url, date_checked, http_status, valid_xml, encoding_utf8, 
        valid_root_element, url_count, unique_url_count, has_lastmod, 
        has_changefreq, has_priority, has_invalid_lastmod, has_invalid_changefreq,
        has_invalid_priority, valid_mime_type, content_type, http_compressed,
        filesize, is_sitemap_index, is_compressed, load_time, has_image_extension,
        has_video_extension, has_news_extension, has_mobile_extension, has_alternate_links,
        image_extension_count, video_extension_count, content_encoding_header, news_extension_count,
        mobile_extension_count, alternate_links_count, sitemap_score, sitemap_grade,
        robots_txt_reference, robots_txt_accessible, url_sample_status,
        validation_results, errors
    ) VALUES (
        :id, :url, :date_checked, :http_status, :valid_xml, :encoding_utf8,
        :valid_root_element, :url_count, :unique_url_count, :has_lastmod,
        :has_changefreq, :has_priority, :has_invalid_lastmod, :has_invalid_changefreq,
        :has_invalid_priority, :valid_mime_type, :content_type, :http_compressed,
        :filesize, :is_sitemap_index, :is_compressed, :load_time, :has_image_extension,
        :has_video_extension, :has_news_extension, :has_mobile_extension, :has_alternate_links,
        :image_extension_count, :video_extension_count, :content_encoding_header, :news_extension_count,
        :mobile_extension_count, :alternate_links_count, :sitemap_score, :sitemap_grade,
        :robots_txt_reference, :robots_txt_accessible, :url_sample_status,
        :validation_results, :errors
    )";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':url' => $results['url'],
            ':date_checked' => $results['date_checked'],
            ':http_status' => $results['http_status'],
            ':valid_xml' => $results['valid_xml'] ? 1 : 0,
            ':encoding_utf8' => $results['encoding_utf8'] ? 1 : 0,
            ':valid_root_element' => $results['valid_root_element'] ? 1 : 0,
            ':url_count' => $results['url_count'],
            ':unique_url_count' => $results['unique_url_count'],
            ':has_lastmod' => $results['has_lastmod'] ? 1 : 0,
            ':has_changefreq' => $results['has_changefreq'] ? 1 : 0,
            ':has_priority' => $results['has_priority'] ? 1 : 0,
            ':has_invalid_lastmod' => $results['has_invalid_lastmod'] ?? false ? 1 : 0,
            ':has_invalid_changefreq' => $results['has_invalid_changefreq'] ?? false ? 1 : 0,
            ':has_invalid_priority' => $results['has_invalid_priority'] ?? false ? 1 : 0,
            ':valid_mime_type' => $results['valid_mime_type'] ?? false ? 1 : 0,
            ':content_type' => $results['content_type'] ?? null,
            ':http_compressed' => $results['http_compressed'] ?? false ? 1 : 0,
            ':filesize' => $results['filesize'],
            ':is_sitemap_index' => $results['is_sitemap_index'] ? 1 : 0,
            ':is_compressed' => $results['is_compressed'] ? 1 : 0,
            ':load_time' => $results['load_time'],
            ':has_image_extension' => $results['has_image_extension'] ? 1 : 0,
            ':has_video_extension' => $results['has_video_extension'] ? 1 : 0,
            ':has_news_extension' => $results['has_news_extension'] ? 1 : 0,
            ':has_mobile_extension' => $results['has_mobile_extension'] ? 1 : 0,
            ':has_alternate_links' => $results['has_alternate_links'] ? 1 : 0,
            ':image_extension_count' => $results['image_extension_count'] ?? 0,
            ':video_extension_count' => $results['video_extension_count'] ?? 0,
            ':news_extension_count' => $results['news_extension_count'] ?? 0,
            ':mobile_extension_count' => $results['mobile_extension_count'] ?? 0,
            ':alternate_links_count' => $results['alternate_links_count'] ?? 0,
            ':sitemap_score' => $results['sitemap_score'] ?? 0,
            ':content_encoding_header' => $results['content_encoding_header'] ?? null,
            ':sitemap_grade' => json_encode($results['sitemap_grade'] ?? []),
            ':robots_txt_reference' => $results['robots_txt_reference'] ? 1 : 0,
            ':robots_txt_accessible' => $results['robots_txt_accessible'] ? 1 : 0,
            ':url_sample_status' => json_encode($results['url_sample_status']),
            ':validation_results' => json_encode($results, JSON_PRETTY_PRINT),
            ':errors' => json_encode($results['errors'], JSON_PRETTY_PRINT)
        ]);
        return true;
} catch (PDOException $e) {
    // Fehler-Logging
    error_log("Fehler beim Speichern: " . $e->getMessage());

    // Optional: Für Debug-Zwecke auch direkt anzeigen lassen
    // die("Fehler beim Speichern: " . $e->getMessage());

    return false;
}
}

/**
 * Ergebnisse abrufen
 * 
 * @param PDO $db Datenbank-Verbindung
 * @param string $id ID des Datensatzes
 * @return array|false Ergebnisse oder false wenn nicht gefunden
 */
function getResults($db, $id) {
    $sql = "SELECT * FROM sitemap_validations WHERE id = :id";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Ergebnisse: " . $e->getMessage());
        return false;
    }
}

// Datenbank initialisieren
$db = connectDB($config['db']);
createTablesIfNotExist($db);

// Hauptlogik basierend auf GET-Parametern
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if ($action === 'results' && !empty($id)) {
    // Ergebnisse anzeigen
    $results = getResults($db, $id);
    if ($results) {
        // JSON-Daten vor der Template-Einbindung dekodieren
        $validationResults = json_decode($results['validation_results'], true);
        $errors = json_decode($results['errors'], true);
        
        // Sitemap-Grade dekodieren, falls es ein String ist
        if (isset($results['sitemap_grade']) && is_string($results['sitemap_grade'])) {
            $results['sitemap_grade'] = json_decode($results['sitemap_grade'], true);
        }
        
        include 'results_template.php';
    } else {
        echo "Keine Ergebnisse für diese ID gefunden.";
    }
} elseif ($action === 'validate' && isset($_POST['sitemap_url'])) {
    // Prüfen, ob die Rate-Begrenzung überschritten wurde
    $clientIP = $_SERVER['REMOTE_ADDR'];
    if (!checkRateLimit($clientIP, $db, $config['validator']['rate_limit'])) {
        include 'rate_limit_template.php';
        exit;
    }
    
    // Sitemap-URL validieren
    $sitemapUrl = filter_var($_POST['sitemap_url'], FILTER_SANITIZE_URL);
    $urlValidation = validateSitemapUrl($sitemapUrl, $config);
    
    if ($urlValidation !== true) {
        // Ungültige URL
        include 'form_template.php';
        echo '<div class="alert alert-danger mt-3">' . $urlValidation . '</div>';
        exit;
    }
    
    // Sitemap validieren
    $id = generateRandomID();
    $results = validateSitemap($sitemapUrl, $cache_dir, $config, $id);
    
    // In Datenbank speichern
    if (saveResults($db, $id, $results)) {
        // Weiterleitung zur Ergebnisseite
        header("Location: ?action=results&id=$id");
        exit;
    } else {
        // Fehler beim Speichern
        include 'form_template.php';
        echo '<div class="alert alert-danger mt-3">Fehler beim Speichern der Ergebnisse. Bitte versuchen Sie es später erneut.</div>';
        exit;
    }
} else {
    // Eingabeformular anzeigen
    include 'form_template.php';
}