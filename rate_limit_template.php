<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitemap Validator - Rate-Limit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h1 class="h4 mb-0">Rate-Limit überschritten</h1>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Sie haben das Limit für Anfragen erreicht. Bitte versuchen Sie es später erneut.
                        </div>
                        
                        <p>Aus Sicherheitsgründen und um Überlastung zu vermeiden, beschränken wir die Anzahl der Validierungen pro Stunde.</p>
                        
                        <p class="text-center mt-4">
                            <a href="index.php" class="btn btn-primary">Zurück zur Startseite</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>