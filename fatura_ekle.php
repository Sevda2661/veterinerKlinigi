<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Yeni Fatura Oluştur - Veteriner Kliniği";
include 'includes/header.php';

// Formdan gelen verileri tutmak için
$form_data = $_SESSION['form_data_fatura'] ?? [
    'musteri_id' => '',
    'hayvan_id' => '', // Opsiyonel
    'fatura_tarihi' => date('Y-m-d'), // Varsayılan olarak bugünün tarihi
    'odeme_durumu' => 'Ödenmedi' // Varsayılan
];
unset($_SESSION['form_data_fatura']);

// Müşterileri çek (dropdown için)
$musteriler_listesi = [];
try {
    $stmt_musteriler = $pdo->query("CALL sp_Musteriler_Listele()");
    $musteriler_listesi = $stmt_musteriler->fetchAll();
    $stmt_musteriler->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_fatura_ekle'] = "<div class='alert alert-danger'>Müşteri listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// Hayvanları çek (dropdown için - tüm hayvanlar)
// İdealde, müşteri seçildiğinde sadece o müşterinin hayvanları AJAX ile yüklenebilir,
// ama şimdilik tüm hayvanları listeleyelim.
$hayvanlar_listesi = [];
try {
    // sp_Hayvanlar_Listele, MusteriAdi ve MusteriSoyadi'nı da getiriyordu.
    $stmt_hayvanlar = $pdo->query("CALL sp_Hayvanlar_Listele()");
    $hayvanlar_listesi = $stmt_hayvanlar->fetchAll();
    $stmt_hayvanlar->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_fatura_ekle'] = "<div class='alert alert-danger'>Hayvan listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}


// Form gönderildi mi diye kontrol et
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $musteri_id_post = filter_input(INPUT_POST, 'musteri_id', FILTER_VALIDATE_INT);
    $hayvan_id_post = filter_input(INPUT_POST, 'hayvan_id', FILTER_VALIDATE_INT);
    if ($hayvan_id_post === false) $hayvan_id_post = null; // Eğer seçilmemişse veya geçersizse NULL yap
    
    $fatura_tarihi_post = trim($_POST['fatura_tarihi']);
    $odeme_durumu_post = trim($_POST['odeme_durumu']);

    // POST edilen verileri session'a kaydet
    $_SESSION['form_data_fatura'] = [
        'musteri_id' => $musteri_id_post,
        'hayvan_id' => $hayvan_id_post,
        'fatura_tarihi' => $fatura_tarihi_post,
        'odeme_durumu' => $odeme_durumu_post
    ];

    // Doğrulama
    $hatalar = [];
    if (empty($musteri_id_post)) $hatalar[] = "Müşteri seçimi zorunludur.";
    if (empty($fatura_tarihi_post)) $hatalar[] = "Fatura tarihi zorunludur.";
    if (empty($odeme_durumu_post)) $hatalar[] = "Ödeme durumu zorunludur.";
    
    $tarih_obj = DateTime::createFromFormat('Y-m-d', $fatura_tarihi_post);
    if (!$tarih_obj || $tarih_obj->format('Y-m-d') !== $fatura_tarihi_post) {
        $hatalar[] = "Geçersiz fatura tarihi formatı. (YYYY-AA-GG)";
    }
    // Fatura tarihi geçmişte de olabilir.

    $gecerli_odeme_durumlari = ['Ödenmedi', 'Ödendi', 'Kısmi Ödendi', 'İptal Edildi'];
    if (!in_array($odeme_durumu_post, $gecerli_odeme_durumlari)) {
        $hatalar[] = "Geçersiz ödeme durumu seçildi.";
    }

    if (!empty($hatalar)) {
        $_SESSION['mesaj_fatura_ekle'] = "<div class='alert alert-danger'><ul>";
        foreach ($hatalar as $hata) {
            $_SESSION['mesaj_fatura_ekle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
        }
        $_SESSION['mesaj_fatura_ekle'] .= "</ul></div>";
        header("Location: fatura_ekle.php");
        exit;
    } else {
        // Saklı yordam: sp_Fatura_Ekle(IN p_MusteriID INT, IN p_HayvanID INT, IN p_FaturaTarihi DATE, IN p_OdemeDurumu VARCHAR(20))
        // ToplamTutar yordam içinde 0.00 olarak set ediliyor.
        $sql = "CALL sp_Fatura_Ekle(:musteri_id, :hayvan_id, :fatura_tarihi, :odeme_durumu)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':musteri_id', $musteri_id_post, PDO::PARAM_INT);
            // HayvanID NULL olabilir
            $stmt->bindParam(':hayvan_id', $hayvan_id_post, $hayvan_id_post === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':fatura_tarihi', $fatura_tarihi_post, PDO::PARAM_STR);
            $stmt->bindParam(':odeme_durumu', $odeme_durumu_post, PDO::PARAM_STR);
            
            $stmt->execute();
            $yeniFaturaID = $stmt->fetchColumn();
            $stmt->closeCursor();

            unset($_SESSION['form_data_fatura']);
            // Başarı mesajı ve fatura görüntüleme/detay ekleme sayfasına yönlendirme
            $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-success'>Yeni fatura (ID: {$yeniFaturaID}) başarıyla oluşturuldu. Şimdi fatura kalemlerini ekleyebilirsiniz.</div>";
            header("Location: fatura_goruntule.php?id=" . $yeniFaturaID);
            exit;

        } catch (PDOException $e) {
            $_SESSION['mesaj_fatura_ekle'] = "<div class='alert alert-danger'>Fatura oluşturulurken bir veritabanı hatası oluştu: " . $e->getMessage() . "</div>";
            header("Location: fatura_ekle.php");
            exit;
        }
    }
}
?>

<div class="container mt-4">
    <h2>Yeni Fatura Oluştur</h2>

    <?php
    if (isset($_SESSION['mesaj_fatura_ekle'])) {
        echo $_SESSION['mesaj_fatura_ekle'];
        unset($_SESSION['mesaj_fatura_ekle']);
    }
    ?>

    <form id="faturaEkleForm" action="fatura_ekle.php" method="POST">
        <div class="mb-3">
            <label for="musteri_id" class="form-label">Müşteri Seçin <span class="text-danger">*</span></label>
            <select class="form-select" id="musteri_id" name="musteri_id" required>
                <option value="">Müşteri Seçiniz...</option>
                <?php foreach ($musteriler_listesi as $musteri_item): ?>
                    <option value="<?php echo htmlspecialchars($musteri_item['MusteriID']); ?>" 
                            <?php echo ($form_data['musteri_id'] == $musteri_item['MusteriID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($musteri_item['Adi'] . ' ' . $musteri_item['Soyadi'] . ' (Tel: ' . $musteri_item['TelefonNumarasi'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="hayvan_id" class="form-label">Hayvan Seçin (Opsiyonel)</label>
            <select class="form-select" id="hayvan_id" name="hayvan_id">
                <option value="">Hayvan Seçilmedi...</option>
                <?php foreach ($hayvanlar_listesi as $hayvan_item): ?>
                    <option value="<?php echo htmlspecialchars($hayvan_item['HayvanID']); ?>"
                            data-musteri-id="<?php echo htmlspecialchars($hayvan_item['MusteriID']); ?>"
                            <?php echo ($form_data['hayvan_id'] == $hayvan_item['HayvanID']) ? 'selected' : ''; ?>
                            style="display:block;"> <!-- Başlangıçta hepsi görünür -->
                        <?php echo htmlspecialchars($hayvan_item['Adi'] . " (Sahibi: " . $hayvan_item['MusteriAdi'] . " " . $hayvan_item['MusteriSoyadi'] . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Müşteri seçildiğinde, sadece o müşteriye ait hayvanlar burada filtrelenecektir.</div>
        </div>
        
        <div class="mb-3">
            <label for="fatura_tarihi" class="form-label">Fatura Tarihi <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="fatura_tarihi" name="fatura_tarihi" value="<?php echo htmlspecialchars($form_data['fatura_tarihi']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="odeme_durumu" class="form-label">Ödeme Durumu <span class="text-danger">*</span></label>
            <select class="form-select" id="odeme_durumu" name="odeme_durumu" required>
                <option value="Ödenmedi" <?php echo ($form_data['odeme_durumu'] == 'Ödenmedi') ? 'selected' : ''; ?>>Ödenmedi</option>
                <option value="Ödendi" <?php echo ($form_data['odeme_durumu'] == 'Ödendi') ? 'selected' : ''; ?>>Ödendi</option>
                <option value="Kısmi Ödendi" <?php echo ($form_data['odeme_durumu'] == 'Kısmi Ödendi') ? 'selected' : ''; ?>>Kısmi Ödendi</option>
                <option value="İptal Edildi" <?php echo ($form_data['odeme_durumu'] == 'İptal Edildi') ? 'selected' : ''; ?>>İptal Edildi</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Fatura Oluştur ve Detay Ekle</button>
        <a href="faturalar.php" class="btn btn-secondary">İptal</a>
    </form>
</div>

<script>
// Müşteri seçildiğinde hayvanları filtrelemek için basit JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const musteriSelect = document.getElementById('musteri_id');
    const hayvanSelect = document.getElementById('hayvan_id');
    const hayvanOptions = Array.from(hayvanSelect.options); // Orijinal tüm hayvan seçenekleri

    function filtreleHayvanlar() {
        const seciliMusteriId = musteriSelect.value;
        
        // Önce hayvan seçimini sıfırla (eğer müşteri değiştiyse)
        // Eğer bir hayvan zaten seçiliyse ve müşteri değişiyorsa, hayvanı "Hayvan Seçilmedi" yap.
        // Ama form yeniden yüklendiğinde ve $_SESSION'dan hayvan_id geliyorsa, o hayvan seçili kalmalı.
        // Bu JS, sayfa ilk yüklendiğinde de çalışacağı için, $_SESSION'dan gelen hayvan_id'nin
        // seçili müşteriyle uyumlu olup olmadığını kontrol etmez. Bu daha karmaşık bir JS gerektirir.
        // Şimdilik basit filtreleme yapalım.
        // hayvanSelect.value = ""; // Bunu yapmak, session'dan gelen seçimi bozabilir.

        // Tüm hayvan seçeneklerini döngüye al
        hayvanOptions.forEach(option => {
            if (option.value === "") { // "Hayvan Seçilmedi..." seçeneği her zaman görünür
                option.style.display = 'block';
                return;
            }
            if (seciliMusteriId === "" || option.dataset.musteriId === seciliMusteriId) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
                // Eğer gizlenen seçenek o an seçili olan hayvansa, hayvan seçimini sıfırla
                if (hayvanSelect.value === option.value) {
                    hayvanSelect.value = "";
                }
            }
        });
    }

    // Sayfa yüklendiğinde de filtrele (eğer session'dan müşteri seçili geldiyse)
    filtreleHayvanlar();

    // Müşteri seçimi değiştiğinde filtrele
    musteriSelect.addEventListener('change', filtreleHayvanlar);
});
</script>

<?php
include 'includes/footer.php';
?>