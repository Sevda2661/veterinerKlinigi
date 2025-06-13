<?php
// Session mesajlarını almak için session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi_dinamik = "Randevular"; // H2 için varsayılan
$filtre_mesaji = "";
$arama_terimi_randevu = '';
$param_id = null; // Filtreleme ID'si (hayvan veya veteriner)
$sql_ana = ""; // Kullanılacak ana SQL sorgusu
$filtre_tipi = ''; // 'hayvan' veya 'veteriner'
$filtre_bilgisi = null; // Filtrelenen varlığın bilgisini tutmak için
$e_var = null; // Hata kontrolü için

// Önce aramayı kontrol et
if (isset($_GET['arama_randevu']) && !empty(trim($_GET['arama_randevu']))) {
    $arama_terimi_randevu = trim($_GET['arama_randevu']);
    $sql_ana = "CALL sp_Randevular_Ara(:arama_terimi)";
    $sayfa_basligi_dinamik = "'" . htmlspecialchars($arama_terimi_randevu) . "' için Arama Sonuçları";
    if (isset($_GET['hayvan_id']) || isset($_GET['veteriner_id'])) {
        $filtre_mesaji = "<p class='text-warning'>Arama yapılırken hayvan/veteriner filtresi dikkate alınmaz. Tüm randevular içinde arama yapılıyor.</p>";
    }
} 
// Arama yoksa filtreleri kontrol et
elseif (isset($_GET['hayvan_id']) && is_numeric($_GET['hayvan_id'])) {
    $param_id = intval($_GET['hayvan_id']);
    $sql_ana = "CALL sp_HayvaninRandevulari_Listele(:id)";
    $filtre_tipi = 'hayvan';
    // Hayvan adını çekme işlemi header.php'den sonra yapılacak
} 
elseif (isset($_GET['veteriner_id']) && is_numeric($_GET['veteriner_id'])) {
    $param_id = intval($_GET['veteriner_id']);
    $sql_ana = "CALL sp_VeterinerinRandevulari_Listele(:id)";
    $filtre_tipi = 'veteriner';
    // Veteriner adını çekme işlemi header.php'den sonra yapılacak
} else {
    $sql_ana = "CALL sp_Randevular_Listele()"; // Varsayılan
}

// <title> için sayfa başlığını ayarla
$sayfa_basligi = $sayfa_basligi_dinamik . " - Veteriner Kliniği";
include 'includes/header.php'; // $pdo BURADA DAHİL EDİLİYOR

// Eğer filtre varsa ve arama yapılmıyorsa, filtre adını ÇEK ve H2 başlığını/filtre mesajını GÜNCELLE
if ($param_id !== null && empty($arama_terimi_randevu)) {
    try {
        if ($filtre_tipi === 'hayvan') {
            $stmt_filtre_adi = $pdo->prepare("SELECT Adi FROM Hayvanlar WHERE HayvanID = :id_val");
        } elseif ($filtre_tipi === 'veteriner') {
            $stmt_filtre_adi = $pdo->prepare("SELECT CONCAT(Adi, ' ', Soyadi) AS AdSoyad FROM Veterinerler WHERE VeterinerID = :id_val");
        }
        
        if (isset($stmt_filtre_adi)) {
            $stmt_filtre_adi->bindParam(':id_val', $param_id, PDO::PARAM_INT);
            $stmt_filtre_adi->execute();
            $filtre_bilgisi = $stmt_filtre_adi->fetch();
            $stmt_filtre_adi->closeCursor();

            if ($filtre_bilgisi) {
                $filtre_adi_goster = ($filtre_tipi === 'hayvan') ? $filtre_bilgisi['Adi'] : $filtre_bilgisi['AdSoyad'];
                $sayfa_basligi_dinamik = htmlspecialchars($filtre_adi_goster) . " Adlı " . ($filtre_tipi === 'hayvan' ? "Hayvanın" : "Veterinerin") . " Randevuları";
                $filtre_mesaji = "<h4>" . htmlspecialchars($filtre_adi_goster) . " adlı " . ($filtre_tipi === 'hayvan' ? "hayvanın" : "veterinerin") . " randevuları listeleniyor. <a href='randevular.php' class='btn btn-sm btn-secondary'>Tüm Randevuları/Aramayı Temizle</a></h4>";
            }
        }
    } catch (PDOException $e) { 
        $filtre_mesaji = "<p class='text-danger'>Filtre bilgisi alınırken hata oluştu.</p>";
    }
}

