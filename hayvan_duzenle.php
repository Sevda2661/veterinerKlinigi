<?php
$sayfa_basligi = "Hayvan Bilgilerini Düzenle - Veteriner Kliniği";
include 'includes/header.php';

$mesaj = "";
$hayvan = null; // Hayvan bilgilerini tutacak değişken
$hayvan_id = null;

// Müşterileri çek (dropdown için)
$musteriler_listesi = [];
try {
    $stmt_musteriler = $pdo->query("CALL sp_Musteriler_Listele()");
    $musteriler_listesi = $stmt_musteriler->fetchAll();
    $stmt_musteriler->closeCursor();
} catch (PDOException $e) {
    $mesaj = "<div class='alert alert-danger'>Müşteri listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// 1. Düzenlenecek Hayvanın ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $hayvan_id = intval($_GET['id']);

    // 2. Form Gönderilmişse (POST isteği ile) Güncelleme İşlemini Yap
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $guncellenecek_musteri_id = filter_input(INPUT_POST, 'musteri_id', FILTER_VALIDATE_INT);
        $hayvan_adi = trim($_POST['hayvan_adi']);
        $turu = trim($_POST['turu']);
        $cinsi = trim($_POST['cinsi']);
        $dogum_tarihi = trim($_POST['dogum_tarihi']);
        $cinsiyet = trim($_POST['cinsiyet']);

        if (empty($guncellenecek_musteri_id) || empty($hayvan_adi) || empty($turu) || empty($cinsiyet)) {
            $mesaj = "<div class='alert alert-danger'>Müşteri, Hayvan Adı, Türü ve Cinsiyet alanları zorunludur.</div>";
            // Hata durumunda, GET ile gelen ID'ye ait orijinal verileri formda tutmak için tekrar çekmeyelim,
            // POST edilen veriler zaten formda kalacak.
            // $hayvan değişkenini mevcut $hayvan_id ile doldurmak gerekebilir.
        } elseif (!empty($dogum_tarihi) && !DateTime::createFromFormat('Y-m-d', $dogum_tarihi)) {
            $mesaj = "<div class='alert alert-danger'>Geçersiz doğum tarihi formatı. Lütfen YYYY-AA-GG formatında girin.</div>";
        } else {
            $dogum_tarihi_db = !empty($dogum_tarihi) ? $dogum_tarihi : null;

            // Saklı yordamımızı kullanarak hayvan güncelle
            // sp_Hayvan_Guncelle(IN p_HayvanID INT, IN p_MusteriID INT, IN p_Adi VARCHAR(100), IN p_Turu VARCHAR(50), IN p_Cinsi VARCHAR(50), IN p_DogumTarihi DATE, IN p_Cinsiyet VARCHAR(10))
            $sql_update = "CALL sp_Hayvan_Guncelle(:hayvan_id, :musteri_id, :adi, :turu, :cinsi, :dogum_tarihi, :cinsiyet)";
            try {
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':hayvan_id', $hayvan_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':musteri_id', $guncellenecek_musteri_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':adi', $hayvan_adi, PDO::PARAM_STR);
                $stmt_update->bindParam(':turu', $turu, PDO::PARAM_STR);
                $stmt_update->bindParam(':cinsi', $cinsi, PDO::PARAM_STR);
                $stmt_update->bindParam(':dogum_tarihi', $dogum_tarihi_db, $dogum_tarihi_db === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt_update->bindParam(':cinsiyet', $cinsiyet, PDO::PARAM_STR);
                
                $stmt_update->execute();
                $guncellenen_satir_sayisi = $stmt_update->fetchColumn();
                $stmt_update->closeCursor();

                if ($guncellenen_satir_sayisi > 0) {
                    $mesaj = "<div class='alert alert-success'>Hayvan bilgileri başarıyla güncellendi. <a href='hayvanlar.php' class='alert-link'>Hayvan Listesine Dön</a></div>";
                } else {
                    $mesaj = "<div class='alert alert-info'>Hayvan bilgilerinde herhangi bir değişiklik yapılmadı veya kayıt bulunamadı.</div>";
                }
                // Başarılı güncelleme sonrası, güncel verileri tekrar çekip formda göstermek için
                // $hayvan = null; // Bu satır $hayvan'ı tekrar çekmeye zorlar.
            } catch (PDOException $e) {
                $mesaj = "<div class='alert alert-danger'>Hayvan güncellenirken bir hata oluştu: " . $e->getMessage() . "</div>";
            }
        }
        // Hata veya başarı sonrası, formun güncel verilerle dolması için $hayvan verisini (eğer $mesaj varsa) tekrar çekmeliyiz
        // veya POST'tan gelen verileri $hayvan arrayine atamalıyız.
        // Şimdilik, eğer $mesaj varsa, aşağıdaki $hayvan çekme bloğu çalışacak ve formu dolduracak.
        if(!empty($mesaj)){ // Eğer bir mesaj oluştuysa (hata veya başarı)
             $hayvan = [ // Formun POST edilen değerlerle dolması için
                'HayvanID' => $hayvan_id,
                'MusteriID' => $guncellenecek_musteri_id,
                'Adi' => $hayvan_adi,
                'Turu' => $turu,
                'Cinsi' => $cinsi,
                'DogumTarihi' => $dogum_tarihi,
                'Cinsiyet' => $cinsiyet
            ];
        }

    }

    // 3. Sayfa ilk yüklendiğinde (GET) veya POST sonrası güncel bilgileri çek (eğer $hayvan henüz set edilmediyse veya mesaj yoksa)
    // Eğer POST işlemi sonucu bir mesaj oluştuysa ve $hayvan yukarıda POST verileriyle set edildiyse, tekrar çekmeye gerek yok.
    if ($hayvan === null || (empty($mesaj) && $_SERVER["REQUEST_METHOD"] != "POST")) {
        // Saklı yordam sp_Hayvan_GetirByID(IN p_HayvanID INT)
        // Bu yordam MusteriAdi ve MusteriSoyadi'nı da getiriyor, bu güzel.
        $sql_select = "CALL sp_Hayvan_GetirByID(:hayvan_id)";
        try {
            $stmt_select = $pdo->prepare($sql_select);
            $stmt_select->bindParam(':hayvan_id', $hayvan_id, PDO::PARAM_INT);
            $stmt_select->execute();
            $hayvan = $stmt_select->fetch(PDO::FETCH_ASSOC); // Tek bir hayvan kaydı
            $stmt_select->closeCursor();

            if (!$hayvan) {
                $mesaj = "<div class='alert alert-warning'>Düzenlenecek hayvan bulunamadı. <a href='hayvanlar.php' class='alert-link'>Listeye Dön</a></div>";
            }
        } catch (PDOException $e) {
            $mesaj = "<div class='alert alert-danger'>Hayvan bilgileri alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
            $hayvan = null;
        }
    }

} else {
    $mesaj = "<div class='alert alert-danger'>Geçersiz hayvan ID'si. <a href='hayvanlar.php' class='alert-link'>Hayvan Listesine Dön</a></div>";
}
?>

