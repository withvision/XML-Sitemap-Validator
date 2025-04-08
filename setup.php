<?php
/**
 * XML Sitemap Validator - Setup Script
 *
 * Ein Tool zur Überprüfung von sitemap.xml Dateien nach gängigen SEO Standards
 * PHP 7.4+ & MySQL/MariaDB [PDO, cURL, SimpleXML, JSON, mbstring]
 *
 * @author   Simon Pokorny <coding@dlx-media.com>
 * @github	 https://github.com/withvision/XML-Sitemap-Validator
 * @web		 https://www.simon-pokorny.com
 *
 * Dieses Skript hilft bei der Ersteinrichtung des Sitemap Validators:
 * - Prüft die PHP-Umgebung auf erforderliche Erweiterungen
 * - Sammelt Datenbankverbindungsinformationen
 * - Testet die Datenbankverbindung
 * - Erstellt oder aktualisiert die benötigten Tabellen
 * - Generiert die Konfigurationsdatei
 * - Erstellt das Cache-Verzeichnis mit Schutzdateien
 */

// Fehlermeldungen aktivieren für das Setup
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sicherheitsheader
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self' https://cdnjs.cloudflare.com; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; script-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com");

// Überprüfen, ob bereits eine erfolgreiche Installation stattgefunden hat
$lockFile = __DIR__ . '/.setup_completed';
$forceContinue = isset($_GET['force']) && $_GET['force'] === 'true';

if (file_exists($lockFile) && !$forceContinue) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup bereits abgeschlossen</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container my-5">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h1 class="h4 mb-0">Setup bereits durchgeführt</h1>
                </div>
                <div class="card-body">
                    <p>Die Installation wurde bereits erfolgreich abgeschlossen. Aus Sicherheitsgründen wurde diese Datei gesperrt.</p>
                    <p>Wenn Sie das Setup erneut durchführen möchten, haben Sie folgende Möglichkeiten:</p>
                    <ul>
                        <li>Die Datei <code>.setup_completed</code> im Hauptverzeichnis löschen</li>
                        <li><a href="setup.php?force=true">Mit diesem Link forciert fortfahren</a> (nicht empfohlen, wenn dies ein Produktivsystem ist)</li>
                    </ul>
                    <p class="mt-4"><a href="index.php" class="btn btn-primary">Zurück zur Startseite</a></p>
                </div>
                <div class="card-footer text-muted">
                    <small>Aus Sicherheitsgründen empfehlen wir, die setup.php zu löschen, wenn sie nicht mehr benötigt wird.</small>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

// Status und Meldungen speichern
$status = [
    'environment_check' => false,
    'db_connection' => false,
    'db_tables' => false,
    'config_file' => false,
    'cache_dir' => false,
    'htaccess' => false,
    'setup_complete' => false
];
$messages = [];

// Funktion zum Hinzufügen von Meldungen
function addMessage($type, $message) {
    global $messages;
    $messages[] = ['type' => $type, 'message' => $message];
}

// Funktion zum sicheren Escapen von Werten für PHP-Konfigurationsdateien
function escapePHPValue($value) {
    if (is_string($value)) {
        return "'" . str_replace("'", "\\'", $value) . "'";
    } elseif (is_bool($value)) {
        return $value ? 'true' : 'false';
    } elseif (is_null($value)) {
        return 'null';
    } elseif (is_numeric($value)) {
        return $value;
    } else {
        return "'" . str_replace("'", "\\'", print_r($value, true)) . "'";
    }
}

