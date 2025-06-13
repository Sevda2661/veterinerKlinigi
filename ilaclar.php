<?php
// Session mesajlarını almak için session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi_dinamik = "İlaç Listesi"; 
$arama_terimi_ilac = '';
$ilaclar = [];
$e_var = null; 

if (isset($_GET['arama_ilac']) && !empty(trim($_GET['arama_ilac']))) {
    $arama_terimi_ilac = trim($_GET['arama_ilac']);
    $sql = "CALL sp_Ilaclar_Ara(:arama_terimi)";
    $sayfa_basligi_dinamik = "'" . htmlspecialchars($arama_terimi_ilac) . "' için Arama Sonuçları";
} else {
    $sql = "CALL sp_Ilaclar_Listele()";
}

$sayfa_basligi = $sayfa_basligi_dinamik . " - Veteriner Kliniği";
if ($sayfa_basligi_dinamik === "İlaç Listesi" && empty($arama_terimi_ilac)) {
    $sayfa_basligi = "İlaç Listesi ve Stok Durumu - Veteriner Kliniği";
}

include 'includes/header.php';

try {
    $stmt = $pdo->prepare($sql);
    if (!empty($arama_terimi_ilac)) {
        $stmt->bindParam(':arama_terimi', $arama_terimi_ilac, PDO::PARAM_STR);
    }
    $stmt->execute();
    $ilaclar = $stmt->fetchAll();
    $stmt->closeCursor();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger mt-3'>İlaçlar listelenirken/aranırken bir hata: " . $e->getMessage() . "</div>";
    $e_var = $e;
}
?>

<div class="container mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="page-title"><?php echo htmlspecialchars($sayfa_basligi_dinamik); ?></h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="ilac_ekle.php" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Yeni İlaç Ekle</a>
        </div>
    </div>

    <form action="ilaclar.php" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-10 mb-2 mb-md-0">
                <input type="text" name="arama_ilac" class="form-control" placeholder="İlaç Adı Ara..." value="<?php echo htmlspecialchars($arama_terimi_ilac); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ara</button>
            </div>
        </div>
        <?php if (!empty($arama_terimi_ilac)): ?>
            <div class="row mt-2">
                <div class="col">
                    <a href="ilaclar.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times-circle me-1"></i>Aramayı Temizle</a>
                </div>
            </div>
        <?php endif; ?>
    </form>

    <?php
    if (isset($_SESSION['mesaj_ilac'])) {
        echo $_SESSION['mesaj_ilac'];
        unset($_SESSION['mesaj_ilac']);
    }
    ?>

    <?php if (empty($ilaclar) && !$e_var): ?>
        <div class="text-center p-5 border rounded bg-light shadow-sm">
            <i class="fas fa-pills fa-3x text-muted mb-3"></i>
            <p class="lead">
                <?php if (!empty($arama_terimi_ilac)): ?>
                    Aradığınız kriterlere uygun ilaç bulunamadı.
                <?php else: ?>
                    Henüz kayıtlı ilaç bulunmamaktadır.
                <?php endif; ?>
            </p>
            <?php if (empty($arama_terimi_ilac)): ?>
            <a href="ilac_ekle.php" class="btn btn-lg btn-success mt-3"><i class="fas fa-plus-circle me-2"></i> Yeni İlaç Kaydı Oluşturun</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($ilaclar)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>İlaç Adı</th>
                        <th>Stok Miktarı</th>
                        <th>Birim Satış Fiyatı</th>
                        <th class="text-center" style="width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ilaclar as $ilac): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ilac['IlacID']); ?></td>
                        <td><?php echo htmlspecialchars($ilac['IlacAdi']); ?></td>
                        <td><?php echo htmlspecialchars($ilac['StokMiktari']); ?> adet</td>
                        <td><?php echo htmlspecialchars(number_format($ilac['BirimSatisFiyati'], 2, ',', '.')); ?> TL</td>
                        <td class="text-center">
                             <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonI_<?php echo $ilac['IlacID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonI_<?php echo $ilac['IlacID']; ?>">
                                    <li><a class="dropdown-item" href="ilac_duzenle.php?id=<?php echo $ilac['IlacID']; ?>"><i class="fas fa-edit text-primary me-2"></i>Düzenle</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="ilac_sil.php?id=<?php echo $ilac['IlacID']; ?>" onclick="return confirm('Bu ilacı silmek istediğinize emin misiniz? Bu işlem, ilacın fatura kayıtlarındaki ilişkisini etkileyebilir.');"><i class="fas fa-trash-alt me-2"></i>Sil</a></li>
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