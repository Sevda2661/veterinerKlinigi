<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "İlaç Bilgilerini Düzenle - Veteriner Kliniği";
$sayfa_basligi_dinamik = "İlaç Bilgilerini Düzenle"; // H2 için
include 'includes/header.php';

$ilac_id = null;
$form_values = [ // Hem DB'den gelen hem de POST sonrası kullanılacak
    'ilac_adi' => '',
    'stok_miktari' => '',
    'birim_satis_fiyati' => ''
];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $ilac_id = intval($_GET['id']);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $ilac_adi_post = trim($_POST['ilac_adi']);
        $stok_miktari_post_raw = $_POST['stok_miktari'];
        $birim_satis_fiyati_post_raw = $_POST['birim_satis_fiyati'];

        // Form değerlerini POST'tan gelenlerle güncelle (hata durumunda formda kalsın diye)
        $form_values['ilac_adi'] = $ilac_adi_post;
        $form_values['stok_miktari'] = $stok_miktari_post_raw;
        $form_values['birim_satis_fiyati'] = $birim_satis_fiyati_post_raw;

        $hatalar = [];
        if (empty($ilac_adi_post)) {
            $hatalar[] = "İlaç adı zorunludur.";
        }
        
        $stok_miktari_post = filter_var($stok_miktari_post_raw, FILTER_VALIDATE_INT);
        if ($stok_miktari_post === false || $stok_miktari_post < 0) {
            $hatalar[] = "Stok miktarı geçerli bir pozitif tam sayı olmalıdır.";
        }

        $birim_satis_fiyati_post = filter_var(str_replace(',', '.', $birim_satis_fiyati_post_raw), FILTER_VALIDATE_FLOAT);
        if ($birim_satis_fiyati_post === false || $birim_satis_fiyati_post < 0) {
            $hatalar[] = "Birim satış fiyatı geçerli bir pozitif sayı olmalıdır (örn: 25.50).";
        }

        if (!empty($hatalar)) {
            $_SESSION['mesaj_ilac_duzenle'] = "<div class='alert alert-danger'><ul>";
            foreach ($hatalar as $hata) {
                $_SESSION['mesaj_ilac_duzenle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
            }
            $_SESSION['mesaj_ilac_duzenle'] .= "</ul></div>";
            // Yönlendirme yok, aynı sayfada mesaj gösterilecek ve form dolu kalacak
        } else {
            $sql_update = "CALL sp_Ilac_Guncelle(:ilac_id, :ilac_adi, :stok_miktari, :birim_satis_fiyati)";
            try {
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':ilac_id', $ilac_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':ilac_adi', $ilac_adi_post, PDO::PARAM_STR);
                $stmt_update->bindParam(':stok_miktari', $stok_miktari_post, PDO::PARAM_INT);
                $stmt_update->bindParam(':birim_satis_fiyati', $birim_satis_fiyati_post, PDO::PARAM_STR);
                
                $stmt_update->execute();
                $guncellenen_satir_sayisi = $stmt_update->fetchColumn();
                $stmt_update->closeCursor();

                if ($guncellenen_satir_sayisi > 0) {
                    $_SESSION['mesaj_ilac'] = "<div class='alert alert-success'>İlaç bilgileri (ID: {$ilac_id}) başarıyla güncellendi.</div>";
                    header("Location: ilaclar.php");
                    exit;
                } else {
                    $_SESSION['mesaj_ilac_duzenle'] = "<div class='alert alert-info'>İlaç bilgilerinde herhangi bir değişiklik yapılmadı veya ilaç bulunamadı.</div>";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { 
                    $_SESSION['mesaj_ilac_duzenle'] = "<div class='alert alert-danger'>HATA: Bu ilaç adı zaten başka bir ilaca ait.</div>";
                } else {
                    $_SESSION['mesaj_ilac_duzenle'] = "<div class='alert alert-danger'>İlaç güncellenirken bir hata: " . $e->getMessage() . "</div>";
                }
            }
        }
    }

    // Sayfa ilk yüklendiğinde (GET) veya POST sonrası (hata yoksa ve değişiklik de yoksa) DB'den çek
    // Eğer POST sonrası hata varsa, $form_values zaten POST'tan gelenleri içeriyor.
    if ($_SERVER["REQUEST_METHOD"] != "POST" || (isset($guncellenen_satir_sayisi) && $guncellenen_satir_sayisi == 0 && empty($hatalar)) || !isset($_POST['ilac_adi']) ) {
        try {
            $stmt_select = $pdo->prepare("CALL sp_Ilac_GetirByID(:ilac_id)");
            $stmt_select->bindParam(':ilac_id', $ilac_id, PDO::PARAM_INT);
            $stmt_select->execute();
            $ilac_db = $stmt_select->fetch(PDO::FETCH_ASSOC);
            $stmt_select->closeCursor();

            if ($ilac_db) {
                $form_values['ilac_adi'] = $ilac_db['IlacAdi'];
                $form_values['stok_miktari'] = $ilac_db['StokMiktari'];
                $form_values['birim_satis_fiyati'] = number_format($ilac_db['BirimSatisFiyati'], 2, '.', ''); // Formatlayarak al
            } else {
                $_SESSION['mesaj_ilac'] = "<div class='alert alert-warning'>Düzenlenecek ilaç (ID: {$ilac_id}) bulunamadı.</div>";
                header("Location: ilaclar.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['mesaj_ilac_duzenle'] = "<div class='alert alert-danger'>İlaç bilgileri alınırken bir hata: " . $e->getMessage() . "</div>";
            $ilac_db = null; // Hata durumunda formu gösterme
        }
    }
    // Eğer $ilac_db null ise (örn: ID geçersiz veya DB hatası), formu gösterme
    if (empty($form_values['ilac_adi']) && $ilac_id && !isset($_POST['ilac_adi'])) { // ID var ama ilaç adı çekilememişse (ve POST değilse)
         // Yukarıda header ile yönlendirme zaten yapılmış olmalı, bu bir fallback.
        if (!isset($_SESSION['mesaj_ilac_duzenle']) && !isset($_SESSION['mesaj_ilac'])) {
             $_SESSION['mesaj_ilac'] = "<div class='alert alert-danger'>İlaç yüklenemedi.</div>";
        }
        if (!headers_sent()) { // Yönlendirme sadece headerlar gönderilmediyse yapılır
            header("Location: ilaclar.php");
            exit;
        }
    }


} else {
    $_SESSION['mesaj_ilac'] = "<div class='alert alert-danger'>Geçersiz ilaç ID'si.</div>";
    header("Location: ilaclar.php");
    exit;
}
?>

<div class="container mt-4">
    <h2 class="page-title"><?php echo htmlspecialchars($sayfa_basligi_dinamik . " (ID: " . $ilac_id . ")"); ?></h2>

    <?php
    if (isset($_SESSION['mesaj_ilac_duzenle'])) {
        echo $_SESSION['mesaj_ilac_duzenle'];
        unset($_SESSION['mesaj_ilac_duzenle']);
    }
    ?>

    <?php if ($ilac_id && !empty($form_values['ilac_adi'])): // Sadece ilaç bilgileri varsa formu göster ?>
    <form action="ilac_duzenle.php?id=<?php echo htmlspecialchars($ilac_id); ?>" method="POST">
        <div class="mb-3">
            <label for="ilac_adi" class="form-label">İlaç Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="ilac_adi" name="ilac_adi" value="<?php echo htmlspecialchars($form_values['ilac_adi']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="stok_miktari" class="form-label">Stok Miktarı <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="stok_miktari" name="stok_miktari" value="<?php echo htmlspecialchars($form_values['stok_miktari']); ?>" min="0" required>
        </div>

        <div class="mb-3">
            <label for="birim_satis_fiyati" class="form-label">Birim Satış Fiyatı (TL) <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="birim_satis_fiyati" name="birim_satis_fiyati" placeholder="Örn: 25.50 veya 25,50" value="<?php echo htmlspecialchars($form_values['birim_satis_fiyati']); ?>" required>
             <div class="form-text">Lütfen ondalık ayırıcı olarak nokta (.) veya virgül (,) kullanın.</div>
        </div>
        
        <div class="d-flex justify-content-between mt-4">
            <a href="ilaclar.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>İptal ve Geri Dön</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Güncelle</button>
        </div>
    </form>
    <?php elseif (!isset($_SESSION['mesaj_ilac_duzenle'])): ?>
        <!-- Bu kısım normalde ID hatası veya DB hatasıyla yönlendirme sonrası görünmez -->
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>