// Funktion zum Überprüfen der PHP-Umgebung
function checkEnvironment() {
    $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'libxml', 'simplexml', 'mbstring'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    if (!empty($missingExtensions)) {
        addMessage('danger', 'Fehlende PHP-Erweiterungen: ' . implode(', ', $missingExtensions));
        return false;
    }
    
    // PHP-Version überprüfen
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        addMessage('warning', 'Ihre PHP-Version (' . PHP_VERSION . ') ist veraltet. Empfohlen wird PHP 7.4 oder höher.');
    } else {
        addMessage('success', 'PHP-Version: ' . PHP_VERSION);
    }
    
    // Überprüfen, ob die benötigten Verzeichnisse beschreibbar sind
    $writableDirectories = [__DIR__, __DIR__ . '/cache'];
    $nonWritableDirectories = [];
    
    foreach ($writableDirectories as $dir) {
        if (!file_exists($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                $nonWritableDirectories[] = $dir;
            }
        } elseif (!is_writable($dir)) {
            $nonWritableDirectories[] = $dir;
        }
    }
    
    if (!empty($nonWritableDirectories)) {
        addMessage('danger', 'Folgende Verzeichnisse sind nicht beschreibbar: ' . implode(', ', $nonWritableDirectories));
        return false;
    }
    
    addMessage('success', 'Alle Systemanforderungen erfüllt.');
    return true;
}

// Funktion zum Erstellen oder Aktualisieren der Tabellen
function createOrUpdateTables($db) {
    try {
        // Überprüfen, ob die Tabellen bereits existieren
        $tablesExist = false;
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE 'sitemap_validations'");
            $stmt->execute();
            $tablesExist = ($stmt->rowCount() > 0);
        } catch (PDOException $e) {
            // Fehler ignorieren, tablesExist bleibt false
        }
        
        if ($tablesExist) {
            // Überprüfen, ob Spalten existieren und ggf. hinzufügen
            $missingColumns = [];
            $requiredColumns = [
                'content_encoding_header' => "ALTER TABLE sitemap_validations ADD COLUMN content_encoding_header VARCHAR(50) DEFAULT NULL"
            ];
            
            foreach ($requiredColumns as $column => $alterSql) {
                try {
                    $stmt = $db->prepare("SHOW COLUMNS FROM sitemap_validations LIKE '{$column}'");
                    $stmt->execute();
                    if ($stmt->rowCount() === 0) {
                        $db->exec($alterSql);
                        addMessage('info', "Spalte '{$column}' zur Tabelle 'sitemap_validations' hinzugefügt.");
                    }
                } catch (PDOException $e) {
                    $missingColumns[] = $column;
                }
            }
            
            if (!empty($missingColumns)) {
                addMessage('warning', "Folgende Spalten konnten nicht hinzugefügt werden: " . implode(', ', $missingColumns));
            } else {
                addMessage('success', "Existierende Tabellen erfolgreich aktualisiert.");
                return true;
            }
        }
        
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
            content_encoding_header VARCHAR(50) DEFAULT NULL,
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
        
        $db->exec($sql);
        $db->exec($sql2);
        addMessage('success', "Datenbanktabellen erfolgreich erstellt.");
        return true;
    } catch (PDOException $e) {
        addMessage('danger', "Fehler beim Erstellen der Tabellen: " . $e->getMessage());
        return false;
    }
}

