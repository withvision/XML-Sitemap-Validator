<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sitemap Validator - Ergebnisse der Sitemap-Analyse">
    <meta name="robots" content="noindex, nofollow">
    <title>XML Sitemap Validator - Ergebnisse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h1 class="h4 mb-0">XML Sitemap Validator - Ergebnisse</h1>
                        <div>
                            <a href="javascript:window.print();" class="btn btn-outline-light btn-sm me-2" title="Drucken">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="index.php" class="btn btn-outline-light btn-sm">Neue Überprüfung</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <strong>Validierungs-ID:</strong> <?php echo htmlspecialchars($id); ?>
                                </span>
                                <button class="btn btn-sm btn-outline-primary copy-link" data-bs-toggle="tooltip" 
                                        data-bs-placement="top" title="Link kopieren">
                                    <i class="fas fa-link"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <small>Teilen Sie diesen Link, um die Ergebnisse zu teilen:</small><br>
                                <small class="text-muted share-url"><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?action=results&id=$id"); ?></small>
                            </div>
                        </div>

                        <h5 class="mt-4">Analysierte Sitemap</h5>
                    <p>
                        <a href="<?php echo htmlspecialchars($results['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo htmlspecialchars($results['url']); ?>
                            <i class="fas fa-external-link-alt ms-1 small"></i>
                        </a>
                    </p>
                    <p><small class="text-muted">Überprüft am: <?php echo htmlspecialchars($results['date_checked']); ?></small></p>

                    <!-- Neue Score-Karte für die Qualitätsbewertung -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-<?php 
                                    if (!$results['valid_xml'] || !$results['valid_root_element']) {
                                        echo 'danger';
                                    } else {
                                        echo $results['sitemap_grade']['color'] ?? 'primary'; 
                                    }
                                ?>">
                                    <h5 class="text-white mb-0">
                                        <?php if (!$results['valid_xml'] || !$results['valid_root_element']): ?>
                                            Keine gültige Sitemap
                                        <?php else: ?>
                                            Sitemap-Qualitätsbewertung
                                        <?php endif; ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!$results['valid_xml'] || !$results['valid_root_element']): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Die überprüfte URL enthält keine gültige Sitemap!</strong>
                                            <?php if (!$results['valid_xml']): ?>
                                                <p class="mb-0 mt-2">Es wurde keine gültige XML-Datei gefunden. Prüfen Sie die URL und stellen Sie sicher, dass sie zu einer Sitemap-Datei führt.</p>
                                            <?php elseif (!$results['valid_root_element']): ?>
                                                <p class="mb-0 mt-2">Die XML-Datei hat nicht die erforderliche Sitemap-Struktur. Eine Sitemap muss ein root-Element &lt;urlset&gt; oder &lt;sitemapindex&gt; mit dem korrekten Namespace haben.</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="row align-items-center">
                                            <div class="col-md-3 text-center">
                                                <div class="display-1 fw-bold text-<?php echo $results['sitemap_grade']['color'] ?? 'primary'; ?>">
                                                    <?php echo $results['sitemap_grade']['grade'] ?? '?'; ?>
                                                </div>
                                                <div class="h5 text-muted"><?php echo $results['sitemap_grade']['text'] ?? 'Nicht bewertet'; ?></div>
                                            </div>
                                            <div class="col-md-9">
                                                <h5>Gesamtqualität der Sitemap: <?php echo number_format($results['sitemap_score'] ?? 0, 1, ',', '.'); ?>%</h5>
                                                <div class="progress mb-3" style="height: 25px;">
                                                    <div class="progress-bar bg-<?php echo $results['sitemap_grade']['color'] ?? 'primary'; ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo $results['sitemap_score'] ?? 0; ?>%;" 
                                                        aria-valuenow="<?php echo $results['sitemap_score'] ?? 0; ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100">
                                                        <?php echo number_format($results['sitemap_score'] ?? 0, 1, ',', '.'); ?>%
                                                    </div>
                                                </div>
                                                <p class="mb-0">
                                                    Die Bewertung basiert auf technischen Faktoren, SEO-Best-Practices und der Erreichbarkeit der enthaltenen URLs.
                                                    <?php if (isset($results['sitemap_score']) && $results['sitemap_score'] < 70): ?>
                                                        <strong class="text-warning">Es wurden Probleme gefunden, die behoben werden sollten.</strong>
                                                    <?php elseif (isset($results['sitemap_score']) && $results['sitemap_score'] >= 90): ?>
                                                        <strong class="text-success">Sehr gute Sitemap! Alle wichtigen Kriterien sind erfüllt.</strong>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>Zusammenfassung</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>HTTP Status</th>
                                            <td class="<?php echo $results['http_status'] == 200 ? 'table-success' : 'table-danger'; ?>">
                                                <?php echo htmlspecialchars($results['http_status']); ?>
                                                <?php echo $results['http_status'] == 200 ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>MIME-Type</th>
                                            <td class="<?php echo (isset($results['valid_mime_type']) && $results['valid_mime_type']) ? 'table-success' : 'table-danger'; ?>">
                                                <?php 
                                                $contentType = $results['content_type'] ?? 'Unbekannt';
                                                $mimeTypeOnly = explode(';', $contentType)[0];
                                                echo htmlspecialchars($mimeTypeOnly); 
                                                ?>
                                                <?php echo (isset($results['valid_mime_type']) && $results['valid_mime_type']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Valides XML</th>
                                            <td class="<?php echo $results['valid_xml'] ? 'table-success' : 'table-danger'; ?>">
                                                <?php echo $results['valid_xml'] ? 'Ja' : 'Nein'; ?>
                                                <?php echo $results['valid_xml'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>UTF-8 Codierung</th>
                                            <td class="<?php echo $results['encoding_utf8'] ? 'table-success' : 'table-danger'; ?>">
                                                <?php echo $results['encoding_utf8'] ? 'Ja' : 'Nein'; ?>
                                                <?php echo $results['encoding_utf8'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Korrektes Root-Element</th>
                                            <td class="<?php echo $results['valid_root_element'] ? 'table-success' : 'table-danger'; ?>">
                                                <?php echo $results['valid_root_element'] ? 'Ja' : 'Nein'; ?>
                                                <?php echo $results['valid_root_element'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Ladezeit</th>
                                            <td class="<?php echo $results['load_time'] < 1 ? 'table-success' : ($results['load_time'] < 3 ? 'table-warning' : 'table-danger'); ?>">
                                                <?php echo htmlspecialchars($results['load_time']); ?> Sekunden
                                                <?php 
                                                    if ($results['load_time'] < 1) {
                                                        echo '<i class="fas fa-check-circle text-success"></i>';
                                                    } elseif ($results['load_time'] < 3) {
                                                        echo '<i class="fas fa-exclamation-circle text-warning"></i>';
                                                    } else {
                                                        echo '<i class="fas fa-times-circle text-danger"></i>';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                    <th>Komprimierung</th>
                                        <td>
                                            <?php if ($results['is_compressed']): ?>
                                                <span class="badge bg-success">Als .xml.gz gespeichert</span>
                                                <?php elseif (isset($results['http_compressed']) && $results['http_compressed']): ?>
                                                      <span class="badge bg-info">HTTP-Komprimierung</span>
                                                        <?php if (isset($results['content_encoding_header'])): ?>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($results['content_encoding_header']); ?>)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Nicht komprimiert</span>
                                        <?php endif; ?>
                                            <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" title="Komprimierte Sitemaps sind schneller zu übertragen"></i>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Inhaltsinformationen</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>Typ</th>
                                            <td class="<?php echo (!$results['valid_xml'] || !$results['valid_root_element']) ? 'table-danger' : ''; ?>">
                                                <?php 
                                                if (!$results['valid_xml']) {
                                                    echo '<span class="text-danger">Keine gültige XML-Datei</span>';
                                                } elseif (!$results['valid_root_element']) {
                                                    echo '<span class="text-danger">Keine gültige Sitemap-Struktur</span>';
                                                } else {
                                                    echo $results['is_sitemap_index'] ? 'Sitemap-Index' : 'Standard-Sitemap'; 
                                                }
                                                ?>
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                title="<?php echo (!$results['valid_xml'] || !$results['valid_root_element']) ? 'Diese Datei entspricht nicht dem Sitemap-Format' : ($results['is_sitemap_index'] ? 'Enthält Verweise auf andere Sitemap-Dateien' : 'Enthält direkte URL-Einträge'); ?>"></i>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Einträge</th>
                                            <td class="<?php echo $results['url_count'] <= 50000 ? 'table-success' : 'table-danger'; ?>">
                                                <?php echo htmlspecialchars(number_format($results['url_count'], 0, ',', '.')); ?>
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                   title="Google empfiehlt maximal 50.000 URLs pro Sitemap-Datei"></i>
                                                <?php if ($results['url_count'] > 50000): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning" data-bs-toggle="tooltip" 
                                                       title="Überschreitet das Limit von 50.000 Einträgen"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Eindeutige URLs</th>
                                            <td class="<?php echo $results['unique_url_count'] == $results['url_count'] ? 'table-success' : 'table-warning'; ?>">
                                                <?php echo htmlspecialchars(number_format($results['unique_url_count'], 0, ',', '.')); ?>
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                   title="Alle URLs in einer Sitemap sollten eindeutig sein"></i>
                                                <?php if ($results['unique_url_count'] < $results['url_count']): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning" data-bs-toggle="tooltip" 
                                                       title="Enthält <?php echo $results['url_count'] - $results['unique_url_count']; ?> doppelte URLs"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Dateigröße</th>
                                            <td class="<?php echo $results['filesize'] <= 52428800 ? 'table-success' : 'table-danger'; ?>">
                                                <?php 
                                                    $fileSizeMB = round($results['filesize'] / (1024 * 1024), 2);
                                                    echo htmlspecialchars($fileSizeMB) . ' MB'; 
                                                ?>
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                   title="Google empfiehlt maximal 50 MB pro Sitemap-Datei (unkomprimiert)"></i>
                                                <?php if ($results['filesize'] > 52428800): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning" data-bs-toggle="tooltip" 
                                                       title="Überschreitet das empfohlene Limit von 50 MB"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <!-- Verbesserte Optionale Elemente Tabelle mit grünem Hintergrund für vorhandene Elemente -->
                            <h5>Optionale Elemente</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>
                                                lastmod
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                title="Datum der letzten Änderung einer URL"></i>
                                            </th>
                                            <td class="<?php echo $results['has_lastmod'] ? ($results['has_invalid_lastmod'] ?? false ? 'table-warning' : 'table-success') : ''; ?>">
                                                <?php if($results['has_lastmod']): ?>
                                                    Vorhanden 
                                                    <?php if(isset($results['has_invalid_lastmod']) && $results['has_invalid_lastmod']): ?>
                                                        <i class="fas fa-exclamation-circle text-warning"></i>
                                                        <span class="text-danger">Mit Fehlern!</span>
                                                    <?php else: ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Nicht vorhanden
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                changefreq
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                title="Änderungshäufigkeit einer URL (z.B. daily, weekly)"></i>
                                            </th>
                                            <td class="<?php echo $results['has_changefreq'] ? ($results['has_invalid_changefreq'] ?? false ? 'table-warning' : 'table-success') : ''; ?>">
                                                <?php if($results['has_changefreq']): ?>
                                                    Vorhanden 
                                                    <?php if(isset($results['has_invalid_changefreq']) && $results['has_invalid_changefreq']): ?>
                                                        <i class="fas fa-exclamation-circle text-warning"></i>
                                                        <span class="text-danger">Mit Fehlern!</span>
                                                    <?php else: ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Nicht vorhanden
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                priority
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                title="Priorität einer URL (0.0 bis 1.0)"></i>
                                            </th>
                                            <td class="<?php echo $results['has_priority'] ? ($results['has_invalid_priority'] ?? false ? 'table-warning' : 'table-success') : ''; ?>">
                                                <?php if($results['has_priority']): ?>
                                                    Vorhanden 
                                                    <?php if(isset($results['has_invalid_priority']) && $results['has_invalid_priority']): ?>
                                                        <i class="fas fa-exclamation-circle text-warning"></i>
                                                        <span class="text-danger">Mit Fehlern!</span>
                                                    <?php else: ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Nicht vorhanden
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($results['has_lastmod'] || $results['has_changefreq'] || $results['has_priority']): ?>
                                <div class="alert alert-info mt-3 small">
                                    <i class="fas fa-info-circle"></i> <strong>Hinweis zu Attributwerten:</strong>
                                    <ul class="mb-0">
                                        <?php if ($results['has_lastmod']): ?>
                                            <li>
                                                <strong>lastmod:</strong> 
                                                <?php if (isset($results['has_invalid_lastmod']) && $results['has_invalid_lastmod']): ?>
                                                    <span class="text-danger">Es wurden ungültige lastmod-Datumsformate gefunden!</span>
                                                    Das Format sollte ISO 8601 entsprechen (YYYY-MM-DD).
                                                <?php else: ?>
                                                    Alle lastmod-Attribute haben ein gültiges Format.
                                                <?php endif; ?>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($results['has_changefreq']): ?>
                                            <li>
                                                <strong>changefreq:</strong> 
                                                <?php if (isset($results['has_invalid_changefreq']) && $results['has_invalid_changefreq']): ?>
                                                    <span class="text-danger">Es wurden ungültige changefreq-Werte gefunden!</span>
                                                    Erlaubte Werte sind: always, hourly, daily, weekly, monthly, yearly, never.
                                                <?php else: ?>
                                                    Alle changefreq-Attribute haben gültige Werte.
                                                <?php endif; ?>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($results['has_priority']): ?>
                                            <li>
                                                <strong>priority:</strong> 
                                                <?php if (isset($results['has_invalid_priority']) && $results['has_invalid_priority']): ?>
                                                    <span class="text-danger">Es wurden ungültige priority-Werte gefunden!</span>
                                                    Die Werte müssen zwischen 0.0 und 1.0 liegen.
                                                <?php else: ?>
                                                    Alle priority-Attribute haben gültige Werte (zwischen 0.0 und 1.0).
                                                <?php endif; ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <h5 class="mt-4">Robots.txt Integration</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>Robots.txt zugänglich</th>
                                            <td class="<?php echo $results['robots_txt_accessible'] ? 'table-success' : 'table-danger'; ?>">
                                                <?php echo $results['robots_txt_accessible'] ? 'Ja' : 'Nein'; ?>
                                                <?php echo $results['robots_txt_accessible'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                Sitemap in Robots.txt
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                   title="Die Sitemap sollte in der robots.txt mit 'Sitemap:' eingetragen sein"></i>
                                            </th>
                                            <td class="<?php echo $results['robots_txt_reference'] ? 'table-success' : 'table-warning'; ?>">
                                                <?php echo $results['robots_txt_reference'] ? 'Ja' : 'Nein'; ?>
                                                <?php echo $results['robots_txt_reference'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-exclamation-circle text-warning"></i>'; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- Verbesserte Sitemap-Erweiterungen Tabelle mit Zählwerten -->
                            <h5>Sitemap-Erweiterungen</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>
                                                Image-Erweiterung
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                title="Zusätzliche Informationen für Bilder (Google Images)"></i>
                                            </th>
                                            <td class="<?php echo $results['has_image_extension'] ? 'table-success' : ''; ?>">
                                                <?php if($results['has_image_extension']): ?>
                                                    Vorhanden <i class="fas fa-check-circle text-success"></i>
                                                    <span class="badge bg-primary ms-2" data-bs-toggle="tooltip" title="Anzahl der Image-Erweiterungen">
                                                        <?php echo number_format($results['image_extension_count'] ?? 0, 0, ',', '.'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    Nicht vorhanden
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                Video-Erweiterung
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                title="Zusätzliche Informationen für Videos (Google Video)"></i>
                                            </th>
                                            <td class="<?php echo $results['has_video_extension'] ? 'table-success' : ''; ?>">
                                                <?php if($results['has_video_extension']): ?>
                                                    Vorhanden <i class="fas fa-check-circle text-success"></i>
                                                    <span class="badge bg-primary ms-2" data-bs-toggle="tooltip" title="Anzahl der Video-Erweiterungen">
                                                        <?php echo number_format($results['video_extension_count'] ?? 0, 0, ',', '.'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    Nicht vorhanden
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                News-Erweiterung
                                                <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                title="Zusätzliche Informationen für News-Artikel (Google News)"></i>
                                            </th>
                                            <td class="<?php echo $results['has_news_extension'] ? 'table-success' : ''; ?>">
                                                    <?php if($results['has_news_extension']): ?>
                                                        Vorhanden <i class="fas fa-check-circle text-success"></i>
                                                        <span class="badge bg-primary ms-2" data-bs-toggle="tooltip" title="Anzahl der News-Erweiterungen">
                                                            <?php echo number_format($results['news_extension_count'] ?? 0, 0, ',', '.'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        Nicht vorhanden
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>
                                                    Mobile-Erweiterung
                                                    <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                    title="Kennzeichnung für mobile Inhalte"></i>
                                                </th>
                                                <td class="<?php echo $results['has_mobile_extension'] ? 'table-success' : ''; ?>">
                                                    <?php if($results['has_mobile_extension']): ?>
                                                        Vorhanden <i class="fas fa-check-circle text-success"></i>
                                                        <span class="badge bg-primary ms-2" data-bs-toggle="tooltip" title="Anzahl der Mobile-Erweiterungen">
                                                            <?php echo number_format($results['mobile_extension_count'] ?? 0, 0, ',', '.'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        Nicht vorhanden
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>
                                                    Hreflang/Alternate
                                                    <i class="fas fa-info-circle text-info" data-bs-toggle="tooltip" 
                                                    title="Sprachvarianten einer URL (für internationale Websites)"></i>
                                                </th>
                                                <td class="<?php echo $results['has_alternate_links'] ? 'table-success' : ''; ?>">
                                                    <?php if($results['has_alternate_links']): ?>
                                                        Vorhanden <i class="fas fa-check-circle text-success"></i>
                                                        <span class="badge bg-primary ms-2" data-bs-toggle="tooltip" title="Anzahl der Hreflang/Alternate Links">
                                                            <?php echo number_format($results['alternate_links_count'] ?? 0, 0, ',', '.'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        Nicht vorhanden
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Verbesserte Fehler und Warnungen Anzeige -->
                            <div class="col-12 mt-4">
                                <h5>Sitemap-Probleme</h5>
                                <?php 
                                $sitemapIssues = categorizeSitemapIssues($results);
                                if (empty($sitemapIssues['critical']) && empty($sitemapIssues['warnings']) && empty($sitemapIssues['info'])): 
                                ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> Keine Probleme gefunden - Perfekt!
                                    </div>
                                <?php else: ?>
                                    <?php if (!empty($sitemapIssues['critical'])): ?>
                                        <div class="alert alert-danger">
                                            <h6><i class="fas fa-exclamation-triangle"></i> Kritische Fehler</h6>
                                            <ul class="mb-0">
                                                <?php foreach ($sitemapIssues['critical'] as $issue): ?>
                                                    <li>
                                                        <strong class="text-danger">FEHLER:</strong> 
                                                        <?php echo htmlspecialchars($issue); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sitemapIssues['warnings'])): ?>
                                        <div class="alert alert-warning">
                                            <h6><i class="fas fa-exclamation-circle"></i> Warnungen</h6>
                                            <ul class="mb-0">
                                                <?php foreach ($sitemapIssues['warnings'] as $issue): ?>
                                                    <li><?php echo htmlspecialchars($issue); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            
                                            <?php if (!empty($sitemapIssues['url_samples'])): ?>
                                                <div class="mt-2 small">
                                                    <strong>Problem-URLs (Stichprobe):</strong>
                                                    <ul>
                                                        <?php foreach (array_slice($sitemapIssues['url_samples'], 0, 3) as $urlIssue): ?>
                                                            <li>
                                                                <a href="<?php echo htmlspecialchars($urlIssue['url']); ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 400px;">
                                                                    <?php echo htmlspecialchars($urlIssue['url']); ?>
                                                                </a>: 
                                                                <?php echo htmlspecialchars($urlIssue['issue']); ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                        <?php if (count($sitemapIssues['url_samples']) > 3): ?>
                                                            <li>... und <?php echo count($sitemapIssues['url_samples']) - 3; ?> weitere</li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sitemapIssues['info'])): ?>
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle"></i> Optimierungspotenzial</h6>
                                            <ul class="mb-0">
                                                <?php foreach ($sitemapIssues['info'] as $issue): ?>
                                                    <li><?php echo htmlspecialchars($issue); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                            $urlSampleStatus = is_string($results['url_sample_status']) ? 
                                                json_decode($results['url_sample_status'], true) : 
                                                $results['url_sample_status'];
                                                
                            if (!empty($urlSampleStatus) && is_array($urlSampleStatus)): 
                            ?>
                            <div class="col-12 mt-4">
                                <h5>URL-Stichproben (<?php echo count($urlSampleStatus); ?> zufällige URLs)</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>URL</th>
                                                <th>HTTP-Status</th>
                                                <th>Robots.txt</th>
                                                <th>Meta Noindex</th>
                                                <th>HTTP Noindex</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($urlSampleStatus as $urlStatus): ?>
                                                <tr>
                                                    <td class="small text-truncate" style="max-width: 200px;">
                                                    <a href="<?php echo htmlspecialchars($urlStatus['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($urlStatus['url']); ?>">
                                                            <?php echo htmlspecialchars($urlStatus['url']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="<?php echo $urlStatus['http_status'] == 200 ? 'table-success' : 'table-danger'; ?>">
                                                        <?php echo $urlStatus['http_status']; ?>
                                                    </td>
                                                    <td class="<?php echo isset($urlStatus['blocked_by_robots']) && $urlStatus['blocked_by_robots'] ? 'table-danger' : 'table-success'; ?>">
                                                        <?php echo isset($urlStatus['blocked_by_robots']) && $urlStatus['blocked_by_robots'] ? 'Blockiert' : 'Erlaubt'; ?>
                                                    </td>
                                                    <td class="<?php echo isset($urlStatus['has_noindex_meta']) && $urlStatus['has_noindex_meta'] ? 'table-danger' : 'table-success'; ?>">
                                                        <?php echo isset($urlStatus['has_noindex_meta']) && $urlStatus['has_noindex_meta'] ? 'Noindex' : 'OK'; ?>
                                                    </td>
                                                    <td class="<?php echo isset($urlStatus['has_noindex_header']) && $urlStatus['has_noindex_header'] ? 'table-danger' : 'table-success'; ?>">
                                                        <?php echo isset($urlStatus['has_noindex_header']) && $urlStatus['has_noindex_header'] ? 'Noindex' : 'OK'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Empfehlungen zur Verbesserung -->
                            <div class="col-12 mt-4">
                                <h5>Empfehlungen zur Verbesserung</h5>
                                <?php 
                                $recommendations = generateSitemapRecommendations($results);
                                
                                if (empty($recommendations)): 
                                ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> Ihre Sitemap ist bereits optimal konfiguriert!
                                    </div>
                                <?php else: ?>
                                    <div class="accordion" id="accordionRecommendations">
                                        <?php foreach ($recommendations as $index => $recommendation): ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                    <button class="accordion-button <?php echo ($index > 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                                        <i class="fas fa-lightbulb text-warning me-2"></i> <?php echo htmlspecialchars($recommendation['title']); ?>
                                                    </button>
                                                </h2>
                                                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#accordionRecommendations">
                                                    <div class="accordion-body">
                                                        <p><?php echo htmlspecialchars($recommendation['description']); ?></p>
                                                        
                                                        <div class="mt-3">
                                                            <label class="fw-bold mb-2">Beispiel:</label>
                                                            <pre class="bg-light p-3 border rounded"><code><?php echo htmlspecialchars($recommendation['example']); ?></code></pre>
                                                            <button class="btn btn-sm btn-outline-primary copy-example" data-example-id="<?php echo $index; ?>">
                                                                <i class="fas fa-copy"></i> Beispiel kopieren
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <script>
                                        // JavaScript für "Kopieren"-Schaltflächen
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const copyButtons = document.querySelectorAll('.copy-example');
                                            copyButtons.forEach(button => {
                                                button.addEventListener('click', function() {
                                                    const exampleId = this.getAttribute('data-example-id');
                                                    const exampleCode = document.querySelector(`#collapse${exampleId} pre code`).textContent;
                                                    
                                                    navigator.clipboard.writeText(exampleCode).then(function() {
                                                        // Schaltflächen-Text vorübergehend ändern
                                                        const originalHTML = button.innerHTML;
                                                        button.innerHTML = '<i class="fas fa-check"></i> Kopiert!';
                                                        button.classList.remove('btn-outline-primary');
                                                        button.classList.add('btn-success');
                                                        
                                                        // Nach 2 Sekunden zurücksetzen
                                                        setTimeout(function() {
                                                            button.innerHTML = originalHTML;
                                                            button.classList.remove('btn-success');
                                                            button.classList.add('btn-outline-primary');
                                                        }, 2000);
                                                    }).catch(function(err) {
                                                        console.error('Fehler beim Kopieren:', err);
                                                        button.innerHTML = '<i class="fas fa-times"></i> Fehler!';
                                                        button.classList.remove('btn-outline-primary');
                                                        button.classList.add('btn-danger');
                                                        
                                                        // Nach 2 Sekunden zurücksetzen
                                                        setTimeout(function() {
                                                            button.innerHTML = '<i class="fas fa-copy"></i> Beispiel kopieren';
                                                            button.classList.remove('btn-danger');
                                                            button.classList.add('btn-outline-primary');
                                                        }, 2000);
                                                    });
                                                });
                                            });
                                        });
                                    </script>
                                <?php endif; ?>
                            </div>

                            <!-- Generator für Sitemap-Vorlagen -->
                            <div class="col-12 mt-4">
                                <h5>Sitemap-Generator</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <p>Erstellen Sie ein einfaches Sitemap-Template für Ihre Website:</p>
                                        
                                        <div class="mb-3">
                                            <label for="base-url" class="form-label">Website-Basis-URL:</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                                <input type="url" class="form-control" id="base-url" placeholder="https://www.example.com" value="<?php echo parse_url($results['url'], PHP_URL_SCHEME) . '://' . parse_url($results['url'], PHP_URL_HOST); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Sitemap-Typ:</label>
                                            <div class="d-flex flex-wrap gap-2">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="sitemap-type" id="type-basic" value="basic" checked>
                                                    <label class="form-check-label" for="type-basic">Standard</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="sitemap-type" id="type-index" value="index">
                                                    <label class="form-check-label" for="type-index">Sitemap-Index</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="sitemap-type" id="type-image" value="image">
                                                    <label class="form-check-label" for="type-image">Mit Bilder-Extension</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="sitemap-type" id="type-hreflang" value="hreflang">
                                                    <label class="form-check-label" for="type-hreflang">Mit hreflang/Sprachen</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button id="generate-sitemap" class="btn btn-primary">
                                            <i class="fas fa-code"></i> Sitemap generieren
                                        </button>
                                        
                                        <div class="mt-3 d-none" id="sitemap-result">
                                            <label class="fw-bold mb-2">Generierte Sitemap:</label>
                                            <pre class="bg-light p-3 border rounded"><code id="sitemap-code"></code></pre>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-outline-primary" id="copy-sitemap">
                                                    <i class="fas fa-copy"></i> Kopieren
                                                </button>
                                                <button class="btn btn-outline-success" id="download-sitemap">
                                                    <i class="fas fa-download"></i> Als sitemap.xml herunterladen
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEO-Tipps für Sitemap-Optimierung -->
                            <div class="col-12 mt-4">
                                <h5>SEO-Tipps zur Sitemap-Optimierung</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="border-bottom pb-2"><i class="fas fa-check-circle text-success me-2"></i>Best Practices für Sitemaps</h6>
                                                <ul class="list-unstyled">
                                                    <li class="mb-2">
                                                        <strong>Aktualität:</strong> Aktualisieren Sie Ihre Sitemap regelmäßig, insbesondere wenn neue Inhalte hinzugefügt werden.
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Lastmod-Angaben:</strong> Verwenden Sie genaue Lastmod-Daten, die tatsächlich dem letzten Änderungsdatum entsprechen.
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Prioritätseinstellungen:</strong> Setzen Sie höhere Prioritäten (0.8-1.0) für wichtige Seiten und niedrigere (0.1-0.5) für weniger wichtige.
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Organisationsstruktur:</strong> Teilen Sie große Websites in thematische Sitemaps auf (Produkte, Kategorien, Blog-Beiträge, etc.).
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Komprimierung:</strong> Verwenden Sie die .xml.gz-Komprimierung für bessere Performance und schnelleres Crawling.
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="border-bottom pb-2"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Was zu vermeiden ist</h6>
                                                <ul class="list-unstyled">
                                                    <li class="mb-2">
                                                        <strong>Falsche URLs:</strong> Vermeiden Sie URLs, die auf 404-Fehlerseiten, Weiterleitungen oder noindex-Seiten führen.
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Veraltete Inhalte:</strong> Entfernen Sie URLs zu gelöschten oder veralteten Inhalten.
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Übermäßige Größe:</strong> Halten Sie jede einzelne Sitemap-Datei unter 50.000 URLs und 50 MB.
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Interne Seiten:</strong> Fügen Sie keine Login-Seiten, Admin-Bereiche oder andere interne Seiten hinzu.
                                                    </li>
                                                    <li class="mb-2">
                                                        <strong>Manipulation:</strong> Setzen Sie nicht für alle Seiten den höchsten Prioritätswert (1.0).
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <h6 class="border-bottom pb-2"><i class="fas fa-lightbulb text-primary me-2"></i>Erweiterte Funktionen für spezielle Websites</h6>
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <div class="card h-100">
                                                        <div class="card-header"><strong>E-Commerce-Websites</strong></div>
                                                        <div class="card-body">
                                                            <ul class="small mb-0">
                                                                <li>Separate Sitemaps für Kategorien und Produkte</li>
                                                                <li>Bilder-Erweiterungen für Produktfotos</li>
                                                                <li>Häufige Aktualisierung für Preis- und Lagerbestandsänderungen</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card h-100">
                                                        <div class="card-header"><strong>Internationale Websites</strong></div>
                                                        <div class="card-body">
                                                            <ul class="small mb-0">
                                                                <li>Hreflang-Attribute für sprachspezifische Versionen</li>
                                                                <li>Separate Sitemaps pro Sprache/Region</li>
                                                                <li>xhtml:link Elemente zur Verbindung der Sprachversionen</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card h-100">
                                                        <div class="card-header"><strong>Medien-Websites</strong></div>
                                                        <div class="card-body">
                                                            <ul class="small mb-0">
                                                                <li>Video-Erweiterungen für Video-Inhalte</li>
                                                                <li>News-Sitemap für aktuelle Nachrichten</li>
                                                                <li>Häufig aktualisierte Sitemaps für neue Inhalte</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Tipp zum Ping von Suchmaschinen -->
                                        <div class="alert alert-info mt-4">
                                            <h6 class="alert-heading"><i class="fas fa-bell me-2"></i>Tipp: Suchmaschinen benachrichtigen</h6>
                                            <p class="mb-0">Benachrichtigen Sie Suchmaschinen nach dem Aktualisieren Ihrer Sitemap:</p>
                                            <ul class="mb-0">
                                                <li>Google: <code>https://www.google.com/ping?sitemap=https://www.example.com/sitemap.xml</code></li>
                                                <li>Bing: <code>https://www.bing.com/ping?sitemap=https://www.example.com/sitemap.xml</code></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0 d-flex align-items-center">
                                <div class="btn-group" role="group" aria-label="Export options">
                                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                        <i class="fas fa-print me-1"></i> Drucken
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="export-json" title="Als JSON exportieren">
                                        <i class="fas fa-file-code me-1"></i> JSON
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <a href="index.php" class="btn btn-primary">Neue Sitemap überprüfen</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                    &copy; <?php echo date('Y'); ?> by <a href="https://dlx-media.com">DLx-Media.com</a> - XML Sitemap Validator | 
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
        // Tooltips initialisieren
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Link-Kopier-Funktion
            document.querySelector('.copy-link').addEventListener('click', function() {
                var shareUrl = document.querySelector('.share-url').textContent.trim();
                navigator.clipboard.writeText(shareUrl).then(function() {
                    // Tooltip aktualisieren
                    var tooltip = bootstrap.Tooltip.getInstance(document.querySelector('.copy-link'));
                    document.querySelector('.copy-link').setAttribute('data-bs-original-title', 'Kopiert!');
                    tooltip.show();
                    
                    // Nach 2 Sekunden zurücksetzen
                    setTimeout(function() {
                        document.querySelector('.copy-link').setAttribute('data-bs-original-title', 'Link kopieren');
                        tooltip.hide();
                    }, 2000);
                }).catch(function() {
                    alert('Fehler beim Kopieren des Links!');
                });
            });
            
            // JSON-Export
            document.getElementById('export-json').addEventListener('click', function() {
                // Ergebnisse als JSON abrufen
                var results = <?php echo json_encode($validationResults); ?>;
                
                // Download-Link erstellen
                var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(results, null, 2));
                var downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute("href", dataStr);
                downloadAnchorNode.setAttribute("download", "sitemap-validation-<?php echo $id; ?>.json");
                document.body.appendChild(downloadAnchorNode);
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
            });
        });
        
        // Sitemap-Generator-Funktionalität
        document.addEventListener('DOMContentLoaded', function() {
            const generateButton = document.getElementById('generate-sitemap');
            const sitemapResult = document.getElementById('sitemap-result');
            const sitemapCode = document.getElementById('sitemap-code');
            const baseUrlInput = document.getElementById('base-url');
            const copyButton = document.getElementById('copy-sitemap');
            const downloadButton = document.getElementById('download-sitemap');
            
            if (generateButton) {
                generateButton.addEventListener('click', function() {
                    const baseUrl = baseUrlInput.value.trim();
                    if (!baseUrl) {
                        alert('Bitte geben Sie eine gültige Basis-URL ein');
                        return;
                    }
                    
                    // Ausgewählten Typ ermitteln
                    const selectedType = document.querySelector('input[name="sitemap-type"]:checked').value;
                    
                    // Template basierend auf dem Typ generieren
                    const templateXml = generateSitemapTemplate(baseUrl, selectedType);
                    
                    // Anzeigen des Ergebnisses
                    sitemapCode.textContent = templateXml;
                    sitemapResult.classList.remove('d-none');
                });
                
                // Kopieren-Funktionalität
                if (copyButton) {
                    copyButton.addEventListener('click', function() {
                        navigator.clipboard.writeText(sitemapCode.textContent).then(function() {
                            const originalHTML = copyButton.innerHTML;
                            copyButton.innerHTML = '<i class="fas fa-check"></i> Kopiert!';
                            copyButton.classList.remove('btn-outline-primary');
                            copyButton.classList.add('btn-success');
                            
                            setTimeout(function() {
                                copyButton.innerHTML = originalHTML;
                                copyButton.classList.remove('btn-success');
                                copyButton.classList.add('btn-outline-primary');
                            }, 2000);
                        }).catch(function(err) {
                            console.error('Fehler beim Kopieren:', err);
                        });
                    });
                }
                
                // Download-Funktionalität
                if (downloadButton) {
                    downloadButton.addEventListener('click', function() {
                        const blob = new Blob([sitemapCode.textContent], {type: 'application/xml'});
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'sitemap.xml';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    });
                }
            }
        });
        
        // Client-seitige Sitemap-Template-Generierung (für den Fall, dass die PHP-Funktion nicht verfügbar ist)
        function generateSitemapTemplate(baseUrl, type) {
            const xmlDeclaration = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>\n';
            const today = new Date().toISOString().split('T')[0];
            let xmlTemplate = xmlDeclaration;
            
            switch (type) {
                case 'index':
                    xmlTemplate += '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
                    xmlTemplate += '  <sitemap>\n';
                    xmlTemplate += '    <loc>' + baseUrl + '/sitemap1.xml</loc>\n';
                    xmlTemplate += '    <lastmod>' + today + '</lastmod>\n';
                    xmlTemplate += '  </sitemap>\n';
                    xmlTemplate += '  <sitemap>\n';
                    xmlTemplate += '    <loc>' + baseUrl + '/sitemap2.xml</loc>\n';
                    xmlTemplate += '    <lastmod>' + today + '</lastmod>\n';
                    xmlTemplate += '  </sitemap>\n';
                    xmlTemplate += '</sitemapindex>';
                    break;
                    
                case 'image':
                    xmlTemplate += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"\n';
                    xmlTemplate += '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">\n';
                    xmlTemplate += '  <url>\n';
                    xmlTemplate += '    <loc>' + baseUrl + '/page-with-images</loc>\n';
                    xmlTemplate += '    <lastmod>' + today + '</lastmod>\n';
                    xmlTemplate += '    <image:image>\n';
                    xmlTemplate += '      <image:loc>' + baseUrl + '/images/example1.jpg</image:loc>\n';
                    xmlTemplate += '      <image:title>Beispielbild 1</image:title>\n';
                    xmlTemplate += '    </image:image>\n';
                    xmlTemplate += '    <image:image>\n';
                    xmlTemplate += '      <image:loc>' + baseUrl + '/images/example2.jpg</image:loc>\n';
                    xmlTemplate += '      <image:title>Beispielbild 2</image:title>\n';
                    xmlTemplate += '    </image:image>\n';
                    xmlTemplate += '  </url>\n';
                    xmlTemplate += '</urlset>';
                    break;
                    
                case 'hreflang':
                    xmlTemplate += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"\n';
                    xmlTemplate += '        xmlns:xhtml="http://www.w3.org/1999/xhtml">\n';
                    xmlTemplate += '  <url>\n';
                    xmlTemplate += '    <loc>' + baseUrl + '</loc>\n';
                    xmlTemplate += '    <lastmod>' + today + '</lastmod>\n';
                    xmlTemplate += '    <xhtml:link rel="alternate" hreflang="de" href="' + baseUrl + '" />\n';
                    xmlTemplate += '    <xhtml:link rel="alternate" hreflang="en" href="' + baseUrl + '/en/" />\n';
                    xmlTemplate += '    <xhtml:link rel="alternate" hreflang="fr" href="' + baseUrl + '/fr/" />\n';
                    xmlTemplate += '  </url>\n';
                    xmlTemplate += '</urlset>';
                    break;
                    
                case 'basic':
                default:
                    xmlTemplate += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
                    xmlTemplate += '  <url>\n';
                    xmlTemplate += '    <loc>' + baseUrl + '</loc>\n';
                    xmlTemplate += '    <lastmod>' + today + '</lastmod>\n';
                    xmlTemplate += '    <changefreq>weekly</changefreq>\n';
                    xmlTemplate += '    <priority>1.0</priority>\n';
                    xmlTemplate += '  </url>\n';
                    xmlTemplate += '  <url>\n';
                    xmlTemplate += '    <loc>' + baseUrl + '/beispiel-seite</loc>\n';
                    xmlTemplate += '    <lastmod>' + today + '</lastmod>\n';
                    xmlTemplate += '    <changefreq>monthly</changefreq>\n';
                    xmlTemplate += '    <priority>0.8</priority>\n';
                    xmlTemplate += '  </url>\n';
                    xmlTemplate += '</urlset>';
                    break;
            }
            
            return xmlTemplate;
        }
        
        // Druck-Styles anpassen
        window.onbeforeprint = function() {
            // Buttons ausblenden
            var buttons = document.querySelectorAll('.btn');
            buttons.forEach(function(button) {
                button.style.display = 'none';
            });
        };
        
        window.onafterprint = function() {
            // Buttons wieder einblenden
            var buttons = document.querySelectorAll('.btn');
            buttons.forEach(function(button) {
                button.style.display = '';
            });
        };
    </script>
</body>
</html>
