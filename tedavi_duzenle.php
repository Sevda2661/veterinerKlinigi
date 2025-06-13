<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Tedavi Bilgilerini Düzenle - Veteriner Kliniği";
include 'includes/header.php';

$tedavi = null; // Tedavi bilgilerini tutacak değişken
$tedavi_id = null;

// Formdan gelen veya veritabanından çekilen verileri tutmak için
$form_values = [
    'hayvan_adi' => '',       // Göstermek için
    'veteriner_adi' => '',    // Göstermek için
    'hayvan_id_orj' => '',    // Orijinal HayvanID'yi saklamak için
    'veteriner_id_orj' => '', // Orijinal VeterinerID'yi saklamak için
    'tani' => '',
    'tedavi_tarihi' => ''
];

// 1. Düzenlenecek Tedavinin ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $tedavi_id = intval($_GET['id']);

    // 2. Form Gönderilmişse (POST isteği ile) Güncelleme İşlemini Yap
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Hayvan ve Veteriner ID'leri formdan gelmiyor (readonly), bu yüzden onları POST'tan değil,
        // sayfa ilk yüklendiğinde çekilen orijinal değerlerden almalıyız.
        // Bunun için $form_values['hayvan_id_orj'] ve $form_values['veteriner_id_orj'] kullanılacak.
        // Ancak sp_Tedavi_Guncelle yordamı HayvanID ve VeterinerID'yi parametre olarak alıyor.
        // Bu yüzden, bu ID'leri bir şekilde saklamamız ve POST ederken göndermemiz lazım.
        // Ya da yordamı sadece Tanı ve TedaviTarihi'ni alacak şekilde değiştirmeliyiz.
        // Mevcut yordam: sp_Tedavi_Guncelle(IN p_TedaviID INT, IN p_HayvanID INT, IN p_VeterinerID INT, IN p_Tani TEXT, IN p_TedaviTarihi DATE)
        // Bu durumda, HayvanID ve VeterinerID'yi hidden input olarak formda tutmamız GEREKİR.

        $hayvan_id_post = filter_input(INPUT_POST, 'hayvan_id_hidden', FILTER_VALIDATE_INT);
        $veteriner_id_post = filter_input(INPUT_POST, 'veteriner_id_hidden', FILTER_VALIDATE_INT);
        $tani_post = trim($_POST['tani']);
        $tedavi_tarihi_post = trim($_POST['tedavi_tarihi']);

        // POST edilen düzenlenebilir verileri form değerleri için sakla
        $form_values['tani'] = $tani_post;
        $form_values['tedavi_tarihi'] = $tedavi_tarihi_post;
        // Sabit kalan hayvan_id_orj ve veteriner_id_orj GET'ten gelen verilerle dolacak.

        $hatalar = [];
        if (empty($tani_post)) $hatalar[] = "Tanı açıklaması zorunludur.";
        if (empty($tedavi_tarihi_post)) $hatalar[] = "Tedavi tarihi zorunludur.";
        
        $tarih_obj = DateTime::createFromFormat('Y-m-d', $tedavi_tarihi_post);
        if (!$tarih_obj || $tarih_obj->format('Y-m-d') !== $tedavi_tarihi_post) {
            $hatalar[] = "Geçersiz tedavi tarihi formatı. (YYYY-AA-GG)";
        }

        if (!empty($hatalar)) {
            $_SESSION['mesaj_tedavi_duzenle'] = "<div class='alert alert-danger'><ul>";
            foreach ($hatalar as $hata) {
                $_SESSION['mesaj_tedavi_duzenle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
            }
            $_SESSION['mesaj_tedavi_duzenle'] .= "</ul></div>";
            // Hata varsa, $form_values zaten POST verilerini (tani, tedavi_tarihi) tutuyor.
            // Hayvan ve Veteriner adını/ID'sini tekrar yüklemek için aşağıdaki GET bloğundaki $tedavi çekimi
            // $form_values['hayvan_adi'], ['veteriner_adi'], ['hayvan_id_orj'], ['veteriner_id_orj']'yi tekrar doldurmalı.
        } else {
            // Saklı yordam: sp_Tedavi_Guncelle(IN p_TedaviID INT, IN p_HayvanID INT, IN p_VeterinerID INT, IN p_Tani TEXT, IN p_TedaviTarihi DATE)
            $sql_update = "CALL sp_Tedavi_Guncelle(:tedavi_id, :hayvan_id, :veteriner_id, :tani, :tedavi_tarihi)";
            try {
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':tedavi_id', $tedavi_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':hayvan_id', $hayvan_id_post, PDO::PARAM_INT);       // Hidden input'tan gelen
                $stmt_update->bindParam(':veteriner_id', $veteriner_id_post, PDO::PARAM_INT); // Hidden input'tan gelen
                $stmt_update->bindParam(':tani', $tani_post, PDO::PARAM_STR);
                $stmt_update->bindParam(':tedavi_tarihi', $tedavi_tarihi_post, PDO::PARAM_STR);
                
                $stmt_update->execute();
                $guncellenen_satir_sayisi = $stmt_update->fetchColumn();
                $stmt_update->closeCursor();

                if ($guncellenen_satir_sayisi > 0) {
                    $_SESSION['mesaj_tedavi'] = "<div class='alert alert-success'>Tedavi bilgileri (ID: {$tedavi_id}) başarıyla güncellendi.</div>";
                    // Kullanıcıyı geldiği hayvanın tedavi listesine veya genel listeye yönlendir
                    $yonlendirme_url = "tedaviler.php";
                    // Eğer bu tedaviye ait hayvan ID'sini biliyorsak (hidden input'tan)
                    if ($hayvan_id_post) {
                        $yonlendirme_url .= "?hayvan_id=" . $hayvan_id_post;
                    }
                    header("Location: " . $yonlendirme_url);
                    exit;
                } else {
                    $_SESSION['mesaj_tedavi_duzenle'] = "<div class='alert alert-info'>Tedavi bilgilerinde herhangi bir değişiklik yapılmadı veya kayıt bulunamadı.</div>";
                }
            } catch (PDOException $e) {
                $_SESSION['mesaj_tedavi_duzenle'] = "<div class='alert alert-danger'>Tedavi güncellenirken bir veritabanı hatası oluştu: " . $e->getMessage() . "</div>";
            }
        }
    }

    // 3. Sayfa ilk yüklendiğinde (GET) veya POST sonrası (güncel bilgileri veya hata sonrası formu doldurmak için)
    // Saklı yordam: sp_Tedavi_GetirByID(IN p_TedaviID INT)
    // Bu yordam H.Adi AS HayvanAdi, CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi, T.HayvanID, T.VeterinerID getiriyordu.
    $sql_select = "CALL sp_Tedavi_GetirByID(:tedavi_id)";
    try {
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->bindParam(':tedavi_id', $tedavi_id, PDO::PARAM_INT);
        $stmt_select->execute();
        $tedavi_db = $stmt_select->fetch(PDO::FETCH_ASSOC);
        $stmt_select->closeCursor();

        if ($tedavi_db) {
            $tedavi = $tedavi_db; // Formu göstermek için kontrol
            
            $form_values['hayvan_adi'] = $tedavi_db['HayvanAdi'];
            $form_values['veteriner_adi'] = $tedavi_db['VeterinerAdiSoyadi'];
            $form_values['hayvan_id_orj'] = $tedavi_db['HayvanID'];       // Hidden input için
            $form_values['veteriner_id_orj'] = $tedavi_db['VeterinerID']; // Hidden input için

            // Eğer POST'tan bir işlem yapılmadıysa (yani GET isteği) veya POST sonrası mesaj yoksa
            // $form_values'u veritabanından gelenlerle doldur.
            if ($_SERVER["REQUEST_METHOD"] != "POST" || empty($_SESSION['mesaj_tedavi_duzenle'])) {
                 $form_values['tani'] = $tedavi_db['Tani'];
                 $form_values['tedavi_tarihi'] = $tedavi_db['TedaviTarihi'];
            }
             // Eğer POST'tan hata geldiyse, $form_values['tani'], ['tedavi_tarihi'] zaten POST'tan gelen değerleri içeriyor olacak.
        } else {
            $_SESSION['mesaj_tedavi'] = "<div class='alert alert-warning'>Düzenlenecek tedavi (ID: {$tedavi_id}) bulunamadı.</div>";
            header("Location: tedaviler.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['mesaj_tedavi_duzenle'] = "<div class='alert alert-danger'>Tedavi bilgileri alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
        $tedavi = null; // Hata durumunda formu gösterme
    }

} else {
    $_SESSION['mesaj_tedavi'] = "<div class='alert alert-danger'>Geçersiz tedavi ID'si.</div>";
    header("Location: tedaviler.php");
    exit;
}
?>

<div class="container mt-4">
    <h2>Tedavi Bilgilerini Düzenle (ID: <?php echo htmlspecialchars($tedavi_id); ?>)</h2>

    <?php
    if (isset($_SESSION['mesaj_tedavi_duzenle'])) {
        echo $_SESSION['mesaj_tedavi_duzenle'];
        unset($_SESSION['mesaj_tedavi_duzenle']);
    }
    ?>

    <?php if ($tedavi): // Sadece tedavi bilgileri başarıyla çekildiyse formu göster ?>
    <form action="tedavi_duzenle.php?id=<?php echo htmlspecialchars($tedavi_id); ?>" method="POST">
        <!-- Hidden input'lar HayvanID ve VeterinerID'yi saklamak için -->
        <input type="hidden" name="hayvan_id_hidden" value="<?php echo htmlspecialchars($form_values['hayvan_id_orj']); ?>">
        <input type="hidden" name="veteriner_id_hidden" value="<?php echo htmlspecialchars($form_values['veteriner_id_orj']); ?>">

        <div class="mb-3">
            <label class="form-label">Hayvan:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($form_values['hayvan_adi']); ?>" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Veteriner:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($form_values['veteriner_adi']); ?>" readonly>
        </div>
        
        <div class="mb-3">
            <label for="tedavi_tarihi" class="form-label">Tedavi Tarihi <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="tedavi_tarihi" name="tedavi_tarihi" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($form_values['tedavi_tarihi']))); ?>" required>
        </div>

        <div class="mb-3">
            <label for="tani" class="form-label">Tanı ve Uygulanan Tedavi <span class="text-danger">*</span></label>
            <textarea class="form-control" id="tani" name="tani" rows="4" required><?php echo htmlspecialchars($form_values['tani']); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Güncelle</button>
        <a href="tedaviler.php<?php echo ($form_values['hayvan_id_orj'] ? '?hayvan_id='.$form_values['hayvan_id_orj'] : ''); ?>" class="btn btn-secondary">İptal</a>
    </form>
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>