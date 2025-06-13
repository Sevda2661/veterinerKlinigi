<?php
// Proje ana dizinini belirlemek için
require_once __DIR__ . '/../config/db_config.php';

// Sayfa başlığını dinamik olarak ayarlamak
$sayfa_basligi_html = isset($sayfa_basligi) ? $sayfa_basligi : "Veteriner Kliniği Yönetim Sistemi";

// Aktif sayfanın adını al
$aktif_sayfa = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sayfa_basligi_html); ?></title>
    
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Bootstrap Icons CDN (İstersen bunu kaldırabilirsin, Font Awesome daha kapsamlı) -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"> -->
    
    <!-- Özel CSS Dosyamız -->
    <link rel="stylesheet" href="assets/css/style.css"> 

</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><i class="fas fa-clinic-medical"></i> Veteriner Kliniği</a> <!-- Font Awesome ikonu -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?php if ($aktif_sayfa == 'index.php') echo 'active'; ?>" href="index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if (in_array($aktif_sayfa, ['musteriler.php', 'musteri_ekle.php', 'musteri_duzenle.php'])) echo 'active'; ?>" href="musteriler.php"><i class="fas fa-users"></i> Müşteriler</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if (in_array($aktif_sayfa, ['hayvanlar.php', 'hayvan_ekle.php', 'hayvan_duzenle.php'])) echo 'active'; ?>" href="hayvanlar.php"><i class="fas fa-paw"></i> Hayvanlar</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if (in_array($aktif_sayfa, ['veterinerler.php', 'veteriner_ekle.php', 'veteriner_duzenle.php'])) echo 'active'; ?>" href="veterinerler.php"><i class="fas fa-user-md"></i> Veterinerler</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if (in_array($aktif_sayfa, ['randevular.php', 'randevu_ekle.php', 'randevu_duzenle.php'])) echo 'active'; ?>" href="randevular.php"><i class="fas fa-calendar-check"></i> Randevular</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if (in_array($aktif_sayfa, ['tedaviler.php', 'tedavi_ekle.php', 'tedavi_duzenle.php'])) echo 'active'; ?>" href="tedaviler.php"><i class="fas fa-briefcase-medical"></i> Tedaviler</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if (in_array($aktif_sayfa, ['ilaclar.php', 'ilac_ekle.php', 'ilac_duzenle.php'])) echo 'active'; ?>" href="ilaclar.php"><i class="fas fa-pills"></i> İlaçlar</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if (in_array($aktif_sayfa, ['faturalar.php', 'fatura_ekle.php', 'fatura_duzenle.php', 'fatura_goruntule.php'])) echo 'active'; ?>" href="faturalar.php"><i class="fas fa-file-invoice-dollar"></i> Faturalar</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">