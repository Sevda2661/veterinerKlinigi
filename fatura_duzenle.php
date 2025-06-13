<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Fatura Bilgilerini Düzenle - Veteriner Kliniği";
include 'includes/header.php';

$fatura = null; // Fatura bilgilerini tutacak değişken
$fatura_id = null;

// Formdan gelen veya veritabanından çekilen verileri tutmak için
$form_values = [
    'musteri_id' => '',
    'hayvan_id' => '',
    'fatura_tarihi' => '',
    'odeme_durumu' => ''
];

// Müşterileri çek (dropdown için)
$musteriler_listesi = [];
try {
    $stmt_musteriler = $pdo->query("CALL sp_Musteriler_Listele()");
    $musteriler_listesi = $stmt_musteriler->fetchAll();
    $stmt_musteriler->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_fatura_duzenle'] = "<div class='alert alert-danger'>Müşteri listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}

// Hayvanları çek (dropdown için - tüm hayvanlar)
$hayvanlar_listesi = [];
try {
    $stmt_hayvanlar = $pdo->query("CALL sp_Hayvanlar_Listele()");
    $hayvanlar_listesi = $stmt_hayvanlar->fetchAll();
    $stmt_hayvanlar->closeCursor();
} catch (PDOException $e) {
    $_SESSION['mesaj_fatura_duzenle'] = "<div class='alert alert-danger'>Hayvan listesi alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
}


// 1. Düzenlenecek Faturanın ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $fatura_id = intval($_GET['id']);

    // 2. Form Gönderilmişse (POST isteği ile) Güncelleme İşlemini Yap
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $musteri_id_post = filter_input(INPUT_POST, 'musteri_id', FILTER_VALIDATE_INT);
        $hayvan_id_post = filter_input(INPUT_POST, 'hayvan_id', FILTER_VALIDATE_INT);
        if ($hayvan_id_post === false) $hayvan_id_post = null;

        $fatura_tarihi_post = trim($_POST['fatura_tarihi']);
        $odeme_durumu_post = trim($_POST['odeme_durumu']);

        // POST edilen verileri form değerleri için sakla
        $form_values = [
            'musteri_id' => $musteri_id_post,
            'hayvan_id' => $hayvan_id_post,
            'fatura_tarihi' => $fatura_tarihi_post,
            'odeme_durumu' => $odeme_durumu_post
        ];
        
        $hatalar = [];
        if (empty($musteri_id_post)) $hatalar[] = "Müşteri seçimi zorunludur.";
        if (empty($fatura_tarihi_post)) $hatalar[] = "Fatura tarihi zorunludur.";
        if (empty($odeme_durumu_post)) $hatalar[] = "Ödeme durumu zorunludur.";
        
        $tarih_obj = DateTime::createFromFormat('Y-m-d', $fatura_tarihi_post);
        if (!$tarih_obj || $tarih_obj->format('Y-m-d') !== $fatura_tarihi_post) {
            $hatalar[] = "Geçersiz fatura tarihi formatı. (YYYY-AA-GG)";
        }

        $gecerli_odeme_durumlari = ['Ödenmedi', 'Ödendi', 'Kısmi Ödendi', 'İptal Edildi'];
        if (!in_array($odeme_durumu_post, $gecerli_odeme_durumlari)) {
            $hatalar[] = "Geçersiz ödeme durumu seçildi.";
        }

        if (!empty($hatalar)) {
            $_SESSION['mesaj_fatura_duzenle'] = "<div class='alert alert-danger'><ul>";
            foreach ($hatalar as $hata) {
                $_SESSION['mesaj_fatura_duzenle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
            }
            $_SESSION['mesaj_fatura_duzenle'] .= "</ul></div>";
        } else {
            // Saklı yordam: sp_Fatura_Guncelle(IN p_FaturaID INT, IN p_MusteriID INT, IN p_HayvanID INT, IN p_FaturaTarihi DATE, IN p_OdemeDurumu VARCHAR(20))
            $sql_update = "CALL sp_Fatura_Guncelle(:fatura_id, :musteri_id, :hayvan_id, :fatura_tarihi, :odeme_durumu)";
            try {
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':musteri_id', $musteri_id_post, PDO::PARAM_INT);
                $stmt_update->bindParam(':hayvan_id', $hayvan_id_post, $hayvan_id_post === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt_update->bindParam(':fatura_tarihi', $fatura_tarihi_post, PDO::PARAM_STR);
                $stmt_update->bindParam(':odeme_durumu', $odeme_durumu_post, PDO::PARAM_STR);
                
                $stmt_update->execute();
                $guncellenen_satir_sayisi = $stmt_update->fetchColumn();
                $stmt_update->closeCursor();

                if ($guncellenen_satir_sayisi > 0) {
                    $_SESSION['mesaj_fatura'] = "<div class='alert alert-success'>Fatura ana bilgileri (ID: {$fatura_id}) başarıyla güncellendi.</div>";
                    header("Location: faturalar.php"); // Veya fatura_goruntule.php?id=...
                    exit;
                } else {
                    $_SESSION['mesaj_fatura_duzenle'] = "<div class='alert alert-info'>Fatura bilgilerinde herhangi bir değişiklik yapılmadı veya kayıt bulunamadı.</div>";
                }
            } catch (PDOException $e) {
                $_SESSION['mesaj_fatura_duzenle'] = "<div class='alert alert-danger'>Fatura güncellenirken bir veritabanı hatası oluştu: " . $e->getMessage() . "</div>";
            }
        }
    }

    // 3. Sayfa ilk yüklendiğinde (GET) veya POST sonrası (formu doldurmak için)
    // sp_Fatura_GetirByID yordamı MusteriID ve HayvanID'yi doğrudan döndürmüyor,
    // ama F.MusteriID ve F.HayvanID olarak tablodan alabiliriz.
    // Yordamın SELECT kısmını `SELECT F.*, ...` olarak değiştirmek iyi olurdu.
    // Şimdilik, Fatura tablosundan direkt sorgu ile alalım sadece ID'leri.
    // Veya sp_Fatura_GetirByID'nin zaten F.MusteriID ve F.HayvanID'yi döndürdüğünü varsayalım (SQL kodunuzda F.* vardı).
    
    $sql_select = "CALL sp_Fatura_GetirByID(:fatura_id)"; // Bu yordam F.* içeriyor olmalıydı.
    try {
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
        $stmt_select->execute();
        $fatura_db = $stmt_select->fetch(PDO::FETCH_ASSOC);
        $stmt_select->closeCursor();

        if ($fatura_db) {
            $fatura = $fatura_db; // Formu göstermek için kontrol
            
            if ($_SERVER["REQUEST_METHOD"] != "POST" || empty($_SESSION['mesaj_fatura_duzenle'])) {
                 $form_values['musteri_id'] = $fatura_db['MusteriID'];
                 $form_values['hayvan_id'] = $fatura_db['HayvanID'];
                 $form_values['fatura_tarihi'] = $fatura_db['FaturaTarihi'];
                 $form_values['odeme_durumu'] = $fatura_db['OdemeDurumu'];
            }
        } else {
            $_SESSION['mesaj_fatura'] = "<div class='alert alert-warning'>Düzenlenecek fatura (ID: {$fatura_id}) bulunamadı.</div>";
            header("Location: faturalar.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['mesaj_fatura_duzenle'] = "<div class='alert alert-danger'>Fatura bilgileri alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
        $fatura = null;
    }

} else {
    $_SESSION['mesaj_fatura'] = "<div class='alert alert-danger'>Geçersiz fatura ID'si.</div>";
    header("Location: faturalar.php");
    exit;
}
?>

<div class="container mt-4">
    <h2>Fatura Ana Bilgilerini Düzenle (ID: <?php echo htmlspecialchars($fatura_id); ?>)</h2>

    <?php
    if (isset($_SESSION['mesaj_fatura_duzenle'])) {
        echo $_SESSION['mesaj_fatura_duzenle'];
        unset($_SESSION['mesaj_fatura_duzenle']);
    }
    ?>

    <?php if ($fatura): ?>
    <form id="faturaDuzenleForm" action="fatura_duzenle.php?id=<?php echo htmlspecialchars($fatura_id); ?>" method="POST">
        <div class="mb-3">
            <label for="musteri_id" class="form-label">Müşteri Seçin <span class="text-danger">*</span></label>
            <select class="form-select" id="musteri_id" name="musteri_id" required>
                <option value="">Müşteri Seçiniz...</option>
                <?php foreach ($musteriler_listesi as $musteri_item): ?>
                    <option value="<?php echo htmlspecialchars($musteri_item['MusteriID']); ?>" 
                            <?php echo ($form_values['musteri_id'] == $musteri_item['MusteriID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($musteri_item['Adi'] . ' ' . $musteri_item['Soyadi']); ?>
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
                            <?php echo ($form_values['hayvan_id'] == $hayvan_item['HayvanID']) ? 'selected' : ''; ?>
                            style="display:block;">
                        <?php echo htmlspecialchars($hayvan_item['Adi'] . " (Sahibi: " . $hayvan_item['MusteriAdi'] . " " . $hayvan_item['MusteriSoyadi'] . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="fatura_tarihi" class="form-label">Fatura Tarihi <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="fatura_tarihi" name="fatura_tarihi" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($form_values['fatura_tarihi']))); ?>" required>
        </div>

        <div class="mb-3">
            <label for="odeme_durumu" class="form-label">Ödeme Durumu <span class="text-danger">*</span></label>
            <select class="form-select" id="odeme_durumu" name="odeme_durumu" required>
                <option value="Ödenmedi" <?php echo ($form_values['odeme_durumu'] == 'Ödenmedi') ? 'selected' : ''; ?>>Ödenmedi</option>
                <option value="Ödendi" <?php echo ($form_values['odeme_durumu'] == 'Ödendi') ? 'selected' : ''; ?>>Ödendi</option>
                <option value="Kısmi Ödendi" <?php echo ($form_values['odeme_durumu'] == 'Kısmi Ödendi') ? 'selected' : ''; ?>>Kısmi Ödendi</option>
                <option value="İptal Edildi" <?php echo ($form_values['odeme_durumu'] == 'İptal Edildi') ? 'selected' : ''; ?>>İptal Edildi</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Fatura Bilgilerini Güncelle</button>
        <a href="fatura_goruntule.php?id=<?php echo htmlspecialchars($fatura_id); ?>" class="btn btn-secondary">İptal ve Detaylara Dön</a>
    </form>
    <?php endif; ?>
</div>

<script>
// Müşteri seçildiğinde hayvanları filtrelemek için basit JavaScript (fatura_ekle.php'den kopyalandı)
document.addEventListener('DOMContentLoaded', function() {
    const musteriSelect = document.getElementById('musteri_id');
    const hayvanSelect = document.getElementById('hayvan_id');
    const hayvanOptions = Array.from(hayvanSelect.options);

    function filtreleHayvanlar() {
        const seciliMusteriId = musteriSelect.value;
        let mevcutHayvanSeciliMi = false; // Eğer seçili hayvan yeni müşteriyle uyumsuzsa, seçimi kaldır.

        hayvanOptions.forEach(option => {
            if (option.value === "") {
                option.style.display = 'block';
                return;
            }
            if (seciliMusteriId === "" || option.dataset.musteriId === seciliMusteriId) {
                option.style.display = 'block';
                if (hayvanSelect.value === option.value) {
                    mevcutHayvanSeciliMi = true;
                }
            } else {
                option.style.display = 'none';
            }
        });
        if (!mevcutHayvanSeciliMi && hayvanSelect.value !== "") {
            hayvanSelect.value = ""; // Uyumsuzsa seçimi kaldır.
        }
    }
    filtreleHayvanlar(); // Sayfa yüklendiğinde
    musteriSelect.addEventListener('change', filtreleHayvanlar);
});
</script>

<?php
include 'includes/footer.php';
?>