<div class="container mt-4">
    <h2>Hayvan Bilgilerini Düzenle</h2>

    <?php if (!empty($mesaj)) echo $mesaj; ?>

    <?php if ($hayvan): // Sadece hayvan bilgileri başarıyla çekildiyse formu göster ?>
    <form action="hayvan_duzenle.php?id=<?php echo htmlspecialchars($hayvan['HayvanID']); ?>" method="POST">
        <div class="mb-3">
            <label for="musteri_id" class="form-label">Sahibi (Müşteri) <span class="text-danger">*</span></label>
            <select class="form-select" id="musteri_id" name="musteri_id" required>
                <option value="">Lütfen bir müşteri seçin...</option>
                <?php foreach ($musteriler_listesi as $musteri_item): ?>
                    <option value="<?php echo htmlspecialchars($musteri_item['MusteriID']); ?>" 
                            <?php echo ($hayvan['MusteriID'] == $musteri_item['MusteriID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($musteri_item['Adi'] . ' ' . $musteri_item['Soyadi'] . ' (Tel: ' . $musteri_item['TelefonNumarasi'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="hayvan_adi" class="form-label">Hayvanın Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="hayvan_adi" name="hayvan_adi" value="<?php echo htmlspecialchars($hayvan['Adi']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="turu" class="form-label">Türü <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="turu" name="turu" value="<?php echo htmlspecialchars($hayvan['Turu']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="cinsi" class="form-label">Cinsi</label>
            <input type="text" class="form-control" id="cinsi" name="cinsi" value="<?php echo htmlspecialchars($hayvan['Cinsi']); ?>">
        </div>

        <div class="mb-3">
            <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
            <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi" value="<?php echo htmlspecialchars($hayvan['DogumTarihi'] ? date('Y-m-d', strtotime($hayvan['DogumTarihi'])) : ''); ?>">
        </div>

        <div class="mb-3">
            <label for="cinsiyet" class="form-label">Cinsiyet <span class="text-danger">*</span></label>
            <select class="form-select" id="cinsiyet" name="cinsiyet" required>
                <option value="">Seçin...</option>
                <option value="Erkek" <?php echo ($hayvan['Cinsiyet'] == 'Erkek') ? 'selected' : ''; ?>>Erkek</option>
                <option value="Dişi" <?php echo ($hayvan['Cinsiyet'] == 'Dişi') ? 'selected' : ''; ?>>Dişi</option>
                <option value="Bilinmiyor" <?php echo ($hayvan['Cinsiyet'] == 'Bilinmiyor') ? 'selected' : ''; ?>>Bilinmiyor</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Güncelle</button>
        <a href="hayvanlar.php" class="btn btn-secondary">İptal Et ve Geri Dön</a>
    </form>
    <?php elseif (empty($mesaj)): ?>
        <div class="alert alert-info">Düzenlenecek bir hayvan seçilmedi veya bulunamadı. Lütfen <a href="hayvanlar.php" class="alert-link">hayvan listesinden</a> bir seçim yapın.</div>
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>