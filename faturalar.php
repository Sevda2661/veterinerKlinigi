<?php
// Session mesajlarını almak için session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi_dinamik = "Faturalar"; // H2 için varsayılan
$filtre_mesaji = "";
$arama_terimi_fatura = '';
$param_id_musteri = null; // Müşteri filtresi için ID
$sql_ana_fatura = ""; // Kullanılacak ana SQL sorgusu
$e_var = null; // Hata kontrolü için

// Önce aramayı kontrol et
if (isset($_GET['arama_fatura']) && !empty(trim($_GET['arama_fatura']))) {
    $arama_terimi_fatura = trim($_GET['arama_fatura']);
    $sql_ana_fatura = "CALL sp_Faturalar_Ara(:arama_terimi)";
    $sayfa_basligi_dinamik = "'" . htmlspecialchars($arama_terimi_fatura) . "' için Arama Sonuçları";
    if (isset($_GET['musteri_id'])) {
        $filtre_mesaji = "<p class='text-warning'>Arama yapılırken müşteri filtresi dikkate alınmaz. Tüm faturalar içinde arama yapılıyor.</p>";
    }
} 
// Arama yoksa müşteri filtresini kontrol et
elseif (isset($_GET['musteri_id']) && is_numeric($_GET['musteri_id'])) {
    $param_id_musteri = intval($_GET['musteri_id']);
    $sql_ana_fatura = "CALL sp_MusterininFaturalari_Listele(:id)"; 
} else {
    $sql_ana_fatura = "CALL sp_Faturalar_Listele()"; 
}

// <title> için sayfa başlığı
$sayfa_basligi = $sayfa_basligi_dinamik . " - Veteriner Kliniği";
if ($param_id_musteri && empty($arama_terimi_fatura)) {
    $sayfa_basligi = "Müşteriye Ait Faturalar - Veteriner Kliniği";
} elseif ($sayfa_basligi_dinamik === "Faturalar" && empty($arama_terimi_fatura)){
    $sayfa_basligi = "Fatura Listesi - Veteriner Kliniği";
}

include 'includes/header.php'; 

// Eğer müşteri filtresi varsa ve arama yapılmıyorsa, müşteri adını ÇEK ve H2 başlığını/filtre mesajını GÜNCELLE
if ($param_id_musteri !== null && empty($arama_terimi_fatura)) {
    try {
        $stmt_filtre_adi = $pdo->prepare("SELECT CONCAT(Adi, ' ', Soyadi) AS AdSoyad FROM Musteriler WHERE MusteriID = :id_val");
        $stmt_filtre_adi->bindParam(':id_val', $param_id_musteri, PDO::PARAM_INT);
        $stmt_filtre_adi->execute();
        $filtre_bilgisi = $stmt_filtre_adi->fetch();
        $stmt_filtre_adi->closeCursor();
        if ($filtre_bilgisi) {
            $sayfa_basligi_dinamik = htmlspecialchars($filtre_bilgisi['AdSoyad']) . " Adlı Müşterinin Faturaları";
            $filtre_mesaji = "<h4>" . htmlspecialchars($filtre_bilgisi['AdSoyad']) . " adlı müşterinin faturaları listeleniyor. <a href='faturalar.php' class='btn btn-sm btn-outline-secondary'><i class='fas fa-times-circle me-1'></i>Filtreyi Temizle</a></h4>";
        }
    } catch (PDOException $e) { 
        $filtre_mesaji = "<p class='text-danger'>Müşteri adı alınırken hata oluştu.</p>";
    }
}