// Funktion zum Generieren der Konfigurationsdatei
function generateConfigFile($config) {
    $configContent = "<?php
/**
 * Sitemap Validator - Konfigurationsdatei
 * 
 * Diese Datei wurde automatisch durch setup.php generiert
 * Erstellt am: " . date('Y-m-d H:i:s') . "
 */

return [
    // Datenbankverbindung
    'db' => [
        'host' => " . escapePHPValue($config['db_host']) . ",
        'username' => " . escapePHPValue($config['db_user']) . ",
        'password' => " . escapePHPValue($config['db_pass']) . ",
        'database' => " . escapePHPValue($config['db_name']) . "
    ],
    
    // Validator-Einstellungen
    'validator' => [
        // Cache für Sitemaps (für Debugging) aktivieren/deaktivieren
        'enable_cache' => " . ($config['enable_cache'] ? 'true' : 'false') . ",
        
        // Benutzerdefinierter User-Agent für alle HTTP-Anfragen
        'user_agent' => " . escapePHPValue($config['user_agent']) . ",
        
        // Anzahl der URL-Stichproben (zufällige URLs aus der Sitemap)
        'sample_urls_count' => " . (int)$config['sample_urls_count'] . ",
        
        // Timeout für HTTP-Anfragen in Sekunden
        'http_timeout' => " . (int)$config['http_timeout'] . ",
        
        // Ratenbegrenzung (Anfragen pro Stunde pro IP)
        'rate_limit' => " . (int)$config['rate_limit'] . "
    ],
    
    // Sicherheitseinstellungen
    'security' => [
        // Liste erlaubter Hostnamen (leer lassen für keine Einschränkung)
        'allowed_hosts' => [" . (!empty($config['allowed_hosts']) ? 
            implode(", ", array_map(function($host) { return escapePHPValue(trim($host)); }, 
            explode(',', $config['allowed_hosts']))) : "") . "],
        
        // Maximale Größe der Sitemap-Datei in Bytes (150 MB)
        'max_filesize' => " . (int)$config['max_filesize'] . ",
        
        // Auf SSL-Zertifikate prüfen
        'verify_ssl' => " . ($config['verify_ssl'] ? 'true' : 'false') . "
    ]
];";

    if (file_put_contents(__DIR__ . '/config.php', $configContent)) {
        addMessage('success', "Konfigurationsdatei erfolgreich erstellt.");
        return true;
    } else {
        addMessage('danger', "Fehler beim Erstellen der Konfigurationsdatei.");
        return false;
    }
}

// Funktion zum Erstellen der .htaccess-Datei
function createHtaccessFiles() {
    $success = true;
    
    // Schutz für das Hauptverzeichnis (config.php und .setup_completed)
    $rootHtaccess = <<<EOT
# Zugriff auf sensible Dateien verhindern
<Files ~ "^(config\.php|\.setup_completed)$">
    Order allow,deny
    Deny from all
</Files>

# Sicherheitsheader setzen
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set X-XSS-Protection "1; mode=block"
    Header set Content-Security-Policy "default-src 'self' https://cdnjs.cloudflare.com; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; script-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;"
</IfModule>

# Verzeichnislisting deaktivieren
Options -Indexes
EOT;

    // Schutz für das Cache-Verzeichnis
    $cacheHtaccess = <<<EOT
# Zugriff auf Cache-Verzeichnis komplett verhindern
Order deny,allow
Deny from all
EOT;

    if (!file_put_contents(__DIR__ . '/.htaccess', $rootHtaccess)) {
        addMessage('warning', "Konnte .htaccess im Hauptverzeichnis nicht erstellen. Der Zugriff auf sensible Dateien ist möglicherweise nicht geschützt.");
        $success = false;
    }
    
    if (!file_exists(__DIR__ . '/cache')) {
        if (!mkdir(__DIR__ . '/cache', 0755, true)) {
            addMessage('warning', "Konnte Cache-Verzeichnis nicht erstellen.");
            $success = false;
        }
    }
    
    if (!file_put_contents(__DIR__ . '/cache/.htaccess', $cacheHtaccess)) {
        addMessage('warning', "Konnte .htaccess im Cache-Verzeichnis nicht erstellen. Der Cache ist möglicherweise ungeschützt.");
        $success = false;
    }
    
    // Leere index.php im Cache-Verzeichnis erstellen
    $indexContent = "<?php\n// Verhindert das Auflisten des Verzeichnisinhalts\nheader('Location: ../index.php');\nexit;\n";
    if (!file_put_contents(__DIR__ . '/cache/index.php', $indexContent)) {
        addMessage('warning', "Konnte index.php im Cache-Verzeichnis nicht erstellen.");
        $success = false;
    }
    
    if ($success) {
        addMessage('success', "Schutz- und Sicherheitsmaßnahmen erfolgreich umgesetzt.");
    }
    
    return $success;
}

