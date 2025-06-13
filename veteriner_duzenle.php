<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Veteriner Bilgilerini Düzenle - Veteriner Kliniği";
include 'includes/header.php';

$veteriner = null; // Veteriner bilgilerini tutacak değişken
$veteriner_id = null;

// Formdan gelen veya veritabanından çekilen verileri tutmak için
$form_values = [
    'adi' => '',
    'soyadi' => '',
    'telefon' => ''
];

// 1. Düzenlenecek Veterinerin ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $veteriner_id = intval($_GET['id']);

    // 2. Form Gönderilmişse (POST isteği ile) Güncelleme İşlemini Yap
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $adi = trim($_POST['adi']);
        $soyadi = trim($_POST['soyadi']);
        $telefon = trim($_POST['telefon']);

        // POST edilen verileri form değerleri için sakla (hata durumunda formu dolu tutmak için)
        $form_values = ['adi' => $adi, 'soyadi' => $soyadi, 'telefon' => $telefon];

        if (empty($adi) || empty($soyadi) || empty($telefon)) {
            $_SESSION['mesaj_veteriner_duzenle'] = "<div class='alert alert-danger'>Ad, Soyad ve Telefon numarası alanları zorunludur.</div>";
            // Hata durumunda $veteriner'i tekrar çekmeye gerek yok, $form_values kullanılacak
        } else {
            // Saklı yordamımızı kullanarak veteriner güncelle
            // sp_Veteriner_Guncelle(IN p_VeterinerID INT, IN p_Adi VARCHAR(100), IN p_Soyadi VARCHAR(100), IN p_TelefonNumarasi VARCHAR(20))
            $sql_update = "CALL sp_Veteriner_Guncelle(:veteriner_id, :adi, :soyadi, :telefon)";
            try {
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':veteriner_id', $veteriner_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':adi', $adi, PDO::PARAM_STR);
                $stmt_update->bindParam(':soyadi', $soyadi, PDO::PARAM_STR);
                $stmt_update->bindParam(':telefon', $telefon, PDO::PARAM_STR);
                
                $stmt_update->execute();
                $guncellenen_satir_sayisi = $stmt_update->fetchColumn();
                $stmt_update->closeCursor();

                if ($guncellenen_satir_sayisi > 0) {
                    $_SESSION['mesaj_veteriner'] = "<div class='alert alert-success'>Veteriner bilgileri (ID: {$veteriner_id}) başarıyla güncellendi.</div>";
                    header("Location: veterinerler.php");
                    exit;
                } else {
                    $_SESSION['mesaj_veteriner_duzenle'] = "<div class='alert alert-info'>Veteriner bilgilerinde herhangi bir değişiklik yapılmadı veya kayıt bulunamadı.</div>";
                }
                // Başarılı güncelleme sonrası, güncel verileri tekrar çekip formda göstermek için:
                // $veteriner = null; // Bu satır $veteriner'ı tekrar çekmeye zorlar veya $form_values'u güncelleyebiliriz.
                // Şimdilik, mesaj oluştuğu için sayfa yeniden yüklenirse $form_values güncel olacak.
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry for phone
                    $_SESSION['mesaj_veteriner_duzenle'] = "<div class='alert alert-danger'>HATA: Bu telefon numarası başka bir veterinere ait. Lütfen farklı bir numara girin.</div>";
                } else {
                    $_SESSION['mesaj_veteriner_duzenle'] = "<div class='alert alert-danger'>Veteriner güncellenirken bir hata oluştu: " . $e->getMessage() . "</div>";
                }
            }
        }
         // Mesaj varsa ve yönlendirme yapılmadıysa, sayfa yeniden yüklenecek ve mesaj gösterilecek
         // Bu durumda $form_values zaten POST'tan gelen değerleri içeriyor olacak.
    }

    // 3. Sayfa ilk yüklendiğinde (GET) veya POST sonrası (eğer hata varsa ve $veteriner henüz yüklenmediyse) veteriner bilgilerini çek
    // Eğer form_values POST'tan dolduysa, önceliği ona verelim.
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_SESSION['mesaj_veteriner_duzenle'])) {
        // Sadece GET isteğinde veya POST sonrası mesaj yoksa (başarılı güncelleme ve yönlendirme olduysa buraya gelinmez)
        // veya POST sonrası hata mesajı var ama $form_values'u kullanmak yerine DB'den çekmek istenirse.
        // Mevcut mantık: GET'te DB'den çek. POST'ta hata varsa $form_values'u kullan.
        
        $sql_select = "CALL sp_Veteriner_GetirByID(:veteriner_id)";
        try {
            $stmt_select = $pdo->prepare($sql_select);
            $stmt_select->bindParam(':veteriner_id', $veteriner_id, PDO::PARAM_INT);
            $stmt_select->execute();
            $veteriner_db = $stmt_select->fetch(PDO::FETCH_ASSOC);
            $stmt_select->closeCursor();

            if ($veteriner_db) {
                // Veritabanından gelen verileri $form_values'a ata (eğer POST'tan gelen bir hata yoksa)
                // Eğer POST'tan bir hata mesajı varsa, $form_values zaten POST verileriyle dolu olmalı.
                if (empty($_SESSION['mesaj_veteriner_duzenle'])) {
                     $form_values['adi'] = $veteriner_db['Adi'];
                     $form_values['soyadi'] = $veteriner_db['Soyadi'];
                     $form_values['telefon'] = $veteriner_db['TelefonNumarasi'];
                }
                 $veteriner = $veteriner_db; // Formu göstermek için $veteriner'in dolu olması yeterli.
            } else {
                $_SESSION['mesaj_veteriner'] = "<div class='alert alert-warning'>Düzenlenecek veteriner (ID: {$veteriner_id}) bulunamadı.</div>";
                header("Location: veterinerler.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['mesaj_veteriner_duzenle'] = "<div class='alert alert-danger'>Veteriner bilgileri alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
            $veteriner = null; // Hata durumunda formu gösterme
        }
    }
    // Eğer POST'tan sonra hata mesajı varsa, $form_values zaten POST verileriyle dolu, $veteriner de GET'teki ID'den dolayı set edilmiş olabilir.
    // Formu göstermek için $veteriner'in varlığı yeterli.

} else {
    $_SESSION['mesaj_veteriner'] = "<div class='alert alert-danger'>Geçersiz veteriner ID'si.</div>";
    header("Location: veterinerler.php");
    exit;
}
?>

<div class="container mt-4">
    <h2>Veteriner Bilgilerini Düzenle</h2>

    <?php
    // Session mesajlarını göstermek için (bu sayfaya özel mesajlar)
    if (isset($_SESSION['mesaj_veteriner_duzenle'])) {
        echo $_SESSION['mesaj_veteriner_duzenle'];
        unset($_SESSION['mesaj_veteriner_duzenle']); // Mesajı gösterdikten sonra sil
    }
    ?>

    <?php if ($veteriner_id && ($veteriner || !empty($form_values['adi']))): // Sadece geçerli bir ID ve veri varsa formu göster ?>
    <form action="veteriner_duzenle.php?id=<?php echo htmlspecialchars($veteriner_id); ?>" method="POST">
        <div class="mb-3">
            <label for="adi" class="form-label">Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="adi" name="adi" value="<?php echo htmlspecialchars($form_values['adi']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="soyadi" class="form-label">Soyadı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="soyadi" name="soyadi" value="<?php echo htmlspecialchars($form_values['soyadi']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="telefon" class="form-label">Telefon Numarası <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="telefon" name="telefon" value="<?php echo htmlspecialchars($form_values['telefon']); ?>" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Güncelle</button>
        <a href="veterinerler.php" class="btn btn-secondary">İptal Et ve Geri Dön</a>
    </form>
    <?php elseif (!isset($_SESSION['mesaj_veteriner_duzenle'])): // Eğer mesaj yoksa ve form da gösterilmiyorsa (örn: ID geçersiz ve yönlendirme oldu) ?>
        <!-- Bu kısım genellikle gösterilmez çünkü ID hatası durumunda yönlendirme yapılır -->
        <!-- <div class="alert alert-info">Düzenlenecek bir veteriner seçilmedi veya bulunamadı. Lütfen <a href="veterinerler.php" class="alert-link">veteriner listesinden</a> bir seçim yapın.</div> -->
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>