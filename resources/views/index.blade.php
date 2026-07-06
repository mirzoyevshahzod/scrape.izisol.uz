<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraping Loyihalar Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 15px 20px;
            margin: 5px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
            border-color: rgba(255,255,255,0.3);
        }
        .main-content {
            padding: 30px;
        }
        .page-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-height: 400px;
        }
        .default-page-card {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .page-title {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        .page-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            text-align: center;
        }
        .header-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .sidebar-title {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .card {
            border: none;
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }
        .info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="sidebar-title">
                        <i class="fas fa-spider me-2"></i>
                        Scraping Loyihalar
                    </div>
                    <nav class="nav flex-column">
                         <a class="nav-link" href="/autos" target="_blank" id="nav-page4">
                            <i class="fas fa-globe-europe me-2"></i>
                            Autos
                        </a>
                         <a class="nav-link" href="/mintrans-auto" target="_blank" id="nav-page3">
                            <i class="fas fa-search me-2"></i>
                            Mintrans Avtoraqam
                        </a>
                           <a class="nav-link" href="/qozoq" target="_blank" id="nav-page6">
                            <i class="fas fa-file-alt me-2"></i>
                            Qozoq Scraper
                        </a>
                        <a class="nav-link" href="/check-qozoq" target="_blank" id="nav-page6">
                            <i class="fas fa-file-alt me-2"></i>
                            Qozoq Tekshirilgan
                        </a>
                        <a class="nav-link" href="/belarus" target="_blank" id="nav-page4">
                            <i class="fas fa-globe-europe me-2"></i>
                            Belarus Litva
                        </a>
                        <a class="nav-link" href="/turkey" target="_blank" id="nav-page5">
                            <i class="fas fa-flag me-2"></i>
                            Turkiya Gruziya
                        </a>
                        <a class="nav-link" href="/zitic" target="_blank" id="nav-page7">
                            <i class="fas fa-download me-2"></i>
                            Zitic Scraper
                        </a>
                        <a class="nav-link" href="/zanjeer" target="_blank" id="nav-page4">
                            <i class="fas fa-globe-europe me-2"></i>
                            Zanjeer Scraper
                        </a>
                        <a class="nav-link" href="/orginfo" target="_blank" id="nav-page7">
                            <i class="fas fa-download me-2"></i>
                            Orginfo Scraper
                        </a>
                        <a class="nav-link" href="{{ route('upload-zanjeer') }}" target="_blank" id="nav-page7">
                            <i class="fas fa-download me-2"></i>
                            Qozoq Tekshirilgan Converter
                        </a>
                        <a class="nav-link" href="{{ route('eOmborConverter') }}" target="_blank" id="nav-page7">
                            <i class="fas fa-download me-2"></i>
                            E Ombor Converter
                        </a>
                        <a class="nav-link" href="{{ route('turkiyaConverter') }}" target="_blank" id="nav-page7">
                            <i class="fas fa-download me-2"></i>
                            Turkiya Converter
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <h1 class="header-title">
                        <i class="fas fa-home me-2"></i>
                        Scraping Loyihalar Dashboard
                    </h1>

                    <!-- Default Content -->
                    <div id="default-content" class="page-card default-page-card">
                        <div class="text-center">
                            <i class="fas fa-rocket" style="font-size: 4rem; color: #667eea; margin-bottom: 20px;"></i>
                            <h2 class="page-title">Xush kelibsiz!</h2>
                            <p class="page-subtitle">
                                Chap tarafdan scraping loyihasini tanlang va ishni boshlang.
                                <br>
                                Barcha scraping loyihalar bitta joyda jamlangan.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>