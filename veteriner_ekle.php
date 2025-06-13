<?php
// Session'ı başlat (mesajları taşımak ve form değerlerini korumak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Yeni Veteriner Ekle - Veteriner Kliniği";
include 'includes/header.php'; // Veritabanı bağlantısı ve sayfa üstü

// Formdan gelen verileri tutmak için değişkenler (sayfa yeniden yüklendiğinde değerleri korumak için)
$form_adi = $_SESSION['form_data']['adi'] ?? '';
$form_soyadi = $_SESSION['form_data']['soyadi'] ?? '';
$form_telefon = $_SESSION['form_data']['telefon'] ?? '';
unset($_SESSION['form_data']); // Veriyi kullandıktan sonra temizle

// Form gönderildi mi diye kontrol et (POST metodu ile)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Formdan gelen verileri al ve temizle (güvenlik için)
    $adi = trim($_POST['adi']);
    $soyadi = trim($_POST['soyadi']);
    $telefon = trim($_POST['telefon']);

    // POST edilen verileri session'a kaydet (hata durumunda formu dolu tutmak için)
    $_SESSION['form_data'] = ['adi' => $adi, 'soyadi' => $soyadi, 'telefon' => $telefon];

    // Basit bir doğrulama (validation)
    if (empty($adi) || empty($soyadi) || empty($telefon)) {
        $_SESSION['mesaj_veteriner'] = "<div class='alert alert-danger'>Ad, Soyad ve Telefon numarası alanları zorunludur.</div>";
        header("Location: veteriner_ekle.php"); // Hata mesajı ile sayfayı yeniden yükle
        exit;
    } else {
        // Saklı yordamımızı kullanarak veteriner ekle
        // sp_Veteriner_Ekle(IN p_Adi VARCHAR(100), IN p_Soyadi VARCHAR(100), IN p_TelefonNumarasi VARCHAR(20))
        $sql = "CALL sp_Veteriner_Ekle(:adi, :soyadi, :telefon)";
        try {
            $stmt = $pdo->prepare($sql);

            // Parametreleri bağla
            $stmt->bindParam(':adi', $adi, PDO::PARAM_STR);
            $stmt->bindParam(':soyadi', $soyadi, PDO::PARAM_STR);
            $stmt->bindParam(':telefon', $telefon, PDO::PARAM_STR);

            // Sorguyu çalıştır
            $stmt->execute();
            
            $yeniVeterinerID = $stmt->fetchColumn(); // Saklı yordam LAST_INSERT_ID() döndürüyor
            $stmt->closeCursor(); 

            unset($_SESSION['form_data']); // Başarılı ekleme sonrası form verilerini temizle
            $_SESSION['mesaj_veteriner'] = "<div class='alert alert-success'>Yeni veteriner (ID: {$yeniVeterinerID}) başarıyla eklendi. <a href='veterinerler.php' class='alert-link'>Veteriner Listesine Dön</a></div>";
            header("Location: veterinerler.php"); // Başarı mesajı ile listeye yönlendir
            exit;

        } catch (PDOException $e) {
            // Telefon numarası UNIQUE olduğu için, aynı numara girilirse hata verebilir.
            if ($e->errorInfo[1] == 1062) { // 1062 MySQL duplicate entry error code
                $_SESSION['mesaj_veteriner'] = "<div class='alert alert-danger'>HATA: Bu telefon numarası zaten başka bir veterinere kayıtlı. Lütfen farklı bir numara girin.</div>";
            } else {
                $_SESSION['mesaj_veteriner'] = "<div class='alert alert-danger'>Veteriner eklenirken bir hata oluştu: " . $e->getMessage() . "</div>";
            }
            header("Location: veteriner_ekle.php"); // Hata mesajı ile sayfayı yeniden yükle
            exit;
        }
    }
}
?>

<div class="container mt-4">
    <h2>Yeni Veteriner Ekle</h2>

    <?php
    // Session mesajlarını göstermek için
    if (isset($_SESSION['mesaj_veteriner'])) {
        echo $_SESSION['mesaj_veteriner'];
        unset($_SESSION['mesaj_veteriner']); // Mesajı gösterdikten sonra sil
    }
    ?>

    <form action="veteriner_ekle.php" method="POST">
        <div class="mb-3">
            <label for="adi" class="form-label">Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="adi" name="adi" value="<?php echo htmlspecialchars($form_adi); ?>" required>
        </div>
        <div class="mb-3">
            <label for="soyadi" class="form-label">Soyadı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="soyadi" name="soyadi" value="<?php echo htmlspecialchars($form_soyadi); ?>" required>
        </div>
        <div class="mb-3">
            <label for="telefon" class="form-label">Telefon Numarası <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="telefon" name="telefon" placeholder="Örn: 05xxxxxxxxx" value="<?php echo htmlspecialchars($form_telefon); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a href="veterinerler.php" class="btn btn-secondary">İptal Et ve Geri Dön</a>
    </form>
</div>

<?php
include 'includes/footer.php';
?>