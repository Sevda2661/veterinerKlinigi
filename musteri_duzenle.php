<?php
$sayfa_basligi = "Müşteri Düzenle - Veteriner Kliniği";
include 'includes/header.php'; // Veritabanı bağlantısı ve sayfa üstü

$mesaj = "";
$musteri = null; // Müşteri bilgilerini tutacak değişken

// 1. Düzenlenecek Müşterinin ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $musteri_id = intval($_GET['id']);

    // 2. Form Gönderilmişse (POST isteği ile) Güncelleme İşlemini Yap
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $adi = trim($_POST['adi']);
        $soyadi = trim($_POST['soyadi']);
        $telefon = trim($_POST['telefon']);
        $adres = trim($_POST['adres']);

        if (empty($adi) || empty($soyadi) || empty($telefon)) {
            $mesaj = "<div class='alert alert-danger'>Ad, Soyad ve Telefon numarası alanları zorunludur.</div>";
            // Hata durumunda formu tekrar doldurmak için güncel olmayan müşteri bilgilerini tekrar yükleyelim
            // Ancak bu POST verileri zaten formda gösteriliyor olacak.
            // $musteri = ['MusteriID' => $musteri_id, 'Adi' => $adi, 'Soyadi' => $soyadi, 'TelefonNumarasi' => $telefon, 'Adres' => $adres];
        } else {
            // Saklı yordamımızı kullanarak müşteri güncelle
            $sql_update = "CALL sp_Musteri_Guncelle(:musteri_id, :adi, :soyadi, :telefon, :adres)";
            try {
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':adi', $adi, PDO::PARAM_STR);
                $stmt_update->bindParam(':soyadi', $soyadi, PDO::PARAM_STR);
                $stmt_update->bindParam(':telefon', $telefon, PDO::PARAM_STR);
                $stmt_update->bindParam(':adres', $adres, PDO::PARAM_STR);
                
                $stmt_update->execute();
                $guncellenen_satir_sayisi = $stmt_update->fetchColumn(); // Saklı yordamdan dönen değeri al
                $stmt_update->closeCursor();

                if ($guncellenen_satir_sayisi > 0) {
                    $mesaj = "<div class='alert alert-success'>Müşteri bilgileri başarıyla güncellendi. <a href='musteriler.php' class='alert-link'>Müşteri Listesine Dön</a></div>";
                } else {
                    $mesaj = "<div class='alert alert-info'>Müşteri bilgilerinde herhangi bir değişiklik yapılmadı veya kayıt bulunamadı.</div>";
                }
                // Başarılı güncelleme sonrası, güncel verileri tekrar çekip formda göstermek için:
                // Veya doğrudan müşteri listesine yönlendirebiliriz: header("Location: musteriler.php"); exit;
                
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $mesaj = "<div class='alert alert-danger'>HATA: Bu telefon numarası başka bir müşteriye ait. Lütfen farklı bir numara girin.</div>";
                } else {
                    $mesaj = "<div class='alert alert-danger'>Müşteri güncellenirken bir hata oluştu: " . $e->getMessage() . "</div>";
                }
            }
        }
    }

    // 3. Sayfa ilk yüklendiğinde veya güncelleme sonrası, müşterinin mevcut bilgilerini çek
    // Eğer POST işlemi sonucu mesaj oluştuysa ve başarılıysa, tekrar çekmeye gerek yok, ama hata varsa veya ilk yükleme ise çekmeliyiz.
    // Daha basit bir mantıkla, her zaman çekebiliriz, POST'tan gelen veriler zaten formda öncelikli olacaktır.
    if (!$musteri || $mesaj) { // Eğer $musteri henüz set edilmediyse veya bir işlem mesajı varsa (güncel verileri gör)
        $sql_select = "CALL sp_Musteri_GetirByID(:musteri_id)";
        try {
            $stmt_select = $pdo->prepare($sql_select);
            $stmt_select->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
            $stmt_select->execute();
            $musteri = $stmt_select->fetch(PDO::FETCH_ASSOC);
            $stmt_select->closeCursor();

            if (!$musteri) {
                $mesaj = "<div class='alert alert-warning'>Düzenlenecek müşteri bulunamadı. <a href='musteriler.php' class='alert-link'>Listeye Dön</a></div>";
                // Müşteri bulunamazsa formu göstermenin anlamı yok, bu yüzden burada scripti durdurabiliriz veya formu gizleyebiliriz.
            }
        } catch (PDOException $e) {
            $mesaj = "<div class='alert alert-danger'>Müşteri bilgileri alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
            $musteri = null; // Hata durumunda müşteri null olsun ki form gösterilmesin
        }
    }

} else {
    // ID yoksa veya geçerli değilse
    $mesaj = "<div class='alert alert-danger'>Geçersiz müşteri ID'si. <a href='musteriler.php' class='alert-link'>Müşteri Listesine Dön</a></div>";
    // header("Location: musteriler.php"); // Kullanıcıyı listeye yönlendir
    // exit;
}
?>

<div class="container mt-4">
    <h2>Müşteri Bilgilerini Düzenle</h2>

    <?php if (!empty($mesaj)) echo $mesaj; // Başarı veya hata mesajını göster ?>

    <?php if ($musteri): // Sadece müşteri bilgileri başarıyla çekildiyse formu göster ?>
    <form action="musteri_duzenle.php?id=<?php echo htmlspecialchars($musteri['MusteriID']); ?>" method="POST">
        <div class="mb-3">
            <label for="adi" class="form-label">Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="adi" name="adi" value="<?php echo htmlspecialchars($musteri['Adi']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="soyadi" class="form-label">Soyadı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="soyadi" name="soyadi" value="<?php echo htmlspecialchars($musteri['Soyadi']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="telefon" class="form-label">Telefon Numarası <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="telefon" name="telefon" value="<?php echo htmlspecialchars($musteri['TelefonNumarasi']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="adres" class="form-label">Adres</label>
            <textarea class="form-control" id="adres" name="adres" rows="3"><?php echo htmlspecialchars($musteri['Adres']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Güncelle</button>
        <a href="musteriler.php" class="btn btn-secondary">İptal Et ve Geri Dön</a>
    </form>
    <?php elseif (empty($mesaj)): // Müşteri null ama henüz bir hata mesajı da yoksa (örn: ID gelmemişse ve yukarıda mesaj setlenmediyse) ?>
        <div class="alert alert-info">Düzenlenecek bir müşteri seçilmedi veya bulunamadı. Lütfen <a href="musteriler.php" class="alert-link">müşteri listesinden</a> bir seçim yapın.</div>
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>