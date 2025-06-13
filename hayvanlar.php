<?php
// Session mesajlarını almak için session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$arama_terimi_hayvan = '';
// $hayvanlar = []; // Bu, header.php'den sonra tanımlanacak
$musteri_id_filtre = null; // Filtre için
$filtre_mesaji = "";     // Filtre için
$sayfa_basligi_dinamik = "Hayvanlar - Veteriner Kliniği"; // Varsayılan başlık (H2 için)
$sql = ""; // Ana SQL sorgusu
$musteri_bilgisi = null; // Müşteri bilgilerini tutmak için

// Önce filtreleri ve aramayı belirle, SQL sorgusunu oluştur
if (isset($_GET['arama_hayvan']) && !empty(trim($_GET['arama_hayvan']))) {
    $arama_terimi_hayvan = trim($_GET['arama_hayvan']);
    $sql = "CALL sp_Hayvanlar_Ara(:arama_terimi)";
    $sayfa_basligi_dinamik = "'" . htmlspecialchars($arama_terimi_hayvan) . "' için Arama Sonuçları - Hayvanlar";
    if (isset($_GET['musteri_id'])) {
        $filtre_mesaji .= "<p class='text-warning'>Arama yapılırken müşteri filtresi dikkate alınmaz. Tüm hayvanlar içinde arama yapılıyor.</p>";
        // $musteri_id_filtre = null; // Arama sırasında müşteri filtresini sıfırlama ihtiyacı yok, SQL zaten tüm hayvanlarda arar.
    }
} elseif (isset($_GET['musteri_id']) && is_numeric($_GET['musteri_id'])) {
    $musteri_id_filtre = intval($_GET['musteri_id']);
    $sql = "CALL sp_MusterininHayvanlari_Listele(:musteri_id)";
    // Müşteri adını çekme işlemi header.php'den sonra yapılacak
} else {
    $sql = "CALL sp_Hayvanlar_Listele()";
}

// <title> için sayfa başlığını ayarla
$sayfa_basligi = $sayfa_basligi_dinamik; // İlk atama
if (strpos($sayfa_basligi, "Arama Sonuçları") === false && $musteri_id_filtre) {
    // Eğer arama yoksa ve müşteri filtresi varsa, başlık "Müşterinin Hayvanları" olabilir.
    // Bu, aşağıdaki blokta müşteri adı çekildikten sonra daha doğru set edilebilir.
    // Şimdilik, header.php'ye genel bir başlık geçirelim.
    // $sayfa_basligi = "Müşteriye Ait Hayvanlar - Veteriner Kliniği";
}


include 'includes/header.php'; // Header'ı burada dahil et ($pdo gelir)

// Eğer müşteri filtresi varsa ve arama yapılmıyorsa, müşteri adını ÇEK ve H2 başlığını/filtre mesajını GÜNCELLE
if ($musteri_id_filtre !== null && empty($arama_terimi_hayvan)) {
    try {
        $stmt_musteri_adi = $pdo->prepare("CALL sp_Musteri_GetirByID(:id)");
        $stmt_musteri_adi->bindParam(':id', $musteri_id_filtre, PDO::PARAM_INT);
        $stmt_musteri_adi->execute();
        $musteri_bilgisi = $stmt_musteri_adi->fetch(); // $musteri_bilgisi'ni burada doldur
        $stmt_musteri_adi->closeCursor();
        if ($musteri_bilgisi) {
            $sayfa_basligi_dinamik = htmlspecialchars($musteri_bilgisi['Adi'] . " " . $musteri_bilgisi['Soyadi']) . " Adlı Müşterinin Hayvanları";
            $filtre_mesaji = "<h4>" . htmlspecialchars($musteri_bilgisi['Adi'] . " " . $musteri_bilgisi['Soyadi']) . " adlı müşterinin hayvanları listeleniyor. <a href='hayvanlar.php' class='btn btn-sm btn-secondary'>Tüm Hayvanları/Aramayı Temizle</a></h4>";
        }
    } catch (PDOException $e) { 
        $filtre_mesaji = "<p class='text-danger'>Müşteri adı alınırken hata oluştu.</p>";
    }
}

$hayvanlar = []; // Burada tanımla
$e_var = null; // Hata için
try {
    $stmt = $pdo->prepare($sql);
    if (!empty($arama_terimi_hayvan)) {
        $stmt->bindParam(':arama_terimi', $arama_terimi_hayvan, PDO::PARAM_STR);
    } elseif ($musteri_id_filtre) { // $musteri_id_filtre null değilse
        $stmt->bindParam(':musteri_id', $musteri_id_filtre, PDO::PARAM_INT);
    }
    $stmt->execute();
    $hayvanlar = $stmt->fetchAll();
    $stmt->closeCursor();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger mt-3'>Hayvanlar listelenirken/aranırken bir hata oluştu: " . $e->getMessage() . "</div>";
    $e_var = $e;
}

// Yaş hesaplama fonksiyonu (eğer yordamlar 'Yas' sütununu getirmiyorsa)
if (!function_exists('hayvanYasiHesapla')) {
    function hayvanYasiHesapla($dogumTarihi) {
        if (empty($dogumTarihi) || $dogumTarihi == '0000-00-00') return 'Bilinmiyor';
        try {
            $dogum = new DateTime($dogumTarihi);
            $bugun = new DateTime();
            if ($dogum > $bugun) return 'Bilinmiyor';
            $fark = $bugun->diff($dogum);
            $yasYil = $fark->y; $yasAy = $fark->m;
            if ($yasYil == 0 && $yasAy == 0) return $fark->d . " günlük";
            elseif ($yasYil == 0) return $yasAy . " aylık";
            else return $yasYil . " yaşında, " . $yasAy . " aylık";
        } catch (Exception $ex) { return 'Bilinmiyor'; }
    }
}
?>

