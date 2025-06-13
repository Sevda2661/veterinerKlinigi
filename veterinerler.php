<?php
// Session mesajlarını almak için session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi_dinamik = "Veterinerler"; // H2 için varsayılan
$arama_terimi_vet = '';
$veterinerler = [];
$e_var = null; // Hata kontrolü için

// Arama yapılıyor mu kontrol et
if (isset($_GET['arama_vet']) && !empty(trim($_GET['arama_vet']))) {
    $arama_terimi_vet = trim($_GET['arama_vet']);
    $sql = "CALL sp_Veterinerler_Ara(:arama_terimi)";
    $sayfa_basligi_dinamik = "'" . htmlspecialchars($arama_terimi_vet) . "' için Arama Sonuçları";
} else {
    // Varsayılan olarak tüm veterinerleri listele
    $sql = "CALL sp_Veterinerler_Listele()";
}

// <title> için sayfa başlığı
$sayfa_basligi = $sayfa_basligi_dinamik . " - Veteriner Kliniği";
if ($sayfa_basligi_dinamik === "Veterinerler" && empty($arama_terimi_vet)) {
    $sayfa_basligi = "Veteriner Listesi - Veteriner Kliniği";
}

include 'includes/header.php'; // Veritabanı bağlantısı ve sayfa üstü HTML'i dahil et

try {
    $stmt = $pdo->prepare($sql);
    if (!empty($arama_terimi_vet)) {
        $stmt->bindParam(':arama_terimi', $arama_terimi_vet, PDO::PARAM_STR);
    }
    $stmt->execute();
    $veterinerler = $stmt->fetchAll();
    $stmt->closeCursor();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger mt-3'>Veterinerler listelenirken/aranırken bir hata oluştu: " . $e->getMessage() . "</div>";
    $e_var = $e;
}
?>

<div class="container mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="page-title"><?php echo htmlspecialchars($sayfa_basligi_dinamik); ?></h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="veteriner_ekle.php" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Yeni Veteriner Ekle</a>
        </div>
    </div>

    <!-- Veteriner Arama Formu -->
    <form action="veterinerler.php" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-10 mb-2 mb-md-0">
                <input type="text" name="arama_vet" class="form-control" placeholder="Veteriner Adı, Soyadı veya Telefon Numarası Ara..." value="<?php echo htmlspecialchars($arama_terimi_vet); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ara</button>
            </div>
        </div>
        <?php if (!empty($arama_terimi_vet)): ?>
            <div class="row mt-2">
                <div class="col">
                    <a href="veterinerler.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times-circle me-1"></i>Aramayı Temizle</a>
                </div>
            </div>
        <?php endif; ?>
    </form>
    <!-- Veteriner Arama Formu Sonu -->

    <?php
    // Session mesajlarını göstermek için
    if (isset($_SESSION['mesaj_veteriner'])) {
        echo $_SESSION['mesaj_veteriner'];
        unset($_SESSION['mesaj_veteriner']);
    }
    ?>

    <?php if (empty($veterinerler) && !$e_var): ?>
        <div class="text-center p-5 border rounded bg-light shadow-sm">
            <i class="fas fa-user-md fa-3x text-muted mb-3"></i> <!-- İkonu güncelledim -->
            <p class="lead">
                <?php if (!empty($arama_terimi_vet)): ?>
                    Aradığınız kriterlere uygun veteriner bulunamadı.
                <?php else: ?>
                    Henüz kayıtlı veteriner bulunmamaktadır.
                <?php endif; ?>
            </p>
            <?php if (empty($arama_terimi_vet)): ?>
            <a href="veteriner_ekle.php" class="btn btn-lg btn-success mt-3"><i class="fas fa-plus-circle me-2"></i> Yeni Veteriner Kaydı Oluşturun</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($veterinerler)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Adı</th>
                        <th>Soyadı</th>
                        <th>Telefon Numarası</th>
                        <th class="text-center" style="width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($veterinerler as $veteriner): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($veteriner['VeterinerID']); ?></td>
                        <td><?php echo htmlspecialchars($veteriner['Adi']); ?></td>
                        <td><?php echo htmlspecialchars($veteriner['Soyadi']); ?></td>
                        <td><?php echo htmlspecialchars($veteriner['TelefonNumarasi']); ?></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonV_<?php echo $veteriner['VeterinerID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonV_<?php echo $veteriner['VeterinerID']; ?>">
                                    <li><a class="dropdown-item" href="veteriner_duzenle.php?id=<?php echo $veteriner['VeterinerID']; ?>"><i class="fas fa-edit text-primary me-2"></i>Düzenle</a></li>
                                    <li><a class="dropdown-item" href="randevular.php?veteriner_id=<?php echo $veteriner['VeterinerID']; ?>"><i class="fas fa-calendar-alt text-info me-2"></i>Randevuları</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="veteriner_sil.php?id=<?php echo $veteriner['VeterinerID']; ?>" onclick="return confirm('Bu veterineri silmek istediğinize emin misiniz? Bu işlem, veterinerin randevu ve tedavi kayıtlarını etkileyebilir.');"><i class="fas fa-trash-alt me-2"></i>Sil</a></li>
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