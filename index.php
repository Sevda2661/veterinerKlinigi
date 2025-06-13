<?php
$sayfa_basligi = "Ana Sayfa - Veteriner Kliniği"; // <title> için
include 'includes/header.php'; 
?>

<div class="container mt-4 mb-5">
    <div class="hero-section text-center shadow-sm mb-5">
        <img class="d-block mx-auto mb-4" src="https://placehold.co/120x120/38761D/white?text=VK&font=roboto" alt="Klinik Logosu" width="120" height="120"> <!-- Logoyu güncelle -->
        <h1 class="display-5 fw-bold">Veteriner Kliniği Yönetim Paneli</h1>
        <div class="col-lg-8 mx-auto">
            <p class="lead mb-4">Kliniğinizin tüm operasyonlarını kolayca yönetin. Müşteri kayıtlarından randevu takibine, tedavi süreçlerinden faturalandırmaya kadar her şey tek bir yerde.</p>
        </div>
    </div>

    <h2 class="section-title">Hızlı Erişim Modülleri</h2>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        
        <div class="col">
            <div class="card h-100 card-module">
                <div class="card-icon"><i class="fas fa-users icon-musteri"></i></div>
                <div class="card-body">
                    <h5 class="card-title">Müşteri Yönetimi</h5>
                    <p class="card-text">Müşteri kayıtlarını görüntüleyin, ekleyin veya düzenleyin.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="musteriler.php" class="btn btn-primary w-100">Müşterilere Git <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 card-module">
                <div class="card-icon"><i class="fas fa-paw icon-hayvan"></i></div>
                <div class="card-body">
                    <h5 class="card-title">Hayvan Yönetimi</h5>
                    <p class="card-text">Kayıtlı hayvanları yönetin, yeni hayvan ekleyin.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="hayvanlar.php" class="btn btn-primary w-100">Hayvanlara Git <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 card-module">
                <div class="card-icon"><i class="fas fa-user-md icon-veteriner"></i></div>
                <div class="card-body">
                    <h5 class="card-title">Veteriner Yönetimi</h5>
                    <p class="card-text">Klinik veterinerlerini listeleyin ve yönetin.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="veterinerler.php" class="btn btn-primary w-100">Veterinerlere Git <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 card-module">
                <div class="card-icon"><i class="fas fa-calendar-check icon-randevu"></i></div>
                <div class="card-body">
                    <h5 class="card-title">Randevu Takibi</h5>
                    <p class="card-text">Randevuları görüntüleyin ve yeni randevular oluşturun.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="randevular.php" class="btn btn-primary w-100">Randevulara Git <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card h-100 card-module">
                <div class="card-icon"><i class="fas fa-briefcase-medical icon-tedavi"></i></div>
                <div class="card-body">
                    <h5 class="card-title">Tedavi Kayıtları</h5>
                    <p class="card-text">Uygulanan tedavileri kaydedin ve takip edin.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="tedaviler.php" class="btn btn-primary w-100">Tedavilere Git <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 card-module">
                <div class="card-icon"><i class="fas fa-pills icon-ilac"></i></div>
                <div class="card-body">
                    <h5 class="card-title">İlaç Stok Yönetimi</h5>
                    <p class="card-text">İlaç listesini ve stok durumlarını yönetin.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="ilaclar.php" class="btn btn-primary w-100">İlaçlara Git <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 card-module">
                 <div class="card-icon"><i class="fas fa-file-invoice-dollar icon-fatura"></i></div>
                <div class="card-body">
                    <h5 class="card-title">Faturalandırma</h5>
                    <p class="card-text">Faturaları oluşturun, görüntüleyin ve yönetin.</p>
                </div>
                 <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="faturalar.php" class="btn btn-primary w-100">Faturalara Git <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
include 'includes/footer.php';
?>