<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Yeni Tedavi Kaydı Ekle - Veteriner Kliniği";
include 'includes/header.php';

// Formdan gelen verileri tutmak için değişkenler
$form_data = $_SESSION['form_data_tedavi'] ?? [
    'hayvan_id' => '',
    'veteriner_id' => '',
    'tani' => '',
    'tedavi_tarihi' => date('Y-m-d') // Varsayılan olarak bugünün tarihi
];
unset($_SESSION['form_data_tedavi']);

// URL'den hayvan_id geldiyse form_data'ya ata
if (isset($_GET['hayvan_id']) && is_numeric($_GET['hayvan_id']) && empty($form_data['hayvan_id'])) {
    $form_data['hayvan_id'] = intval($_GET['hayvan_id']);
}

// Hayvanları çek (dropdown için)
$hayvanlar_listesi = [];
try {
    $stmt_hayvanlar = $pdo->query("CALL sp_Hayvanlar_Listele()");
    $hayvanlar_listesi = $stmt_hayvanlar->fetchAll();
    $stmt_hayvanlar->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_tedavi_ekle'] = "<div class='alert alert-danger'>Hayvan listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// Veterinerleri çek (dropdown için)
$veterinerler_listesi = [];
try {
    $stmt_veterinerler = $pdo->query("CALL sp_Veterinerler_Listele()");
    $veterinerler_listesi = $stmt_veterinerler->fetchAll();
    $stmt_veterinerler->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_tedavi_ekle'] = "<div class='alert alert-danger'>Veteriner listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// Form gönderildi mi diye kontrol et
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hayvan_id_post = filter_input(INPUT_POST, 'hayvan_id', FILTER_VALIDATE_INT);
    $veteriner_id_post = filter_input(INPUT_POST, 'veteriner_id', FILTER_VALIDATE_INT);
    $tani_post = trim($_POST['tani']);
    $tedavi_tarihi_post = trim($_POST['tedavi_tarihi']); // YYYY-MM-DD

    // POST edilen verileri session'a kaydet (hata durumunda formu dolu tutmak için)
    $_SESSION['form_data_tedavi'] = [
        'hayvan_id' => $hayvan_id_post,
        'veteriner_id' => $veteriner_id_post,
        'tani' => $tani_post,
        'tedavi_tarihi' => $tedavi_tarihi_post
    ];

    // Doğrulama
    $hatalar = [];
    if (empty($hayvan_id_post)) $hatalar[] = "Tedavi uygulanan hayvan seçimi zorunludur.";
    if (empty($veteriner_id_post)) $hatalar[] = "Tedaviyi yapan veteriner seçimi zorunludur.";
    if (empty($tani_post)) $hatalar[] = "Tanı açıklaması zorunludur.";
    if (empty($tedavi_tarihi_post)) $hatalar[] = "Tedavi tarihi zorunludur.";
    
    $tarih_obj = DateTime::createFromFormat('Y-m-d', $tedavi_tarihi_post);
    if (!$tarih_obj || $tarih_obj->format('Y-m-d') !== $tedavi_tarihi_post) {
        $hatalar[] = "Geçersiz tedavi tarihi formatı. (YYYY-AA-GG)";
    }
    // Tedavi tarihi geçmişte de olabilir, gelecekte de. Bu yüzden katı bir kontrol yapmıyoruz,
    // ama istenirse eklenebilir (örn: gelecekteki bir tarih olamaz).

    if (!empty($hatalar)) {
        $_SESSION['mesaj_tedavi_ekle'] = "<div class='alert alert-danger'><ul>";
        foreach ($hatalar as $hata) {
            $_SESSION['mesaj_tedavi_ekle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
        }
        $_SESSION['mesaj_tedavi_ekle'] .= "</ul></div>";
        header("Location: tedavi_ekle.php" . ($hayvan_id_post ? '?hayvan_id='.$hayvan_id_post : '')); // hayvan_id'yi URL'de tut
        exit;
    } else {
        // Saklı yordam: sp_Tedavi_Ekle(IN p_HayvanID INT, IN p_VeterinerID INT, IN p_Tani TEXT, IN p_TedaviTarihi DATE)
        $sql = "CALL sp_Tedavi_Ekle(:hayvan_id, :veteriner_id, :tani, :tedavi_tarihi)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':hayvan_id', $hayvan_id_post, PDO::PARAM_INT);
            $stmt->bindParam(':veteriner_id', $veteriner_id_post, PDO::PARAM_INT);
            $stmt->bindParam(':tani', $tani_post, PDO::PARAM_STR);
            $stmt->bindParam(':tedavi_tarihi', $tedavi_tarihi_post, PDO::PARAM_STR);
            
            $stmt->execute();
            $yeniTedaviID = $stmt->fetchColumn();
            $stmt->closeCursor();

            unset($_SESSION['form_data_tedavi']);
            $_SESSION['mesaj_tedavi'] = "<div class='alert alert-success'>Yeni tedavi kaydı (ID: {$yeniTedaviID}) başarıyla oluşturuldu.</div>";
            // Kullanıcıyı geldiği hayvanın tedavi listesine veya genel listeye yönlendir
            $yonlendirme_url = "tedaviler.php";
            if ($hayvan_id_post) {
                $yonlendirme_url .= "?hayvan_id=" . $hayvan_id_post;
            }
            header("Location: " . $yonlendirme_url);
            exit;

        } catch (PDOException $e) {
            // Olası hatalar, örneğin var olmayan HayvanID veya VeterinerID.
            // Saklı yordamda foreign key'ler kontrol ediliyor olmalı.
            $_SESSION['mesaj_tedavi_ekle'] = "<div class='alert alert-danger'>Tedavi kaydı oluşturulurken bir veritabanı hatası oluştu: " . $e->getMessage() . "</div>";
            header("Location: tedavi_ekle.php" . ($hayvan_id_post ? '?hayvan_id='.$hayvan_id_post : ''));
            exit;
        }
    }
}
?>

<div class="container mt-4">
    <h2>Yeni Tedavi Kaydı Ekle</h2>

    <?php
    if (isset($_SESSION['mesaj_tedavi_ekle'])) {
        echo $_SESSION['mesaj_tedavi_ekle'];
        unset($_SESSION['mesaj_tedavi_ekle']);
    }
    ?>

    <form action="tedavi_ekle.php<?php echo ($form_data['hayvan_id'] ? '?hayvan_id='.$form_data['hayvan_id'] : ''); ?>" method="POST">
        <div class="mb-3">
            <label for="hayvan_id" class="form-label">Tedavi Uygulanan Hayvan <span class="text-danger">*</span></label>
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
            <label for="veteriner_id" class="form-label">Tedaviyi Yapan Veteriner <span class="text-danger">*</span></label>
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
        
        <div class="mb-3">
            <label for="tedavi_tarihi" class="form-label">Tedavi Tarihi <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="tedavi_tarihi" name="tedavi_tarihi" value="<?php echo htmlspecialchars($form_data['tedavi_tarihi']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="tani" class="form-label">Tanı ve Uygulanan Tedavi <span class="text-danger">*</span></label>
            <textarea class="form-control" id="tani" name="tani" rows="4" required><?php echo htmlspecialchars($form_data['tani']); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Tedaviyi Kaydet</button>
        <a href="tedaviler.php<?php echo ($form_data['hayvan_id'] ? '?hayvan_id='.$form_data['hayvan_id'] : ''); ?>" class="btn btn-secondary">İptal</a>
    </form>
</div>

<?php
include 'includes/footer.php';
?>