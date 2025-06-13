<?php
// Session'ı başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$sayfa_basligi = "Fatura Görüntüle/Yönet"; // <title> için
$sayfa_basligi_dinamik = "Fatura Detayları"; // H2 için
include 'includes/header.php';

$fatura_id = null;
$fatura = null;
$fatura_detaylari = [];
$e_var = null; // Hata kontrolü için

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $fatura_id = intval($_GET['id']);
    $sayfa_basligi_dinamik = "Fatura Detayları (ID: " . htmlspecialchars($fatura_id) . ")";
    $sayfa_basligi = "Fatura #" . htmlspecialchars($fatura_id) . " Detayları - Veteriner Kliniği";


    // Fatura ana bilgilerini çek
    try {
        $stmt_fatura = $pdo->prepare("CALL sp_Fatura_GetirByID(:fatura_id)");
        $stmt_fatura->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
        $stmt_fatura->execute();
        $fatura = $stmt_fatura->fetch();
        $stmt_fatura->closeCursor();

        if (!$fatura) {
            $_SESSION['mesaj_fatura'] = "<div class='alert alert-danger'>Fatura bulunamadı (ID: {$fatura_id}).</div>";
            header("Location: faturalar.php");
            exit;
        }

        // Faturaya ait detayları çek
        $stmt_detaylar = $pdo->prepare("CALL sp_FaturaninDetaylari_Listele(:fatura_id)");
        $stmt_detaylar->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
        $stmt_detaylar->execute();
        $fatura_detaylari = $stmt_detaylar->fetchAll();
        $stmt_detaylar->closeCursor();

    } catch (PDOException $e) {
        $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-danger'>Fatura bilgileri alınırken hata: " . $e->getMessage() . "</div>";
        $e_var = $e; // Hata varsa formu/listeyi göstermeyi engelleyebiliriz
    }

} else {
    $_SESSION['mesaj_fatura'] = "<div class='alert alert-danger'>Geçersiz Fatura ID'si.</div>";
    header("Location: faturalar.php");
    exit;
}

// Tedavileri çek (yeni kalem ekleme formu için)
$tedaviler_listesi = [];
if ($fatura && $fatura['HayvanID'] && !$e_var) { 
    try {
        $stmt_tedaviler = $pdo->prepare("CALL sp_HayvaninTedavileri_Listele(:hayvan_id)");
        $stmt_tedaviler->bindParam(':hayvan_id', $fatura['HayvanID'], PDO::PARAM_INT);
        $stmt_tedaviler->execute();
        $tedaviler_listesi = $stmt_tedaviler->fetchAll();
        $stmt_tedaviler->closeCursor();
    } catch (PDOException $e) {
        // Bu hata mesajını session'a atmak yerine direkt basabiliriz veya loglayabiliriz.
        // $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-warning'>Tedavi listesi alınırken hata: " . $e->getMessage() . "</div>";
    }
}


// İlaçları çek (yeni kalem ekleme formu için)
$ilaclar_listesi = [];
if (!$e_var) { // Ana fatura bilgileri çekilirken hata olmamışsa ilaçları çek
    try {
        // sp_Ilaclar_Listele yordamının BirimSatisFiyati'nı da getirdiğinden emin olmalıyız.
        $stmt_ilaclar = $pdo->query("CALL sp_Ilaclar_Listele()"); 
        $ilaclar_listesi = $stmt_ilaclar->fetchAll();
        $stmt_ilaclar->closeCursor();
    } catch (PDOException $e) {
        // $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-warning'>İlaç listesi alınırken hata: " . $e->getMessage() . "</div>";
    }
}


