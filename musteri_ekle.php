<?php
$sayfa_basligi = "Yeni Müşteri Ekle - Veteriner Kliniği";
include 'includes/header.php'; // Veritabanı bağlantısı ve sayfa üstü

$mesaj = ""; // Başlangıçta mesaj boş

// Form gönderildi mi diye kontrol et (POST metodu ile)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Formdan gelen verileri al ve temizle (güvenlik için)
    $adi = trim($_POST['adi']);
    $soyadi = trim($_POST['soyadi']);
    $telefon = trim($_POST['telefon']);
    $adres = trim($_POST['adres']);

    // Basit bir doğrulama (validation)
    if (empty($adi) || empty($soyadi) || empty($telefon)) {
        $mesaj = "<div class='alert alert-danger'>Ad, Soyad ve Telefon numarası alanları zorunludur.</div>";
    } else {
        // Saklı yordamımızı kullanarak müşteri ekle
        $sql = "CALL sp_Musteri_Ekle(:adi, :soyadi, :telefon, :adres)";
        try {
            $stmt = $pdo->prepare($sql);

            // Parametreleri bağla
            $stmt->bindParam(':adi', $adi, PDO::PARAM_STR);
            $stmt->bindParam(':soyadi', $soyadi, PDO::PARAM_STR);
            $stmt->bindParam(':telefon', $telefon, PDO::PARAM_STR);
            $stmt->bindParam(':adres', $adres, PDO::PARAM_STR);

            // Sorguyu çalıştır
            $stmt->execute();
            
            // Saklı yordam LAST_INSERT_ID() döndürüyordu, onu alabiliriz (isteğe bağlı)
            // $yeniMusteriID = $stmt->fetchColumn(); 

            $stmt->closeCursor(); // Birden fazla sorgu seti varsa veya OUT parametresi varsa önemli

            $mesaj = "<div class='alert alert-success'>Yeni müşteri başarıyla eklendi. <a href='musteriler.php' class='alert-link'>Müşteri Listesine Dön</a></div>";
            // Formu temizlemek için değerleri sıfırla (isteğe bağlı, genellikle kullanıcıyı yönlendiririz)
            $_POST = array(); 
            $adi = $soyadi = $telefon = $adres = "";

        } catch (PDOException $e) {
            // Telefon numarası UNIQUE olduğu için, aynı numara girilirse hata verebilir.
            if ($e->errorInfo[1] == 1062) { // 1062 MySQL duplicate entry error code
                $mesaj = "<div class='alert alert-danger'>HATA: Bu telefon numarası zaten kayıtlı. Lütfen farklı bir numara girin.</div>";
            } else {
                $mesaj = "<div class='alert alert-danger'>Müşteri eklenirken bir hata oluştu: " . $e->getMessage() . "</div>";
            }
        }
    }
}
?>

<div class="container mt-4">
    <h2>Yeni Müşteri Ekle</h2>

    <?php if (!empty($mesaj)) echo $mesaj; // Başarı veya hata mesajını göster ?>

    <form action="musteri_ekle.php" method="POST">
        <div class="mb-3">
            <label for="adi" class="form-label">Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="adi" name="adi" value="<?php echo isset($_POST['adi']) ? htmlspecialchars($_POST['adi']) : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label for="soyadi" class="form-label">Soyadı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="soyadi" name="soyadi" value="<?php echo isset($_POST['soyadi']) ? htmlspecialchars($_POST['soyadi']) : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label for="telefon" class="form-label">Telefon Numarası <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="telefon" name="telefon" placeholder="Örn: 05xxxxxxxxx" value="<?php echo isset($_POST['telefon']) ? htmlspecialchars($_POST['telefon']) : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label for="adres" class="form-label">Adres</label>
            <textarea class="form-control" id="adres" name="adres" rows="3"><?php echo isset($_POST['adres']) ? htmlspecialchars($_POST['adres']) : ''; ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a href="musteriler.php" class="btn btn-secondary">İptal Et ve Geri Dön</a>
    </form>
</div>

<?php
include 'includes/footer.php';
?>