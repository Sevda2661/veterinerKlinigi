<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Yeni Randevu Oluştur - Veteriner Kliniği";
include 'includes/header.php';

// Formdan gelen verileri tutmak için değişkenler (sayfa yeniden yüklendiğinde değerleri korumak için)
$form_data = $_SESSION['form_data_randevu'] ?? [
    'hayvan_id' => '',
    'veteriner_id' => '',
    'randevu_tarihi' => '',
    'randevu_saati' => '',
    'randevu_nedeni' => ''
];
unset($_SESSION['form_data_randevu']); // Veriyi kullandıktan sonra temizle

// Hayvanları çek (dropdown için)
$hayvanlar_listesi = [];
try {
    $stmt_hayvanlar = $pdo->query("CALL sp_Hayvanlar_Listele()"); // Bu yordam MusteriAdi ve MusteriSoyadi'nı da getiriyor
    $hayvanlar_listesi = $stmt_hayvanlar->fetchAll();
    $stmt_hayvanlar->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_randevu_ekle'] = "<div class='alert alert-danger'>Hayvan listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// Veterinerleri çek (dropdown için)
$veterinerler_listesi = [];
try {
    $stmt_veterinerler = $pdo->query("CALL sp_Veterinerler_Listele()");
    $veterinerler_listesi = $stmt_veterinerler->fetchAll();
    $stmt_veterinerler->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_randevu_ekle'] = "<div class='alert alert-danger'>Veteriner listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// Form gönderildi mi diye kontrol et
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hayvan_id = filter_input(INPUT_POST, 'hayvan_id', FILTER_VALIDATE_INT);
    $veteriner_id = filter_input(INPUT_POST, 'veteriner_id', FILTER_VALIDATE_INT);
    $randevu_tarihi = trim($_POST['randevu_tarihi']); // YYYY-MM-DD
    $randevu_saati = trim($_POST['randevu_saati']);   // HH:MM veya HH:MM:SS
    $randevu_nedeni = trim($_POST['randevu_nedeni']);

    // POST edilen verileri session'a kaydet (hata durumunda formu dolu tutmak için)
    $_SESSION['form_data_randevu'] = [
        'hayvan_id' => $hayvan_id,
        'veteriner_id' => $veteriner_id,
        'randevu_tarihi' => $randevu_tarihi,
        'randevu_saati' => $randevu_saati,
        'randevu_nedeni' => $randevu_nedeni
    ];

    // Doğrulama
    $hatalar = [];
    if (empty($hayvan_id)) $hatalar[] = "Hayvan seçimi zorunludur.";
    if (empty($veteriner_id)) $hatalar[] = "Veteriner seçimi zorunludur.";
    if (empty($randevu_tarihi)) $hatalar[] = "Randevu tarihi zorunludur.";
    if (empty($randevu_saati)) $hatalar[] = "Randevu saati zorunludur.";
    
    // Tarih ve saat format kontrolü
    $tarih_obj = DateTime::createFromFormat('Y-m-d', $randevu_tarihi);
    if (!$tarih_obj || $tarih_obj->format('Y-m-d') !== $randevu_tarihi) {
        $hatalar[] = "Geçersiz randevu tarihi formatı. (YYYY-AA-GG)";
    } elseif ($tarih_obj < new DateTime('today') && $randevu_tarihi != (new DateTime('today'))->format('Y-m-d')) {
        // Eğer tarih bugünden eskiyse (ve bugün değilse) hata ver
        $hatalar[] = "Randevu tarihi geçmiş bir tarih olamaz.";
    }


    $saat_obj = DateTime::createFromFormat('H:i', $randevu_saati); // Sadece saat ve dakika
    if (!$saat_obj || $saat_obj->format('H:i') !== $randevu_saati) {
         $saat_obj_saniye_ile = DateTime::createFromFormat('H:i:s', $randevu_saati); // Saniye ile de deneyelim
         if (!$saat_obj_saniye_ile || $saat_obj_saniye_ile->format('H:i:s') !== $randevu_saati) {
            $hatalar[] = "Geçersiz randevu saati formatı. (SS:DD veya SS:DD:ss)";
         } else {
             $randevu_saati_db = $saat_obj_saniye_ile->format('H:i:s'); // Veritabanına saniyeli formatta kaydet
         }
    } else {
        $randevu_saati_db = $saat_obj->format('H:i:s'); // Veritabanına saniyeli formatta kaydet
    }


    if (!empty($hatalar)) {
        $_SESSION['mesaj_randevu_ekle'] = "<div class='alert alert-danger'><ul>";
        foreach ($hatalar as $hata) {
            $_SESSION['mesaj_randevu_ekle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
        }
        $_SESSION['mesaj_randevu_ekle'] .= "</ul></div>";
        header("Location: randevu_ekle.php");
        exit;
    } else {
        // Saklı yordam: sp_Randevu_Ekle(IN p_HayvanID INT, IN p_VeterinerID INT, IN p_RandevuTarihi DATE, IN p_RandevuSaati TIME, IN p_RandevuNedeni TEXT)
        $sql = "CALL sp_Randevu_Ekle(:hayvan_id, :veteriner_id, :randevu_tarihi, :randevu_saati, :randevu_nedeni)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':hayvan_id', $hayvan_id, PDO::PARAM_INT);
            $stmt->bindParam(':veteriner_id', $veteriner_id, PDO::PARAM_INT);
            $stmt->bindParam(':randevu_tarihi', $randevu_tarihi, PDO::PARAM_STR);
            $stmt->bindParam(':randevu_saati', $randevu_saati_db, PDO::PARAM_STR); // Düzeltilmiş saat formatı
            $stmt->bindParam(':randevu_nedeni', $randevu_nedeni, PDO::PARAM_STR);
            
            $stmt->execute();
            $yeniRandevuID = $stmt->fetchColumn();
            $stmt->closeCursor();

            unset($_SESSION['form_data_randevu']);
            $_SESSION['mesaj_randevu'] = "<div class='alert alert-success'>Yeni randevu (ID: {$yeniRandevuID}) başarıyla oluşturuldu.</div>";
            header("Location: randevular.php");
            exit;

        } catch (PDOException $e) {
            // UNIQUE constraint (HayvanID, VeterinerID, RandevuTarihi, RandevuSaati) hatası
            if ($e->errorInfo[1] == 1062) { // UK_Randevu
                $_SESSION['mesaj_randevu_ekle'] = "<div class='alert alert-danger'>HATA: Bu hayvan için bu veterinerle belirtilen tarih ve saatte zaten bir randevu mevcut.</div>";
            } else {
                $_SESSION['mesaj_randevu_ekle'] = "<div class='alert alert-danger'>Randevu oluşturulurken bir veritabanı hatası oluştu: " . $e->getMessage() . "</div>";
            }
            header("Location: randevu_ekle.php");
            exit;
        }
    }
}
?>

<div class="container mt-4">
    <h2>Yeni Randevu Oluştur</h2>

    <?php
    if (isset($_SESSION['mesaj_randevu_ekle'])) {
        echo $_SESSION['mesaj_randevu_ekle'];
        unset($_SESSION['mesaj_randevu_ekle']);
    }
    ?>

    <form action="randevu_ekle.php" method="POST">
        <div class="mb-3">
            <label for="hayvan_id" class="form-label">Hayvan Seçin <span class="text-danger">*</span></label>
            <select class="form-select" id="hayvan_id" name="hayvan_id" required>
                <option value="">Hayvan Seçiniz...</option>
                <?php foreach ($hayvanlar_listesi as $hayvan_item): ?>
                    <option value="<?php echo htmlspecialchars($hayvan_item['HayvanID']); ?>" 
                            <?php echo ($form_data['hayvan_id'] == $hayvan_item['HayvanID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($hayvan_item['Adi'] . " (Sahibi: " . $hayvan_item['MusteriAdi'] . " " . $hayvan_item['MusteriSoyadi'] . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="veteriner_id" class="form-label">Veteriner Seçin <span class="text-danger">*</span></label>
            <select class="form-select" id="veteriner_id" name="veteriner_id" required>
                <option value="">Veteriner Seçiniz...</option>
                <?php foreach ($veterinerler_listesi as $veteriner_item): ?>
                    <option value="<?php echo htmlspecialchars($veteriner_item['VeterinerID']); ?>"
                            <?php echo ($form_data['veteriner_id'] == $veteriner_item['VeterinerID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($veteriner_item['Adi'] . ' ' . $veteriner_item['Soyadi']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="randevu_tarihi" class="form-label">Randevu Tarihi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="randevu_tarihi" name="randevu_tarihi" value="<?php echo htmlspecialchars($form_data['randevu_tarihi']); ?>" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="randevu_saati" class="form-label">Randevu Saati <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="randevu_saati" name="randevu_saati" value="<?php echo htmlspecialchars($form_data['randevu_saati']); ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="randevu_nedeni" class="form-label">Randevu Nedeni</label>
            <textarea class="form-control" id="randevu_nedeni" name="randevu_nedeni" rows="3"><?php echo htmlspecialchars($form_data['randevu_nedeni']); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Randevu Oluştur</button>
        <a href="randevular.php" class="btn btn-secondary">İptal</a>
    </form>
</div>

<?php
include 'includes/footer.php';
?>