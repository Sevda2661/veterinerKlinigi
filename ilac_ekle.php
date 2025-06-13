<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Yeni İlaç Ekle - Veteriner Kliniği";
$sayfa_basligi_dinamik = "Yeni İlaç Ekle"; // H2 için
include 'includes/header.php';

$form_data = $_SESSION['form_data_ilac'] ?? [
    'ilac_adi' => '',
    'stok_miktari' => 0,
    'birim_satis_fiyati' => '' 
];
unset($_SESSION['form_data_ilac']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ilac_adi_post = trim($_POST['ilac_adi']);
    $stok_miktari_post_raw = $_POST['stok_miktari']; 
    $birim_satis_fiyati_post_raw = $_POST['birim_satis_fiyati']; 

    $_SESSION['form_data_ilac'] = [
        'ilac_adi' => $ilac_adi_post,
        'stok_miktari' => $stok_miktari_post_raw,
        'birim_satis_fiyati' => $birim_satis_fiyati_post_raw 
    ];

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
        $_SESSION['mesaj_ilac_ekle'] = "<div class='alert alert-danger'><ul>";
        foreach ($hatalar as $hata) {
            $_SESSION['mesaj_ilac_ekle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
        }
        $_SESSION['mesaj_ilac_ekle'] .= "</ul></div>";
        header("Location: ilac_ekle.php");
        exit;
    } else {
        $sql = "CALL sp_Ilac_Ekle(:ilac_adi, :stok_miktari, :birim_satis_fiyati)"; 
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':ilac_adi', $ilac_adi_post, PDO::PARAM_STR);
            $stmt->bindParam(':stok_miktari', $stok_miktari_post, PDO::PARAM_INT);
            $stmt->bindParam(':birim_satis_fiyati', $birim_satis_fiyati_post, PDO::PARAM_STR); 
            
            $stmt->execute();
            $yeniIlacID = $stmt->fetchColumn();
            $stmt->closeCursor();

            unset($_SESSION['form_data_ilac']);
            $_SESSION['mesaj_ilac'] = "<div class='alert alert-success'>Yeni ilaç (ID: {$yeniIlacID}) başarıyla eklendi.</div>";
            header("Location: ilaclar.php");
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $_SESSION['mesaj_ilac_ekle'] = "<div class='alert alert-danger'>HATA: Bu ilaç adı zaten kayıtlı.</div>";
            } else {
                $_SESSION['mesaj_ilac_ekle'] = "<div class='alert alert-danger'>İlaç eklenirken bir hata: " . $e->getMessage() . "</div>";
            }
            header("Location: ilac_ekle.php");
            exit;
        }
    }
}
?>

<div class="container mt-4">
    <h2 class="page-title"><?php echo htmlspecialchars($sayfa_basligi_dinamik); ?></h2>

    <?php
    if (isset($_SESSION['mesaj_ilac_ekle'])) {
        echo $_SESSION['mesaj_ilac_ekle'];
        unset($_SESSION['mesaj_ilac_ekle']);
    }
    ?>

    <form action="ilac_ekle.php" method="POST">
        <div class="mb-3">
            <label for="ilac_adi" class="form-label">İlaç Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="ilac_adi" name="ilac_adi" value="<?php echo htmlspecialchars($form_data['ilac_adi']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="stok_miktari" class="form-label">Başlangıç Stok Miktarı <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="stok_miktari" name="stok_miktari" value="<?php echo htmlspecialchars($form_data['stok_miktari']); ?>" min="0" required>
        </div>

        <div class="mb-3">
            <label for="birim_satis_fiyati" class="form-label">Birim Satış Fiyatı (TL) <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="birim_satis_fiyati" name="birim_satis_fiyati" placeholder="Örn: 25.50 veya 25,50" value="<?php echo htmlspecialchars($form_data['birim_satis_fiyati']); ?>" required>
            <div class="form-text">Lütfen ondalık ayırıcı olarak nokta (.) veya virgül (,) kullanın.</div>
        </div>
        
        <div class="d-flex justify-content-between mt-4">
            <a href="ilaclar.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>İptal ve Geri Dön</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>İlacı Kaydet</button>
        </div>
    </form>
</div>

<?php
include 'includes/footer.php';
?>