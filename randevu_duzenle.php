<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Randevu Bilgilerini Düzenle - Veteriner Kliniği";
include 'includes/header.php';

$randevu = null; // Randevu bilgilerini tutacak değişken
$randevu_id = null;

// Formdan gelen veya veritabanından çekilen verileri tutmak için
$form_values = [
    'hayvan_adi' => '', // Göstermek için
    'veteriner_adi' => '', // Göstermek için
    'randevu_tarihi' => '',
    'randevu_saati' => '',
    'randevu_nedeni' => ''
];

// 1. Düzenlenecek Randevunun ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $randevu_id = intval($_GET['id']);

    // 2. Form Gönderilmişse (POST isteği ile) Güncelleme İşlemini Yap
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $randevu_tarihi_post = trim($_POST['randevu_tarihi']);
        $randevu_saati_post = trim($_POST['randevu_saati']);
        $randevu_nedeni_post = trim($_POST['randevu_nedeni']);

        // POST edilen verileri form değerleri için sakla
        // Hayvan ve veteriner adı değişmeyeceği için onları bir önceki GET'ten (veya DB'den) gelen $form_values'tan koruyalım
        $form_values['randevu_tarihi'] = $randevu_tarihi_post;
        $form_values['randevu_saati'] = $randevu_saati_post;
        $form_values['randevu_nedeni'] = $randevu_nedeni_post;
        // Hayvan ve Veteriner adını korumak için, sayfa başında çekilen $randevu bilgisinden alabiliriz.
        // Bu, hata durumunda bile bu bilgilerin doğru kalmasını sağlar.
        // Aşağıdaki GET bloğunda $randevu çekildikten sonra bu değerler $form_values'a atanacak.

        $hatalar = [];
        if (empty($randevu_tarihi_post)) $hatalar[] = "Randevu tarihi zorunludur.";
        if (empty($randevu_saati_post)) $hatalar[] = "Randevu saati zorunludur.";

        $tarih_obj = DateTime::createFromFormat('Y-m-d', $randevu_tarihi_post);
        if (!$tarih_obj || $tarih_obj->format('Y-m-d') !== $randevu_tarihi_post) {
            $hatalar[] = "Geçersiz randevu tarihi formatı. (YYYY-AA-GG)";
        }
        // Geçmiş tarih kontrolü düzenlemede daha esnek olabilir, belki sadece uyarı verilir.
        // Ama yine de mantıksız bir düzenleme olmaması için kontrol edilebilir.
        // Şimdilik ekleme sayfasındaki gibi katı bir kontrol yapalım.
        elseif ($tarih_obj < new DateTime('today') && $randevu_tarihi_post != (new DateTime('today'))->format('Y-m-d')) {
             $hatalar[] = "Randevu tarihi geçmiş bir tarih olamaz.";
        }


        $saat_obj = DateTime::createFromFormat('H:i', $randevu_saati_post);
        if (!$saat_obj || $saat_obj->format('H:i') !== $randevu_saati_post) {
            $saat_obj_saniye_ile = DateTime::createFromFormat('H:i:s', $randevu_saati_post);
            if (!$saat_obj_saniye_ile || $saat_obj_saniye_ile->format('H:i:s') !== $randevu_saati_post) {
                $hatalar[] = "Geçersiz randevu saati formatı. (SS:DD veya SS:DD:ss)";
            } else {
                $randevu_saati_db = $saat_obj_saniye_ile->format('H:i:s');
            }
        } else {
            $randevu_saati_db = $saat_obj->format('H:i:s');
        }

        if (!empty($hatalar)) {
            $_SESSION['mesaj_randevu_duzenle'] = "<div class='alert alert-danger'><ul>";
            foreach ($hatalar as $hata) {
                $_SESSION['mesaj_randevu_duzenle'] .= "<li>" . htmlspecialchars($hata) . "</li>";
            }
            $_SESSION['mesaj_randevu_duzenle'] .= "</ul></div>";
            // Hata varsa, $form_values zaten POST verilerini tutuyor.
            // Hayvan ve Veteriner adını tekrar yüklemek için $randevu'yu tekrar çekmemiz gerekebilir.
            // Veya $form_values'a GET ile gelen hayvan/veteriner adını en başta atamalıyız. (Aşağıda yapıldı)
        } else {
            // Saklı yordam: sp_Randevu_Guncelle(IN p_RandevuID INT, IN p_RandevuTarihi DATE, IN p_RandevuSaati TIME, IN p_RandevuNedeni TEXT)
            // Bu yordam HayvanID ve VeterinerID'yi değiştirmiyor, sadece tarih, saat, neden.
            $sql_update = "CALL sp_Randevu_Guncelle(:randevu_id, :randevu_tarihi, :randevu_saati, :randevu_nedeni)";
            try {
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':randevu_id', $randevu_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':randevu_tarihi', $randevu_tarihi_post, PDO::PARAM_STR);
                $stmt_update->bindParam(':randevu_saati', $randevu_saati_db, PDO::PARAM_STR);
                $stmt_update->bindParam(':randevu_nedeni', $randevu_nedeni_post, PDO::PARAM_STR);
                
                $stmt_update->execute();
                $guncellenen_satir_sayisi = $stmt_update->fetchColumn();
                $stmt_update->closeCursor();

                if ($guncellenen_satir_sayisi > 0) {
                    $_SESSION['mesaj_randevu'] = "<div class='alert alert-success'>Randevu bilgileri (ID: {$randevu_id}) başarıyla güncellendi.</div>";
                    header("Location: randevular.php");
                    exit;
                } else {
                    $_SESSION['mesaj_randevu_duzenle'] = "<div class='alert alert-info'>Randevu bilgilerinde herhangi bir değişiklik yapılmadı veya kayıt bulunamadı.</div>";
                }
            } catch (PDOException $e) {
                // Randevu çakışması hatası (UK_Randevu) güncellemede de olabilir,
                // eğer sp_Randevu_Guncelle içinde bir kontrol yoksa veya trigger ile yapılıyorsa.
                // Mevcut sp_Randevu_Guncelle yordamımız HayvanID ve VeterinerID'yi değiştirmediği için,
                // sadece tarih/saat değişimi ile aynı hayvan/veteriner için çakışma olabilir.
                // Veritabanındaki UK_Randevu (HayvanID, VeterinerID, RandevuTarihi, RandevuSaati) UNIQUE
                // constraint bu durumu yakalayacaktır.
                if ($e->errorInfo[1] == 1062) {
                     $_SESSION['mesaj_randevu_duzenle'] = "<div class='alert alert-danger'>HATA: Güncellenen tarih ve saatte bu hayvan için bu veterinerle zaten bir randevu mevcut.</div>";
                } else {
                    $_SESSION['mesaj_randevu_duzenle'] = "<div class='alert alert-danger'>Randevu güncellenirken bir veritabanı hatası oluştu: " . $e->getMessage() . "</div>";
                }
            }
        }
        // Hata mesajı varsa veya değişiklik yoksa, sayfa yeniden yüklenecek.
        // Bu durumda $form_values zaten POST verileriyle dolu.
        // GET'ten gelen hayvan/veteriner adını tekrar yüklemek için aşağıdaki GET bloğundaki $randevu çekimi
        // $form_values['hayvan_adi'] ve $form_values['veteriner_adi']'nı tekrar doldurmalı.
    }

    // 3. Sayfa ilk yüklendiğinde (GET) veya POST sonrası (güncel bilgileri veya hata sonrası formu doldurmak için)
    // Saklı yordam: sp_Randevu_GetirByID(IN p_RandevuID INT)
    // Bu yordam H.Adi AS HayvanAdi, V.Adi AS VeterinerAdi, V.Soyadi AS VeterinerSoyadi getiriyordu.
    $sql_select = "CALL sp_Randevu_GetirByID(:randevu_id)";
    try {
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->bindParam(':randevu_id', $randevu_id, PDO::PARAM_INT);
        $stmt_select->execute();
        $randevu_db = $stmt_select->fetch(PDO::FETCH_ASSOC);
        $stmt_select->closeCursor();

        if ($randevu_db) {
            $randevu = $randevu_db; // Formu göstermek için kontrol
            // Eğer POST'tan bir hata mesajı varsa, $form_values POST verileriyle dolu olmalı.
            // Sadece hayvan ve veteriner adını $randevu_db'den alıp $form_values'a güncelleyelim.
            $form_values['hayvan_adi'] = $randevu_db['HayvanAdi'];
            $form_values['veteriner_adi'] = $randevu_db['VeterinerAdi'] . ' ' . $randevu_db['VeterinerSoyadi'];

            // Eğer POST'tan bir işlem yapılmadıysa (yani GET isteği) veya POST sonrası mesaj yoksa
            // $form_values'u veritabanından gelenlerle doldur.
            if ($_SERVER["REQUEST_METHOD"] != "POST" || empty($_SESSION['mesaj_randevu_duzenle'])) {
                 $form_values['randevu_tarihi'] = $randevu_db['RandevuTarihi'];
                 // Saati H:i formatına çevir (veritabanında H:i:s olabilir)
                 $form_values['randevu_saati'] = $randevu_db['RandevuSaati'] ? date('H:i', strtotime($randevu_db['RandevuSaati'])) : '';
                 $form_values['randevu_nedeni'] = $randevu_db['RandevuNedeni'];
            }
             // Eğer POST'tan hata geldiyse, $form_values['randevu_tarihi'], ['randevu_saati'], ['randevu_nedeni'] zaten POST'tan gelen değerleri içeriyor olacak.
        } else {
            $_SESSION['mesaj_randevu'] = "<div class='alert alert-warning'>Düzenlenecek randevu (ID: {$randevu_id}) bulunamadı.</div>";
            header("Location: randevular.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['mesaj_randevu_duzenle'] = "<div class='alert alert-danger'>Randevu bilgileri alınırken bir hata oluştu: " . $e->getMessage() . "</div>";
        $randevu = null; // Hata durumunda formu gösterme
    }

} else {
    $_SESSION['mesaj_randevu'] = "<div class='alert alert-danger'>Geçersiz randevu ID'si.</div>";
    header("Location: randevular.php");
    exit;
}
?>

<div class="container mt-4">
    <h2>Randevu Bilgilerini Düzenle (ID: <?php echo htmlspecialchars($randevu_id); ?>)</h2>

    <?php
    if (isset($_SESSION['mesaj_randevu_duzenle'])) {
        echo $_SESSION['mesaj_randevu_duzenle'];
        unset($_SESSION['mesaj_randevu_duzenle']);
    }
    ?>

    <?php if ($randevu): // Sadece randevu bilgileri başarıyla çekildiyse formu göster ?>
    <form action="randevu_duzenle.php?id=<?php echo htmlspecialchars($randevu_id); ?>" method="POST">
        <div class="mb-3">
            <label class="form-label">Hayvan:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($form_values['hayvan_adi']); ?>" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Veteriner:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($form_values['veteriner_adi']); ?>" readonly>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="randevu_tarihi" class="form-label">Randevu Tarihi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="randevu_tarihi" name="randevu_tarihi" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($form_values['randevu_tarihi']))); ?>" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="randevu_saati" class="form-label">Randevu Saati <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="randevu_saati" name="randevu_saati" value="<?php echo htmlspecialchars($form_values['randevu_saati']); ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="randevu_nedeni" class="form-label">Randevu Nedeni</label>
            <textarea class="form-control" id="randevu_nedeni" name="randevu_nedeni" rows="3"><?php echo htmlspecialchars($form_values['randevu_nedeni']); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Güncelle</button>
        <a href="randevular.php" class="btn btn-secondary">İptal</a>
    </form>
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>