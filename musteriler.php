<?php
// Session mesajlarını almak için session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi_dinamik = "Müşteriler"; // H2 için varsayılan
$arama_terimi = '';
$musteriler = [];
$e_var = null; // Hata kontrolü için

// Arama yapılıyor mu kontrol et
if (isset($_GET['arama']) && !empty(trim($_GET['arama']))) {
    $arama_terimi = trim($_GET['arama']);
    $sql = "CALL sp_Musteriler_Ara(:arama_terimi)";
    $sayfa_basligi_dinamik = "'" . htmlspecialchars($arama_terimi) . "' için Arama Sonuçları";
} else {
    $sql = "CALL sp_Musteriler_Listele()";
}

// Sayfa başlığı <title> için
$sayfa_basligi = $sayfa_basligi_dinamik . " - Veteriner Kliniği";
if ($sayfa_basligi_dinamik === "Müşteriler" && empty($arama_terimi)) { // Eğer arama yoksa ve varsayılan başlık ise
    $sayfa_basligi = "Müşteri Listesi - Veteriner Kliniği";
}

include 'includes/header.php'; 

try {
    $stmt = $pdo->prepare($sql);
    if (!empty($arama_terimi)) {
        $stmt->bindParam(':arama_terimi', $arama_terimi, PDO::PARAM_STR);
    }
    $stmt->execute();
    $musteriler = $stmt->fetchAll();
    $stmt->closeCursor();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger mt-3'>Müşteriler listelenirken/aranırken bir hata oluştu: " . $e->getMessage() . "</div>";
    $e_var = $e;
}
?>

<div class="container mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="page-title"><?php echo htmlspecialchars($sayfa_basligi_dinamik); ?></h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="musteri_ekle.php" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Yeni Müşteri Ekle</a>
        </div>
    </div>

    <!-- Arama Formu -->
    <form action="musteriler.php" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-10 mb-2 mb-md-0">
                <input type="text" name="arama" class="form-control" placeholder="Müşteri Adı, Soyadı, Telefon veya Adreste Ara..." value="<?php echo htmlspecialchars($arama_terimi); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ara</button>
            </div>
        </div>
        <?php if (!empty($arama_terimi)): ?>
            <div class="row mt-2">
                <div class="col">
                    <a href="musteriler.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times-circle me-1"></i>Aramayı Temizle</a>
                </div>
            </div>
        <?php endif; ?>
    </form>
    <!-- Arama Formu Sonu -->

    <?php
    if (isset($_SESSION['mesaj_musteri'])) {
        echo $_SESSION['mesaj_musteri'];
        unset($_SESSION['mesaj_musteri']);
    }
    ?>

    <?php if (empty($musteriler) && !$e_var): ?>
        <div class="text-center p-5 border rounded bg-light shadow-sm">
            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
            <p class="lead">
                <?php if (!empty($arama_terimi)): ?>
                    Aradığınız kriterlere uygun müşteri bulunamadı.
                <?php else: ?>
                    Henüz kayıtlı müşteri bulunmamaktadır.
                <?php endif; ?>
            </p>
            <?php if (empty($arama_terimi)): ?>
            <a href="musteri_ekle.php" class="btn btn-lg btn-success mt-3"><i class="fas fa-plus-circle me-2"></i> Yeni Müşteri Kaydı Oluşturun</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($musteriler)): ?>
        <div class="table-responsive"> <!-- Tablonun taşmasını engellemek için -->
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Adı</th>
                        <th>Soyadı</th>
                        <th>Telefon</th>
                        <th>Adres</th>
                        <th class="text-center" style="width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($musteriler as $musteri): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($musteri['MusteriID']); ?></td>
                        <td><?php echo htmlspecialchars($musteri['Adi']); ?></td>
                        <td><?php echo htmlspecialchars($musteri['Soyadi']); ?></td>
                        <td><?php echo htmlspecialchars($musteri['TelefonNumarasi']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($musteri['Adres'])); ?></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton_<?php echo $musteri['MusteriID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i> <!-- İşlemler ikonu -->
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton_<?php echo $musteri['MusteriID']; ?>">
                                    <li><a class="dropdown-item" href="musteri_duzenle.php?id=<?php echo $musteri['MusteriID']; ?>"><i class="fas fa-edit text-primary me-2"></i>Düzenle</a></li>
                                    <li><a class="dropdown-item" href="hayvanlar.php?musteri_id=<?php echo $musteri['MusteriID']; ?>"><i class="fas fa-paw text-info me-2"></i>Hayvanları</a></li>
                                    <li><a class="dropdown-item" href="faturalar.php?musteri_id=<?php echo $musteri['MusteriID']; ?>"><i class="fas fa-file-invoice-dollar text-warning me-2"></i>Faturaları</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="musteri_sil.php?id=<?php echo $musteri['MusteriID']; ?>" onclick="return confirm('Bu müşteriyi ve ilişkili tüm kayıtlarını (hayvanlar, randevular, faturalar vb. etkilenebilir) silmek istediğinize emin misiniz? Bu işlem geri alınamaz ve dikkatli olunmalıdır!');"><i class="fas fa-trash-alt me-2"></i>Sil</a></li>
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