$randevular = [];
try {
    $stmt = $pdo->prepare($sql_ana);
    if (!empty($arama_terimi_randevu)) {
        $stmt->bindParam(':arama_terimi', $arama_terimi_randevu, PDO::PARAM_STR);
    } elseif ($param_id !== null) {
        $stmt->bindParam(':id', $param_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $randevular = $stmt->fetchAll();
    $stmt->closeCursor();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger mt-3'>Randevular listelenirken/aranırken bir hata oluştu: " . $e->getMessage() . "</div>";
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
            <a href="randevu_ekle.php" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Yeni Randevu Oluştur</a>
        </div>
    </div>

    <!-- Randevu Arama Formu -->
    <form action="randevular.php" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-10 mb-2 mb-md-0">
                <input type="text" name="arama_randevu" class="form-control" placeholder="Hayvan Adı, Veteriner Adı/Soyadı veya Randevu Nedeni Ara..." value="<?php echo htmlspecialchars($arama_terimi_randevu); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ara</button>
            </div>
        </div>
        <?php if (!empty($arama_terimi_randevu)): ?>
            <div class="row mt-2">
                <div class="col">
                    <a href="randevular.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times-circle me-1"></i>Aramayı Temizle</a>
                </div>
            </div>
        <?php endif; ?>
    </form>
    <!-- Randevu Arama Formu Sonu -->

    <?php
    if (isset($_SESSION['mesaj_randevu'])) {
        echo $_SESSION['mesaj_randevu'];
        unset($_SESSION['mesaj_randevu']);
    }
    ?>

    <?php if (empty($randevular) && !$e_var): ?>
        <div class="text-center p-5 border rounded bg-light shadow-sm">
            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
            <p class="lead">
                <?php if (!empty($arama_terimi_randevu)): ?>
                    Aradığınız kriterlere uygun randevu bulunamadı.
                <?php elseif ($param_id): ?>
                    Bu filtreye uygun randevu bulunmamaktadır.
                <?php else: ?>
                    Henüz kayıtlı randevu bulunmamaktadır.
                <?php endif; ?>
            </p>
            <?php if (empty($arama_terimi_randevu) && !$param_id): ?>
            <a href="randevu_ekle.php" class="btn btn-lg btn-success mt-3"><i class="fas fa-plus-circle me-2"></i> Yeni Randevu Oluşturun</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($randevular)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Hayvan Adı</th>
                        <th>Veteriner</th>
                        <th>Randevu Tarihi</th>
                        <th>Randevu Saati</th>
                        <th>Nedeni</th>
                        <th class="text-center" style="width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($randevular as $randevu): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($randevu['RandevuID']); ?></td>
                        <td>
                            <?php
                            // İlgili SP'lerin HayvanID ve HayvanAdi'nı getirdiğini varsayıyoruz.
                            if (isset($randevu['HayvanID']) && isset($randevu['HayvanAdi'])) {
                                echo '<a href="hayvan_duzenle.php?id='.$randevu['HayvanID'].'">'.htmlspecialchars($randevu['HayvanAdi']).'</a>';
                            } elseif (isset($randevu['HayvanAdi'])) {
                                echo htmlspecialchars($randevu['HayvanAdi']);
                            } elseif ($filtre_tipi === 'hayvan' && $filtre_bilgisi) {
                                echo htmlspecialchars($filtre_bilgisi['Adi']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            // İlgili SP'lerin VeterinerID ve VeterinerAdiSoyadi'nı getirdiğini varsayıyoruz.
                            if (isset($randevu['VeterinerID']) && isset($randevu['VeterinerAdiSoyadi'])) {
                                echo '<a href="veteriner_duzenle.php?id='.$randevu['VeterinerID'].'">'.htmlspecialchars($randevu['VeterinerAdiSoyadi']).'</a>';
                            } elseif (isset($randevu['VeterinerAdiSoyadi'])) {
                                echo htmlspecialchars($randevu['VeterinerAdiSoyadi']);
                            } elseif ($filtre_tipi === 'veteriner' && $filtre_bilgisi) {
                                echo htmlspecialchars($filtre_bilgisi['AdSoyad']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($randevu['RandevuTarihi']))); ?></td>
                        <td><?php echo htmlspecialchars(date('H:i', strtotime($randevu['RandevuSaati']))); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($randevu['RandevuNedeni'])); ?></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonR_<?php echo $randevu['RandevuID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonR_<?php echo $randevu['RandevuID']; ?>">
                                    <li><a class="dropdown-item" href="randevu_duzenle.php?id=<?php echo $randevu['RandevuID']; ?>"><i class="fas fa-edit text-primary me-2"></i>Düzenle</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="randevu_sil.php?id=<?php echo $randevu['RandevuID']; ?>" onclick="return confirm('Bu randevuyu silmek istediğinize emin misiniz?');"><i class="fas fa-trash-alt me-2"></i>Sil</a></li>
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