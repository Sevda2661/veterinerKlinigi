<?php
// Session mesajlarını almak için session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi_dinamik = "Tedaviler"; // H2 için varsayılan
$filtre_mesaji = "";
$arama_terimi_tedavi = '';
$param_id_hayvan = null; // Hayvan filtresi için ID
$sql_ana_tedavi = ""; // Kullanılacak ana SQL sorgusu
$e_var = null; // Hata kontrolü için

// Önce aramayı kontrol et
if (isset($_GET['arama_tedavi']) && !empty(trim($_GET['arama_tedavi']))) {
    $arama_terimi_tedavi = trim($_GET['arama_tedavi']);
    $sql_ana_tedavi = "CALL sp_Tedaviler_Ara(:arama_terimi)";
    $sayfa_basligi_dinamik = "'" . htmlspecialchars($arama_terimi_tedavi) . "' için Arama Sonuçları";
    if (isset($_GET['hayvan_id'])) {
        $filtre_mesaji = "<p class='text-warning'>Arama yapılırken hayvan filtresi dikkate alınmaz. Tüm tedaviler içinde arama yapılıyor.</p>";
    }
} 
// Arama yoksa hayvan filtresini kontrol et
elseif (isset($_GET['hayvan_id']) && is_numeric($_GET['hayvan_id'])) {
    $param_id_hayvan = intval($_GET['hayvan_id']);
    $sql_ana_tedavi = "CALL sp_HayvaninTedavileri_Listele(:id)"; 
} else {
    $sql_ana_tedavi = "CALL sp_Tedaviler_Listele()";
}

// <title> için sayfa başlığı
$sayfa_basligi = $sayfa_basligi_dinamik . " - Veteriner Kliniği";
if ($param_id_hayvan && empty($arama_terimi_tedavi)) {
    // Hayvan adı aşağıdaki blokta çekileceği için, <title> için genel bir ifade kullanabiliriz veya boş bırakıp H2'ye odaklanabiliriz.
    // Şimdilik, eğer filtre varsa ve hayvan adı çekilecekse, H2 başlığı daha spesifik olacak.
    $sayfa_basligi = "Hayvana Ait Tedaviler - Veteriner Kliniği";
} elseif ($sayfa_basligi_dinamik === "Tedaviler" && empty($arama_terimi_tedavi)){
    $sayfa_basligi = "Tedavi Listesi - Veteriner Kliniği";
}


include 'includes/header.php'; 