// Funktion zum Erstellen einer Sperrdatei, die verhindert, dass setup.php erneut ausgeführt wird
function createSetupLockFile() {
    $lockContent = "# Diese Datei wurde automatisch erstellt, um erneute Setup-Ausführungen zu verhindern.\n";
    $lockContent .= "# Datum: " . date('Y-m-d H:i:s') . "\n";
    $lockContent .= "# Löschen Sie diese Datei, wenn Sie das Setup erneut ausführen möchten.\n";
    
    if (file_put_contents(__DIR__ . '/.setup_completed', $lockContent)) {
        addMessage('success', "Setup-Sperrdatei erstellt. Weitere unbeabsichtigte Ausführungen werden verhindert.");
        return true;
    } else {
        addMessage('warning', "Konnte Setup-Sperrdatei nicht erstellen. Erneute Ausführungen sind möglich.");
        return false;
    }
}

// Hauptlogik des Setups
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] === 'environment') {
        // Umgebungsprüfung
        $status['environment_check'] = checkEnvironment();
    } elseif (isset($_POST['step']) && $_POST['step'] === 'database') {
        // Schritt 1: Datenbankverbindung testen und Tabellen erstellen
        try {
            $db = new PDO(
                "mysql:host={$_POST['db_host']};dbname={$_POST['db_name']};charset=utf8mb4",
                $_POST['db_user'],
                $_POST['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            $status['db_connection'] = true;
            $status['environment_check'] = true; // Wenn DB-Verbindung klappt, gilt die Umgebung als ok
            addMessage('success', "Datenbankverbindung erfolgreich hergestellt.");
            
            // Tabellen erstellen oder aktualisieren
            if (createOrUpdateTables($db)) {
                $status['db_tables'] = true;
            }
        } catch (PDOException $e) {
            addMessage('danger', "Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    } elseif (isset($_POST['step']) && $_POST['step'] === 'config') {
        // Schritt 2: Konfigurationsdatei erstellen
        $config = [
            'db_host' => $_POST['db_host'],
            'db_user' => $_POST['db_user'],
            'db_pass' => $_POST['db_pass'],
            'db_name' => $_POST['db_name'],
            'enable_cache' => isset($_POST['enable_cache']),
            'user_agent' => $_POST['user_agent'],
            'sample_urls_count' => $_POST['sample_urls_count'],
            'http_timeout' => $_POST['http_timeout'],
            'rate_limit' => $_POST['rate_limit'],
            'allowed_hosts' => $_POST['allowed_hosts'],
            'max_filesize' => $_POST['max_filesize'] * 1024 * 1024, // MB zu Bytes
            'verify_ssl' => isset($_POST['verify_ssl'])
        ];
        
        $status['environment_check'] = true; // Vorhergehende Schritte als ok betrachten
        $status['db_connection'] = true;
        $status['db_tables'] = true;
        
        if (generateConfigFile($config)) {
            $status['config_file'] = true;
            
            // .htaccess-Dateien erstellen
            if (createHtaccessFiles()) {
                $status['htaccess'] = true;
                $status['cache_dir'] = true;
            }
            
            // Setup abgeschlossen, wenn alle Schritte erfolgreich waren
            if ($status['db_connection'] && $status['db_tables'] && $status['config_file'] && $status['cache_dir']) {
                $status['setup_complete'] = true;
                
                // Sperrdatei erstellen
                createSetupLockFile();
                
                addMessage('success', "Setup erfolgreich abgeschlossen!");
            }
        }
    }
}

// Wenn keine POST-Anfrage, prüfen wir zuerst die Umgebung
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$status['environment_check']) {
    $status['environment_check'] = checkEnvironment();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitemap Validator - Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .step-indicator {
            display: flex;
            margin-bottom: 2rem;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
            color: #6c757d;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -50%;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            z-index: 1;
        }
        .step:last-child::after {
            display: none;
        }
        .step .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 2;
        }
        .step.active {
            color: #212529;
            font-weight: bold;
        }
        .step.active .step-number {
            background-color: #007bff;
            color: white;
        }
        .step.completed {
            color: #28a745;
        }
        .step.completed .step-number {
            background-color: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h4 mb-0">Sitemap Validator - Setup</h1>
                    </div>
                    <div class="card-body">
                        <!-- Setup-Fortschrittsanzeige -->
                        <div class="step-indicator">
                            <div class="step <?php echo $status['environment_check'] ? 'completed' : 'active'; ?>">
                                <div class="step-number"><?php echo $status['environment_check'] ? '<i class="fas fa-check"></i>' : '1'; ?></div>
                                <span>Umgebung</span>
                            </div>
                            <div class="step <?php echo ($status['db_connection'] && $status['db_tables']) ? 'completed' : (($status['environment_check'] && !$status['db_connection']) ? 'active' : ''); ?>">
                                <div class="step-number"><?php echo ($status['db_connection'] && $status['db_tables']) ? '<i class="fas fa-check"></i>' : '2'; ?></div>
                                <span>Datenbank</span>
                            </div>
                            <div class="step <?php echo $status['config_file'] ? 'completed' : (($status['db_tables'] && !$status['config_file']) ? 'active' : ''); ?>">
                                <div class="step-number"><?php echo $status['config_file'] ? '<i class="fas fa-check"></i>' : '3'; ?></div>
                                <span>Konfiguration</span>
                            </div>
                            <div class="step <?php echo $status['setup_complete'] ? 'completed' : ''; ?>">
                                <div class="step-number"><?php echo $status['setup_complete'] ? '<i class="fas fa-check"></i>' : '4'; ?></div>
                                <span>Fertigstellung</span>
                            </div>
                        </div>

                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
                                    <?php echo $message['message']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if ($status['setup_complete']): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                                <h2>Setup abgeschlossen!</h2>
                                <p class="lead">Sitemap Validator wurde erfolgreich eingerichtet.</p>
                                <div class="alert alert-warning">
                                    <strong>Wichtig:</strong> Aus Sicherheitsgründen sollten Sie diese setup.php-Datei jetzt löschen.
                                </div>
                                <a href="index.php" class="btn btn-primary btn-lg mt-3">
                                    <i class="fas fa-home"></i> Zur Startseite
                                </a>
                            </div>
                        <?php elseif (!$status['environment_check']): ?>
                            <!-- Schritt 0: Umgebungsprüfung -->
                            <h2 class="mb-4">Systemvoraussetzungen prüfen</h2>
                            <form method="post" action="">
                                <input type="hidden" name="step" value="environment">
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Das Setup überprüft nun die Systemvoraussetzungen für den Sitemap Validator.
                                </div>
                                
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">Systemvoraussetzungen prüfen</button>
                                </div>
                            </form>
                        <?php elseif (!$status['db_connection'] || !$status['db_tables']): ?>
                            <!-- Schritt 1: Datenbankverbindung -->
                            <h2 class="mb-4">Datenbank-Konfiguration</h2>
                            <form method="post" action="">
                                <input type="hidden" name="step" value="database">
                                
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Datenbank-Host:</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo $_POST['db_host'] ?? 'localhost'; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Datenbank-Name:</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" value="<?php echo $_POST['db_name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Datenbank-Benutzer:</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo $_POST['db_user'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Datenbank-Passwort:</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo $_POST['db_pass'] ?? ''; ?>" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Datenbank testen & Tabellen erstellen</button>
                                </div>
                            </form>
                        <?php elseif (!$status['config_file'] || !$status['cache_dir']): ?>
<!-- Schritt 2: Weitere Konfiguration -->
<h2 class="mb-4">Anwendungskonfiguration</h2>
                            <form method="post" action="">
                                <input type="hidden" name="step" value="config">
                                
                                <!-- Datenbank-Informationen übernehmen -->
                                <input type="hidden" name="db_host" value="<?php echo $_POST['db_host']; ?>">
                                <input type="hidden" name="db_user" value="<?php echo $_POST['db_user']; ?>">
                                <input type="hidden" name="db_pass" value="<?php echo $_POST['db_pass']; ?>">
                                <input type="hidden" name="db_name" value="<?php echo $_POST['db_name']; ?>">
                                
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Validator-Einstellungen</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="enable_cache" name="enable_cache" checked>
                                            <label class="form-check-label" for="enable_cache">
                                                Cache für Sitemaps aktivieren
                                            </label>
                                            <div class="form-text">
                                                Speichert Sitemaps im Cache-Verzeichnis für Debugging-Zwecke.
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="user_agent" class="form-label">User-Agent:</label>
                                            <input type="text" class="form-control" id="user_agent" name="user_agent" value="XML Sitemap Validator (https://github.com/withvision/XML-Sitemap-Validator)">
                                            <div class="form-text">
                                                Der User-Agent wird bei HTTP-Anfragen verwendet.
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="sample_urls_count" class="form-label">URL-Stichproben:</label>
                                                <input type="number" class="form-control" id="sample_urls_count" name="sample_urls_count" value="5" min="1" max="20">
                                                <div class="form-text">
                                                    Anzahl der zu prüfenden URLs.
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label for="http_timeout" class="form-label">HTTP-Timeout (s):</label>
                                                <input type="number" class="form-control" id="http_timeout" name="http_timeout" value="10" min="1" max="60">
                                                <div class="form-text">
                                                    Timeout für HTTP-Anfragen.
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label for="rate_limit" class="form-label">Rate-Limit (pro Std):</label>
                                                <input type="number" class="form-control" id="rate_limit" name="rate_limit" value="20" min="1" max="200">
                                                <div class="form-text">
                                                    Max. Anfragen pro IP pro Stunde.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Sicherheitseinstellungen</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="allowed_hosts" class="form-label">Erlaubte Hosts (optional):</label>
                                            <input type="text" class="form-control" id="allowed_hosts" name="allowed_hosts" value="">
                                            <div class="form-text">
                                                Kommagetrennte Liste von erlaubten Domains (z.B. example.com,example.org). Leer lassen, um keine Einschränkungen zu setzen.
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="max_filesize" class="form-label">Max. Dateigröße (MB):</label>
                                            <input type="number" class="form-control" id="max_filesize" name="max_filesize" value="150" min="1" max="500">
                                            <div class="form-text">
                                                Maximale Größe der zu verarbeitenden Sitemap-Dateien.
                                            </div>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="verify_ssl" name="verify_ssl" checked>
                                            <label class="form-check-label" for="verify_ssl">
                                                SSL-Zertifikate überprüfen
                                            </label>
                                            <div class="form-text">
                                                Deaktivieren Sie diese Option nur, wenn Sie selbstsignierte Zertifikate verwenden.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Konfiguration speichern & Setup abschließen</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        <div class="row">
                            <div class="col">Sitemap Validator Setup</div>
                            <div class="col text-end">Version 1.0</div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        &copy; <?php echo date('Y'); ?> by <a href="https://dlx-media.com">DLx-Media.com</a> - Sitemap Validator | 
                        <a href="https://github.com/withvision/XML-Sitemap-Validator" target="_blank" class="text-muted">
                            <i class="fab fa-github"></i> GitHub
                        </a>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Passwort-Sichtbarkeit umschalten
        document.addEventListener('DOMContentLoaded', function() {
            const togglePasswordButton = document.getElementById('toggle-password');
            if (togglePasswordButton) {
                togglePasswordButton.addEventListener('click', function() {
                    const passwordInput = document.getElementById('db_pass');
                    const icon = this.querySelector('i');
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
        });
    </script>
</body>
</html>