$faturalar = [];
try {
    $stmt = $pdo->prepare($sql_ana_fatura);
    if (!empty($arama_terimi_fatura)) {
        $stmt->bindParam(':arama_terimi', $arama_terimi_fatura, PDO::PARAM_STR);
    } elseif ($param_id_musteri !== null) {
        $stmt->bindParam(':id', $param_id_musteri, PDO::PARAM_INT);
    }
    $stmt->execute();
    $faturalar = $stmt->fetchAll();
    $stmt->closeCursor();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger mt-3'>Faturalar listelenirken/aranırken bir hata oluştu: " . $e->getMessage() . "</div>";
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
            <a href="fatura_ekle.php" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Yeni Fatura Oluştur</a>
        </div>
    </div>

    <!-- Fatura Arama Formu -->
    <form action="faturalar.php" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-10 mb-2 mb-md-0">
                <input type="text" name="arama_fatura" class="form-control" placeholder="Fatura ID, Müşteri Adı/Soyadı, Hayvan Adı veya Ödeme Durumu Ara..." value="<?php echo htmlspecialchars($arama_terimi_fatura); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ara</button>
            </div>
        </div>
        <?php if (!empty($arama_terimi_fatura)): ?>
            <div class="row mt-2">
                <div class="col">
                    <a href="faturalar.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times-circle me-1"></i>Aramayı Temizle</a>
                </div>
            </div>
        <?php endif; ?>
    </form>
    <!-- Fatura Arama Formu Sonu -->

    <?php
    if (isset($_SESSION['mesaj_fatura'])) {
        echo $_SESSION['mesaj_fatura'];
        unset($_SESSION['mesaj_fatura']);
    }
    ?>

    <?php if (empty($faturalar) && !$e_var): ?>
        <div class="text-center p-5 border rounded bg-light shadow-sm">
            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
             <p class="lead">
                <?php if (!empty($arama_terimi_fatura)): ?>
                    Aradığınız kriterlere uygun fatura bulunamadı.
                <?php elseif ($param_id_musteri): ?>
                    Bu müşteriye ait fatura bulunmamaktadır.
                <?php else: ?>
                    Henüz kayıtlı fatura bulunmamaktadır.
                <?php endif; ?>
            </p>
            <?php if (empty($arama_terimi_fatura) && !$param_id_musteri): ?>
            <a href="fatura_ekle.php" class="btn btn-lg btn-success mt-3"><i class="fas fa-plus-circle me-2"></i> Yeni Fatura Oluşturun</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($faturalar)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Fatura ID</th>
                        <?php if (!$param_id_musteri || !empty($arama_terimi_fatura)): ?>
                            <th>Müşteri</th>
                        <?php endif; ?>
                        <th>Hayvan Adı</th>
                        <th>Fatura Tarihi</th>
                        <th>Toplam Tutar</th>
                        <th>Ödeme Durumu</th>
                        <th class="text-center" style="width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faturalar as $fatura): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($fatura['FaturaID']); ?></td>
                        <?php if (!$param_id_musteri || !empty($arama_terimi_fatura)): ?>
                            <td>
                                <?php 
                                if (isset($fatura['MusteriID']) && isset($fatura['Musteri'])) {
                                    echo '<a href="musteri_duzenle.php?id='.$fatura['MusteriID'].'">'.htmlspecialchars($fatura['Musteri']).'</a>';
                                } elseif (isset($fatura['Musteri'])) {
                                    echo htmlspecialchars($fatura['Musteri']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($fatura['HayvanAdi'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($fatura['FaturaTarihi']))); ?></td>
                        <td><?php echo htmlspecialchars(number_format($fatura['ToplamTutar'], 2, ',', '.')); ?> TL</td>
                        <td>
                            <span class="badge bg-<?php echo ($fatura['OdemeDurumu'] == 'Ödendi') ? 'success' : (($fatura['OdemeDurumu'] == 'Kısmi Ödendi') ? 'warning text-dark' : 'danger'); ?>">
                                <?php echo htmlspecialchars($fatura['OdemeDurumu']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                             <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonF_<?php echo $fatura['FaturaID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonF_<?php echo $fatura['FaturaID']; ?>">
                                    <li><a class="dropdown-item" href="fatura_goruntule.php?id=<?php echo $fatura['FaturaID']; ?>"><i class="fas fa-eye text-info me-2"></i>Görüntüle/Detay</a></li>
                                    <li><a class="dropdown-item" href="fatura_duzenle.php?id=<?php echo $fatura['FaturaID']; ?>"><i class="fas fa-edit text-primary me-2"></i>Düzenle (Ana Bilgi)</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="fatura_sil.php?id=<?php echo $fatura['FaturaID']; ?><?php echo ($param_id_musteri && empty($arama_terimi_fatura)) ? '&musteri_id='.$param_id_musteri : (isset($fatura['MusteriID']) ? '&musteri_id='.$fatura['MusteriID'] : '');?>" onclick="return confirm('Bu faturayı ve tüm detaylarını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.');"><i class="fas fa-trash-alt me-2"></i>Sil</a></li>
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