$tedaviler = [];
try {
    $stmt = $pdo->prepare($sql_ana_tedavi);
    if (!empty($arama_terimi_tedavi)) {
        $stmt->bindParam(':arama_terimi', $arama_terimi_tedavi, PDO::PARAM_STR);
    } elseif ($param_id_hayvan !== null) {
        $stmt->bindParam(':id', $param_id_hayvan, PDO::PARAM_INT);
    }
    $stmt->execute();
    $tedaviler = $stmt->fetchAll();
    $stmt->closeCursor();

    if ($param_id_hayvan !== null && empty($arama_terimi_tedavi) && !empty($tedaviler)) {
        $filtrelenen_hayvan_adi = $tedaviler[0]['HayvanAdi']; // sp_HayvaninTedavileri_Listele HayvanAdi getirmeli
        $sayfa_basligi_dinamik = htmlspecialchars($filtrelenen_hayvan_adi) . " Adlı Hayvana Uygulanan Tedaviler";
        $filtre_mesaji = "<h4>" . htmlspecialchars($filtrelenen_hayvan_adi) . " adlı hayvana uygulanan tedaviler listeleniyor. <a href='tedaviler.php' class='btn btn-sm btn-outline-secondary'><i class='fas fa-times-circle me-1'></i>Filtreyi Temizle</a></h4>";
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger mt-3'>Tedaviler listelenirken/aranırken bir hata oluştu: " . $e->getMessage() . "</div>";
    $e_var = $e;
}
?>

<div class="container mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-7">
            <h2 class="page-title"><?php echo $sayfa_basligi_dinamik; ?></h2>
            <?php if (!empty($filtre_mesaji)) echo $filtre_mesaji; ?>
        </div>
        <div class="col-md-5 text-end">
            <a href="tedavi_ekle.php<?php echo $param_id_hayvan ? '?hayvan_id='.$param_id_hayvan : ''; ?>" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Yeni Tedavi Ekle</a>
        </div>
    </div>

    <!-- Tedavi Arama Formu -->
    <form action="tedaviler.php" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-10 mb-2 mb-md-0">
                <input type="text" name="arama_tedavi" class="form-control" placeholder="Hayvan Adı, Veteriner Adı/Soyadı veya Tanı Ara..." value="<?php echo htmlspecialchars($arama_terimi_tedavi); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ara</button>
            </div>
        </div>
        <?php if (!empty($arama_terimi_tedavi)): ?>
            <div class="row mt-2">
                <div class="col">
                    <a href="tedaviler.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times-circle me-1"></i>Aramayı Temizle</a>
                </div>
            </div>
        <?php endif; ?>
    </form>
    <!-- Tedavi Arama Formu Sonu -->

    <?php
    if (isset($_SESSION['mesaj_tedavi'])) {
        echo $_SESSION['mesaj_tedavi'];
        unset($_SESSION['mesaj_tedavi']);
    }
    ?>

    <?php if (empty($tedaviler) && !$e_var): ?>
        <div class="text-center p-5 border rounded bg-light shadow-sm">
            <i class="fas fa-notes-medical fa-3x text-muted mb-3"></i>
            <p class="lead">
                <?php if (!empty($arama_terimi_tedavi)): ?>
                    Aradığınız kriterlere uygun tedavi kaydı bulunamadı.
                <?php elseif ($param_id_hayvan): ?>
                    Bu hayvana ait tedavi kaydı bulunmamaktadır.
                <?php else: ?>
                    Henüz kayıtlı tedavi bulunmamaktadır.
                <?php endif; ?>
            </p>
            <?php if (empty($arama_terimi_tedavi)): ?>
            <a href="tedavi_ekle.php<?php echo $param_id_hayvan ? '?hayvan_id='.$param_id_hayvan : ''; ?>" class="btn btn-lg btn-success mt-3"><i class="fas fa-plus-circle me-2"></i> Yeni Tedavi Kaydı Oluşturun</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($tedaviler)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Hayvan Adı</th>
                        <th>Veteriner</th>
                        <th>Tanı</th>
                        <th>Tedavi Tarihi</th>
                        <th class="text-center" style="width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tedaviler as $tedavi): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tedavi['TedaviID']); ?></td>
                        <td>
                            <?php 
                            if (isset($tedavi['HayvanID']) && isset($tedavi['HayvanAdi'])) {
                                echo '<a href="hayvan_duzenle.php?id='.$tedavi['HayvanID'].'">'.htmlspecialchars($tedavi['HayvanAdi']).'</a>';
                            } else {
                                echo htmlspecialchars($tedavi['HayvanAdi'] ?? 'N/A');
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (isset($tedavi['VeterinerID']) && isset($tedavi['VeterinerAdiSoyadi'])) {
                               echo '<a href="veteriner_duzenle.php?id='.$tedavi['VeterinerID'].'">'.htmlspecialchars($tedavi['VeterinerAdiSoyadi']).'</a>';
                            } else {
                                echo htmlspecialchars($tedavi['VeterinerAdiSoyadi'] ?? 'N/A');
                            }
                            ?>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars($tedavi['Tani'])); ?></td>
                        <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($tedavi['TedaviTarihi']))); ?></td>
                        <td class="text-center">
                             <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonT_<?php echo $tedavi['TedaviID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonT_<?php echo $tedavi['TedaviID']; ?>">
                                    <li><a class="dropdown-item" href="tedavi_duzenle.php?id=<?php echo $tedavi['TedaviID']; ?>"><i class="fas fa-edit text-primary me-2"></i>Düzenle</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="tedavi_sil.php?id=<?php echo $tedavi['TedaviID']; ?>&hayvan_id=<?php echo $tedavi['HayvanID'] ?? ($param_id_hayvan ?? ''); ?>" onclick="return confirm('Bu tedavi kaydını silmek istediğinize emin misiniz?');"><i class="fas fa-trash-alt me-2"></i>Sil</a></li>
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