<div class="container mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-7"> 
            <h2 class="page-title"><?php echo $sayfa_basligi_dinamik; // H2 için dinamik başlık ?></h2>
            <?php if (!empty($filtre_mesaji) && empty($arama_terimi_hayvan)) echo $filtre_mesaji; ?>
        </div>
        <div class="col-md-5 text-end">
            <a href="hayvan_ekle.php<?php echo $musteri_id_filtre ? '?musteri_id='.$musteri_id_filtre : ''; ?>" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Yeni Hayvan Ekle</a>
        </div>
    </div>
    
    <!-- Hayvan Arama Formu -->
    <form action="hayvanlar.php" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-10 mb-2 mb-md-0">
                <input type="text" name="arama_hayvan" class="form-control" placeholder="Hayvan Adı, Türü, Cinsi veya Sahip Adıyla Ara..." value="<?php echo htmlspecialchars($arama_terimi_hayvan); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ara</button>
            </div>
        </div>
        <?php if (!empty($arama_terimi_hayvan)): ?>
            <div class="row mt-2">
                <div class="col">
                    <a href="hayvanlar.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times-circle me-1"></i>Aramayı Temizle</a>
                </div>
            </div>
        <?php endif; ?>
    </form>
    <!-- Hayvan Arama Formu Sonu -->

    <?php
    if (isset($_SESSION['mesaj_hayvan'])) {
        echo $_SESSION['mesaj_hayvan'];
        unset($_SESSION['mesaj_hayvan']);
    }
    ?>

    <?php if (empty($hayvanlar) && !$e_var): ?>
        <div class="text-center p-5 border rounded bg-light shadow-sm">
            <i class="fas fa-paw fa-3x text-muted mb-3"></i> <!-- İkonu değiştirdim -->
             <p class="lead">
                <?php if (!empty($arama_terimi_hayvan)): ?>
                    Aradığınız kriterlere uygun hayvan bulunamadı.
                <?php elseif ($musteri_id_filtre): ?>
                    Bu müşteriye ait kayıtlı hayvan bulunmamaktadır.
                <?php else: ?>
                    Henüz kayıtlı hayvan bulunmamaktadır.
                <?php endif; ?>
            </p>
            <?php if (empty($arama_terimi_hayvan)): // Arama yoksa yeni ekleme butonu göster ?>
            <a href="hayvan_ekle.php<?php echo $musteri_id_filtre ? '?musteri_id='.$musteri_id_filtre : ''; ?>" class="btn btn-lg btn-success mt-3"><i class="fas fa-plus-circle me-2"></i> Yeni Hayvan Kaydı Oluşturun</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($hayvanlar)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Adı</th>
                        <th>Sahibi</th>
                        <th>Türü</th>
                        <th>Cinsi</th>
                        <th>Yaşı</th>
                        <th>Cinsiyet</th>
                        <th>Kayıt Tarihi</th>
                        <th class="text-center" style="width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hayvanlar as $hayvan): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($hayvan['HayvanID']); ?></td>
                        <td><?php echo htmlspecialchars($hayvan['Adi']); ?></td>
                        <td>
                            <?php
                            if (isset($hayvan['MusteriAdi'])) { // sp_Hayvanlar_Listele veya sp_Hayvanlar_Ara'dan gelir
                                echo '<a href="musteri_duzenle.php?id='.($hayvan['MusteriID'] ?? '').'">' . htmlspecialchars($hayvan['MusteriAdi'] . ' ' . $hayvan['MusteriSoyadi']) . '</a>';
                            } elseif ($musteri_bilgisi) { // Müşteri filtresinden gelir
                                echo htmlspecialchars($musteri_bilgisi['Adi'] . ' ' . $musteri_bilgisi['Soyadi']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($hayvan['Turu']); ?></td>
                        <td><?php echo htmlspecialchars($hayvan['Cinsi'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                            echo htmlspecialchars($hayvan['Yas'] ?? hayvanYasiHesapla($hayvan['DogumTarihi'])); 
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($hayvan['Cinsiyet']); ?></td>
                        <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($hayvan['KayitTarihi']))); ?></td>
                        <td class="text-center">
                             <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonH_<?php echo $hayvan['HayvanID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonH_<?php echo $hayvan['HayvanID']; ?>">
                                    <li><a class="dropdown-item" href="hayvan_duzenle.php?id=<?php echo $hayvan['HayvanID']; ?>"><i class="fas fa-edit text-primary me-2"></i>Düzenle</a></li>
                                    <li><a class="dropdown-item" href="randevular.php?hayvan_id=<?php echo $hayvan['HayvanID']; ?>"><i class="fas fa-calendar-alt text-info me-2"></i>Randevuları</a></li>
                                    <li><a class="dropdown-item" href="tedaviler.php?hayvan_id=<?php echo $hayvan['HayvanID']; ?>"><i class="fas fa-notes-medical text-warning me-2"></i>Tedavileri</a></li>
                                    <li><a class="dropdown-item" href="faturalar.php?musteri_id=<?php echo $hayvan['MusteriID']; ?>&hayvan_id_secili=<?php echo $hayvan['HayvanID']; ?>"><i class="fas fa-file-invoice text-secondary me-2"></i>Faturaları</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="hayvan_sil.php?id=<?php echo $hayvan['HayvanID']; ?>" onclick="return confirm('Bu hayvanı silmek istediğinize emin misiniz?');"><i class="fas fa-trash-alt me-2"></i>Sil</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>