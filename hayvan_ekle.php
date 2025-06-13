<?php
$sayfa_basligi = "Yeni Hayvan Ekle - Veteriner Kliniği";
include 'includes/header.php'; // Veritabanı bağlantısı ve sayfa üstü

$mesaj = ""; // Başlangıçta mesaj boş

// Müşterileri çek (dropdown için)
$musteriler = [];
try {
    $stmt_musteriler = $pdo->query("CALL sp_Musteriler_Listele()");
    $musteriler = $stmt_musteriler->fetchAll();
    $stmt_musteriler->closeCursor();
} catch (PDOException $e) {
    $mesaj = "<div class='alert alert-danger'>Müşteri listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// Eğer musteri_id URL'den geldiyse, onu seçili hale getir
$secili_musteri_id = null;
if (isset($_GET['musteri_id']) && is_numeric($_GET['musteri_id'])) {
    $secili_musteri_id = intval($_GET['musteri_id']);
}


// Form gönderildi mi diye kontrol et (POST metodu ile)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Formdan gelen verileri al
    $musteri_id = filter_input(INPUT_POST, 'musteri_id', FILTER_VALIDATE_INT);
    $hayvan_adi = trim($_POST['hayvan_adi']);
    $turu = trim($_POST['turu']);
    $cinsi = trim($_POST['cinsi']);
    $dogum_tarihi = trim($_POST['dogum_tarihi']); // Tarih formatı YYYY-MM-DD olmalı
    $cinsiyet = trim($_POST['cinsiyet']);

    // Basit bir doğrulama
    if (empty($musteri_id) || empty($hayvan_adi) || empty($turu) || empty($cinsiyet)) {
        $mesaj = "<div class='alert alert-danger'>Müşteri, Hayvan Adı, Türü ve Cinsiyet alanları zorunludur.</div>";
    } elseif (!empty($dogum_tarihi) && !DateTime::createFromFormat('Y-m-d', $dogum_tarihi)) {
        $mesaj = "<div class='alert alert-danger'>Geçersiz doğum tarihi formatı. Lütfen YYYY-AA-GG formatında girin.</div>";
    } else {
        // Doğum tarihi boşsa NULL olarak ayarla
        $dogum_tarihi_db = !empty($dogum_tarihi) ? $dogum_tarihi : null;

        // Saklı yordamımızı kullanarak hayvan ekle
        $sql = "CALL sp_Hayvan_Ekle(:musteri_id, :adi, :turu, :cinsi, :dogum_tarihi, :cinsiyet)";
        try {
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
            $stmt->bindParam(':adi', $hayvan_adi, PDO::PARAM_STR);
            $stmt->bindParam(':turu', $turu, PDO::PARAM_STR);
            $stmt->bindParam(':cinsi', $cinsi, PDO::PARAM_STR); // Cinsi boş olabilir
            $stmt->bindParam(':dogum_tarihi', $dogum_tarihi_db, $dogum_tarihi_db === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':cinsiyet', $cinsiyet, PDO::PARAM_STR);

            $stmt->execute();
            $yeniHayvanID = $stmt->fetchColumn(); // sp_Hayvan_Ekle LAST_INSERT_ID() döndürüyor
            $stmt->closeCursor();

            $mesaj = "<div class='alert alert-success'>Yeni hayvan (ID: {$yeniHayvanID}) başarıyla eklendi. <a href='hayvanlar.php" . ($musteri_id ? "?musteri_id=".$musteri_id : "") . "' class='alert-link'>Hayvan Listesine Dön</a></div>";
            
            // Formu temizlemek için (isteğe bağlı, genellikle yönlendiririz)
            $_POST = array(); 
            $hayvan_adi = $turu = $cinsi = $dogum_tarihi = $cinsiyet = "";
            // $secili_musteri_id kalabilir, eğer aynı müşteriye başka hayvan eklemek isterse

        } catch (PDOException $e) {
            // Olası hatalar, örneğin var olmayan MusteriID vb.
            $mesaj = "<div class='alert alert-danger'>Hayvan eklenirken bir hata oluştu: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<div class="container mt-4">
    <h2>Yeni Hayvan Ekle</h2>

    <?php if (!empty($mesaj)) echo $mesaj; // Başarı veya hata mesajını göster ?>

    <form action="hayvan_ekle.php<?php echo $secili_musteri_id ? '?musteri_id='.$secili_musteri_id : ''; ?>" method="POST">
        <div class="mb-3">
            <label for="musteri_id" class="form-label">Sahibi (Müşteri) <span class="text-danger">*</span></label>
            <select class="form-select" id="musteri_id" name="musteri_id" required>
                <option value="">Lütfen bir müşteri seçin...</option>
                <?php foreach ($musteriler as $musteri_item): ?>
                    <option value="<?php echo htmlspecialchars($musteri_item['MusteriID']); ?>" 
                            <?php echo ($secili_musteri_id == $musteri_item['MusteriID'] || (isset($_POST['musteri_id']) && $_POST['musteri_id'] == $musteri_item['MusteriID'])) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($musteri_item['Adi'] . ' ' . $musteri_item['Soyadi'] . ' (Tel: ' . $musteri_item['TelefonNumarasi'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="hayvan_adi" class="form-label">Hayvanın Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="hayvan_adi" name="hayvan_adi" value="<?php echo isset($_POST['hayvan_adi']) ? htmlspecialchars($_POST['hayvan_adi']) : ''; ?>" required>
        </div>

        <div class="mb-3">
            <label for="turu" class="form-label">Türü (Örn: Kedi, Köpek) <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="turu" name="turu" value="<?php echo isset($_POST['turu']) ? htmlspecialchars($_POST['turu']) : ''; ?>" required>
        </div>

        <div class="mb-3">
            <label for="cinsi" class="form-label">Cinsi (Örn: Siyam, Labrador)</label>
            <input type="text" class="form-control" id="cinsi" name="cinsi" value="<?php echo isset($_POST['cinsi']) ? htmlspecialchars($_POST['cinsi']) : ''; ?>">
        </div>

        <div class="mb-3">
            <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
            <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi" value="<?php echo isset($_POST['dogum_tarihi']) ? htmlspecialchars($_POST['dogum_tarihi']) : ''; ?>">
            <div class="form-text">Eğer biliniyorsa, YYYY-AA-GG formatında.</div>
        </div>

        <div class="mb-3">
            <label for="cinsiyet" class="form-label">Cinsiyet <span class="text-danger">*</span></label>
            <select class="form-select" id="cinsiyet" name="cinsiyet" required>
                <option value="">Seçin...</option>
                <option value="Erkek" <?php echo (isset($_POST['cinsiyet']) && $_POST['cinsiyet'] == 'Erkek') ? 'selected' : ''; ?>>Erkek</option>
                <option value="Dişi" <?php echo (isset($_POST['cinsiyet']) && $_POST['cinsiyet'] == 'Dişi') ? 'selected' : ''; ?>>Dişi</option>
                <option value="Bilinmiyor" <?php echo (isset($_POST['cinsiyet']) && $_POST['cinsiyet'] == 'Bilinmiyor') ? 'selected' : ''; ?>>Bilinmiyor</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a href="hayvanlar.php<?php echo $secili_musteri_id ? '?musteri_id='.$secili_musteri_id : ''; ?>" class="btn btn-secondary">İptal Et ve Geri Dön</a>
    </form>
</div>

<?php
include 'includes/footer.php';
?>