// Form gönderildi mi (Yeni Fatura Kalemi Ekleme)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kalem_ekle']) && $fatura && !$e_var) { // Fatura varsa ve hata yoksa işlem yap
    $tip = $_POST['kalem_tipi'] ?? '';
    $tedavi_id_post = filter_input(INPUT_POST, 'tedavi_id', FILTER_VALIDATE_INT);
    $ilac_id_post = filter_input(INPUT_POST, 'ilac_id', FILTER_VALIDATE_INT);
    $miktar_post_raw = $_POST['miktar'];
    $birim_fiyat_post_raw = $_POST['birim_fiyat'];
    $aciklama_post = trim($_POST['aciklama']);

    $hatalar_kalem = [];

    $miktar_post = filter_var($miktar_post_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($miktar_post === false) {
        $hatalar_kalem[] = "Miktar geçerli bir pozitif tam sayı olmalıdır.";
    }

    $birim_fiyat_post = filter_var(str_replace(',', '.', $birim_fiyat_post_raw), FILTER_VALIDATE_FLOAT);
     if ($birim_fiyat_post === false || $birim_fiyat_post < 0) { // 0 fiyatlı kalem olabilir mi? Genelde hayır.
        $hatalar_kalem[] = "Birim fiyat geçerli bir pozitif sayı olmalıdır.";
    }


    if ($tip === 'tedavi') {
        if (empty($tedavi_id_post)) $hatalar_kalem[] = "Tedavi seçimi zorunludur.";
        $ilac_id_post = null; 
    } elseif ($tip === 'ilac') {
        if (empty($ilac_id_post)) $hatalar_kalem[] = "İlaç seçimi zorunludur.";
        // Stok kontrolü (isteğe bağlı olarak PHP tarafında da yapılabilir, JS'ye ek olarak)
        // Trigger zaten bu kontrolü yapacaktır.
        $tedavi_id_post = null; 
    } elseif ($tip === 'diger') {
        if (empty($aciklama_post)) $hatalar_kalem[] = "Diğer kalem için açıklama zorunludur.";
        $tedavi_id_post = null;
        $ilac_id_post = null;
    } else {
        $hatalar_kalem[] = "Geçersiz kalem tipi.";
    }

    if (!empty($hatalar_kalem)) {
        $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-danger'><ul>";
        foreach ($hatalar_kalem as $hata) {
            $_SESSION['mesaj_fatura_goruntule'] .= "<li>" . htmlspecialchars($hata) . "</li>";
        }
        $_SESSION['mesaj_fatura_goruntule'] .= "</ul></div>";
    } else {
        $sql_ekle_detay = "CALL sp_FaturaDetay_Ekle(:fatura_id, :tedavi_id, :ilac_id, :miktar, :birim_fiyat, :aciklama)";
        try {
            $stmt_ekle = $pdo->prepare($sql_ekle_detay);
            $stmt_ekle->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
            $stmt_ekle->bindParam(':tedavi_id', $tedavi_id_post, $tedavi_id_post === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt_ekle->bindParam(':ilac_id', $ilac_id_post, $ilac_id_post === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt_ekle->bindParam(':miktar', $miktar_post, PDO::PARAM_INT);
            $stmt_ekle->bindParam(':birim_fiyat', $birim_fiyat_post, PDO::PARAM_STR); 
            $stmt_ekle->bindParam(':aciklama', $aciklama_post, ($aciklama_post === '' && ($tip === 'tedavi' || $tip === 'ilac')) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            $stmt_ekle->execute();
            $yeniDetayID = $stmt_ekle->fetchColumn();
            $stmt_ekle->closeCursor();

            $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-success'>Fatura kalemi (Detay ID: {$yeniDetayID}) başarıyla eklendi.</div>";
            header("Location: fatura_goruntule.php?id=" . $fatura_id);
            exit;
        } catch (PDOException $e) {
            $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-danger'>Fatura kalemi eklenirken hata: " . $e->getMessage() . "</div>";
        }
    }
    // Hata varsa, sayfa zaten POST ile aynı URL'de kalacak, mesaj gösterilecek.
    // Başarılı ekleme sonrası yönlendirme yapıldı.
}


if (!$fatura || $e_var) { // Eğer fatura çekilemediyse veya ilk sorguda hata oluştuysa
    // Mesaj zaten session'da veya ekrana basılmış olabilir.
    // Sadece footer'ı include edip çıkalım ki aşağıdaki HTML render edilmesin.
    echo '<div class="container mt-4">';
    if(isset($_SESSION['mesaj_fatura_goruntule'])) echo $_SESSION['mesaj_fatura_goruntule'];
    echo '<a href="faturalar.php" class="btn btn-primary">Faturalar Listesine Dön</a>';
    echo '</div>';
    include 'includes/footer.php';
    exit;
}
?>

<div class="container mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-8">
            <h2 class="page-title"><?php echo htmlspecialchars($sayfa_basligi_dinamik); ?></h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="faturalar.php" class="btn btn-outline-secondary mb-2 me-2"><i class="fas fa-list me-1"></i>Tüm Faturalar</a>
            <a href="fatura_duzenle.php?id=<?php echo $fatura['FaturaID']; ?>" class="btn btn-primary mb-2"><i class="fas fa-edit me-1"></i>Faturayı Düzenle</a>
        </div>
    </div>

    <?php
    if (isset($_SESSION['mesaj_fatura_goruntule'])) {
        echo $_SESSION['mesaj_fatura_goruntule'];
        unset($_SESSION['mesaj_fatura_goruntule']);
    }
    ?>

    <div class="card mb-4 card-fatura-bilgi shadow-sm">
        <div class="card-header">Fatura Bilgileri</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Müşteri:</strong> 
                        <a href="musteri_duzenle.php?id=<?php echo $fatura['MusteriID']; ?>">
                            <?php echo htmlspecialchars($fatura['MusteriAdi'] . ' ' . $fatura['MusteriSoyadi']); ?>
                        </a>
                    </p>
                    <?php if ($fatura['HayvanID'] && $fatura['HayvanAdi']): ?>
                    <p><strong>Hayvan:</strong> 
                        <a href="hayvan_duzenle.php?id=<?php echo $fatura['HayvanID']; ?>">
                            <?php echo htmlspecialchars($fatura['HayvanAdi']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <p><strong>Fatura Tarihi:</strong> <?php echo htmlspecialchars(date('d.m.Y', strtotime($fatura['FaturaTarihi']))); ?></p>
                    <p><strong>Ödeme Durumu:</strong> 
                        <span class="badge bg-<?php echo ($fatura['OdemeDurumu'] == 'Ödendi') ? 'success' : (($fatura['OdemeDurumu'] == 'Kısmi Ödendi') ? 'warning text-dark' : 'danger'); ?>">
                            <?php echo htmlspecialchars($fatura['OdemeDurumu']); ?>
                        </span>
                    </p>
                </div>
            </div>
            <hr>
            <div class="text-end">
                <h4 class="mt-0">Toplam Tutar: <?php echo htmlspecialchars(number_format($fatura['ToplamTutar'], 2, ',', '.')); ?> TL</h4>
            </div>
        </div>
    </div>

    <h4 class="page-title" style="font-size: 1.5rem; border-bottom-width: 2px;">Fatura Kalemleri</h4>
    <?php if (empty($fatura_detaylari)): ?>
        <div class="alert alert-info">Bu faturaya henüz bir kalem eklenmemiş.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Açıklama/Ürün</th>
                        <th class="text-center">Miktar</th>
                        <th class="text-end">Birim Fiyat</th>
                        <th class="text-end">Ara Toplam</th>
                        <th class="text-center">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fatura_detaylari as $detay): ?>
                    <tr>
                        <td>
                            <?php 
                            if ($detay['Tani']) echo "<strong>Tedavi:</strong> " . htmlspecialchars($detay['Tani']);
                            elseif ($detay['IlacAdi']) echo "<strong>İlaç:</strong> " . htmlspecialchars($detay['IlacAdi']);
                            else echo htmlspecialchars($detay['Aciklama']);
                            ?>
                        </td>
                        <td class="text-center"><?php echo htmlspecialchars($detay['Miktar']); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars(number_format($detay['BirimFiyat'], 2, ',', '.')); ?> TL</td>
                        <td class="text-end"><?php echo htmlspecialchars(number_format($detay['AraToplam'], 2, ',', '.')); ?> TL</td>
                        <td class="text-center">
                            <a href="fatura_kalem_sil.php?id=<?php echo $detay['FaturaDetayID']; ?>&fatura_id=<?php echo $fatura_id; ?>" 
                               class="btn btn-xs btn-danger" title="Kalemi Sil"
                               onclick="return confirm('Bu fatura kalemini silmek istediğinize emin misiniz?');">
                               <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <hr>
    <h4 class="page-title" style="font-size: 1.5rem; border-bottom-width: 2px;">Yeni Fatura Kalemi Ekle</h4>
    <form id="kalemEkleForm" action="fatura_goruntule.php?id=<?php echo $fatura_id; ?>" method="POST" class="border p-3 rounded bg-light shadow-sm">
        <input type="hidden" name="kalem_ekle" value="1">
        <div class="row align-items-end">
            <div class="col-md-3 mb-3">
                <label for="kalem_tipi" class="form-label fw-bold">Kalem Tipi:</label>
                <select class="form-select" id="kalem_tipi" name="kalem_tipi">
                    <option value="diger">Diğer/Hizmet</option>
                    <?php if($fatura['HayvanID']): ?>
                    <option value="tedavi">Tedavi Kaydı</option>
                    <?php endif; ?>
                    <option value="ilac">İlaç</option>
                </select>
            </div>

            <div id="tedavi_secimi_div" class="col-md-9 mb-3" style="display:none;">
                <label for="tedavi_id" class="form-label fw-bold">Tedavi Seçin:</label>
                <select class="form-select" id="tedavi_id" name="tedavi_id">
                    <option value="">Tedavi Seçiniz...</option>
                    <?php foreach($tedaviler_listesi as $tedavi_item): ?>
                        <option value="<?php echo htmlspecialchars($tedavi_item['TedaviID']); ?>" data-tani="<?php echo htmlspecialchars(substr($tedavi_item['Tani'], 0, 50) . '... (Tarih: '.date('d.m.Y', strtotime($tedavi_item['TedaviTarihi'])).')'); ?>">
                            <?php echo htmlspecialchars(substr($tedavi_item['Tani'], 0, 70) . '... (Vet: ' . $tedavi_item['VeterinerAdiSoyadi'] . ' - '.date('d.m.Y', strtotime($tedavi_item['TedaviTarihi'])).')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="ilac_secimi_div" class="col-md-9 mb-3" style="display:none;">
                <label for="ilac_id" class="form-label fw-bold">İlaç Seçin:</label>
                <select class="form-select" id="ilac_id" name="ilac_id">
                    <option value="">İlaç Seçiniz...</option>
                     <?php foreach($ilaclar_listesi as $ilac_item): ?>
                        <option value="<?php echo htmlspecialchars($ilac_item['IlacID']); ?>" 
                                data-stok="<?php echo htmlspecialchars($ilac_item['StokMiktari']); ?>" 
                                data-ilacadi="<?php echo htmlspecialchars($ilac_item['IlacAdi']); ?>"
                                data-fiyat="<?php echo htmlspecialchars($ilac_item['BirimSatisFiyati']); ?>"> 
                            <?php echo htmlspecialchars($ilac_item['IlacAdi'] . " (Stok: " . $ilac_item['StokMiktari'] . " | Fiyat: " . number_format($ilac_item['BirimSatisFiyati'], 2, ',', '.') . " TL)"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small id="stok_uyari" class="form-text text-danger d-block mt-1" style="display:none;"></small>
            </div>
        </div>
        
        <div class="mb-3"> 
            <label for="aciklama" class="form-label fw-bold">Açıklama (Faturada Görünecek):</label>
            <input type="text" class="form-control" id="aciklama" name="aciklama">
        </div>

        <div class="row align-items-end">
            <div class="col-md-3 mb-3">
                <label for="miktar" class="form-label fw-bold">Miktar:</label>
                <input type="number" class="form-control" id="miktar" name="miktar" value="1" min="1" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="birim_fiyat" class="form-label fw-bold">Birim Fiyat (TL):</label>
                <input type="text" class="form-control" id="birim_fiyat" name="birim_fiyat" placeholder="Örn: 50.00" step="0.01" min="0" required>
            </div>
            <div class="col-md-5 mb-3 d-grid">
                <button type="submit" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Kalemi Faturaya Ekle</button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const kalemTipiSelect = document.getElementById('kalem_tipi');
    const tedaviDiv = document.getElementById('tedavi_secimi_div');
    const ilacDiv = document.getElementById('ilac_secimi_div');
    const aciklamaInput = document.getElementById('aciklama');
    const miktarInput = document.getElementById('miktar');
    const birimFiyatInput = document.getElementById('birim_fiyat');
    const ilacSelect = document.getElementById('ilac_id');
    const tedaviSelect = document.getElementById('tedavi_id');
    const stokUyari = document.getElementById('stok_uyari');

    function resetKalemFormFields(clearAciklama = true) {
        tedaviSelect.value = '';
        ilacSelect.value = '';
        if(clearAciklama) aciklamaInput.value = '';
        miktarInput.value = 1;
        birimFiyatInput.value = '';
        stokUyari.style.display = 'none';
    }

    function toggleKalemAlanlari() {
        const tip = kalemTipiSelect.value;
        tedaviDiv.style.display = 'none';
        ilacDiv.style.display = 'none';
        aciklamaInput.required = false; 
        birimFiyatInput.readOnly = false; // Varsayılan olarak düzenlenebilir

        if (tip === 'tedavi') {
            tedaviDiv.style.display = 'block';
            resetKalemFormFields(false); // Açıklamayı silme, tedavi seçince dolacak
        } else if (tip === 'ilac') {
            ilacDiv.style.display = 'block';
            birimFiyatInput.readOnly = true; // İlaç fiyatı otomatik, değiştirilemesin
            resetKalemFormFields(false); // Açıklamayı silme, ilaç seçince dolacak
        } else { // diger
            aciklamaInput.required = true;
            resetKalemFormFields(true); // Diğer seçilince açıklamayı da temizle ki kullanıcı kendi girsin
        }
    }

    kalemTipiSelect.addEventListener('change', toggleKalemAlanlari);

    tedaviSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        birimFiyatInput.value = ''; // Tedavi için fiyat manuel girilecek
        if (selectedOption && selectedOption.value !== "") {
            aciklamaInput.value = "Tedavi Hizmeti: " + selectedOption.dataset.tani;
        } else {
            aciklamaInput.value = '';
        }
    });

    ilacSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        stokUyari.style.display = 'none';
        birimFiyatInput.value = '';
        if (selectedOption && selectedOption.value !== "") {
            aciklamaInput.value = "İlaç: " + selectedOption.dataset.ilacadi;
            birimFiyatInput.value = selectedOption.dataset.fiyat || '';
            
            const stok = parseInt(selectedOption.dataset.stok);
            const istenenMiktar = parseInt(miktarInput.value);
            if (istenenMiktar > stok) {
                stokUyari.textContent = `Stokta ${stok} adet var. İstenen miktar: ${istenenMiktar}.`;
                stokUyari.style.display = 'block';
            }
        } else {
            aciklamaInput.value = '';
        }
    });
    
    miktarInput.addEventListener('input', function() {
        stokUyari.style.display = 'none';
        if (kalemTipiSelect.value === 'ilac' && ilacSelect.value !== "") {
            const selectedOption = ilacSelect.options[ilacSelect.selectedIndex];
            const stok = parseInt(selectedOption.dataset.stok);
            const istenenMiktar = parseInt(this.value);
            if (istenenMiktar > stok) {
                stokUyari.textContent = `Stokta ${stok} adet var. İstenen miktar: ${istenenMiktar}.`;
                stokUyari.style.display = 'block';
            }
        }
    });

    toggleKalemAlanlari(); // Sayfa ilk yüklendiğinde
    // Başlangıçta 'ilac' seçiliyse ve bir ilaç seçilmişse fiyatı doldur (form verileri session'dan gelirse)
    if (kalemTipiSelect.value === 'ilac' && ilacSelect.value !== "") {
        const selectedOption = ilacSelect.options[ilacSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.fiyat) {
             birimFiyatInput.value = selectedOption.dataset.fiyat;
        }
    }
});
</script>

<?php
include 'includes/footer.php';
?>