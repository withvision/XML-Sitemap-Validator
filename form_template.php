<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sitemap Validator - Überprüfen Sie Ihre sitemap.xml auf Fehler und Konformität mit den Standards">
    <title>XML Sitemap Validator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h4 mb-0">Sitemap Validator</h1>
                    </div>
                    <div class="card-body">
                        <p class="lead">Geben Sie die URL einer Sitemap-Datei ein, um diese zu analysieren:</p>
                        
                        <form action="?action=validate" method="post" id="sitemap-form">
                            <div class="mb-3">
                                <label for="sitemap_url" class="form-label">Sitemap-URL:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-link"></i></span>
                                    <input type="url" class="form-control" id="sitemap_url" name="sitemap_url" 
                                           placeholder="https://example.com/sitemap.xml" required
                                           title="Gültige URL zu einer Sitemap (beginnt mit http:// oder https://)">
                                </div>
                                <div class="form-text">
                                    Geben Sie die vollständige URL zur Sitemap ein. Unterstützt werden nicht nur .xml oder .xml.gz, 
                                    sondern auch dynamisch generierte Sitemaps (z.B. .php, .jsp) und URLs mit Parametern, 
                                    solange der Inhalt ein gültiges XML ist.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submit-button">
                                    <span class="spinner-border spinner-border-sm d-none" id="loading-spinner" role="status" aria-hidden="true"></span>
                                    <span id="button-text">Sitemap analysieren</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <h5>Was wird überprüft?</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="small">
                                    <li><strong>HTTP Status & MIME-Type:</strong> Korrekte HTTP-Statuscode und Content-Type-Header</li>
                                    <li><strong>XML-Validität:</strong> Die Datei muss wohlgeformtes XML sein</li>
                                    <li><strong>Codierung:</strong> UTF-8 muss verwendet werden</li>
                                    <li><strong>Root-Element:</strong> Muss &lt;urlset&gt; oder &lt;sitemapindex&gt; mit korrektem Namespace sein</li>
                                    <li><strong>URL-Einträge:</strong> Jeder Eintrag muss ein &lt;loc&gt;-Element enthalten</li>
                                    <li><strong>Attributwerte:</strong> Prüfung auf gültige lastmod, changefreq und priority-Werte</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="small">
                                    <li><strong>Limitierungen:</strong> Maximal 50.000 URLs und 50 MB Dateigröße</li>
                                    <li><strong>Duplikate:</strong> Prüfung auf doppelte URLs</li>
                                    <li><strong>Komprimierung:</strong> Prüfung auf .xml.gz oder HTTP-Komprimierung</li>
                                    <li><strong>robots.txt:</strong> Prüfung auf Referenzierung der Sitemap</li>
                                    <li><strong>Stichproben:</strong> Prüfung zufällig ausgewählter URLs auf Erreichbarkeit und Indexierbarkeit</li>
                                    <li><strong>Erweiterungen:</strong> Erkennung von Image-, Video-, News-, Mobile- und hreflang-Erweiterungen</li>
                                </ul>
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
        // Ladeanzeige während der Verarbeitung
        document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('sitemap-form').addEventListener('submit', function(e) {
        // URL-Validierung hier entfernt um alle URL-Typen zuzulassen
        
        // Ladezustand anzeigen
        const spinner = document.getElementById('loading-spinner');
        const buttonText = document.getElementById('button-text');
        const submitButton = document.getElementById('submit-button');
        
        spinner.classList.remove('d-none');
        buttonText.textContent = 'Wird analysiert...';
        submitButton.disabled = true;
    });
});
    </script>
</body>
</html>
