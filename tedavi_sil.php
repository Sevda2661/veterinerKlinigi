<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (mesajları session ile taşımak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Silinecek Tedavinin ID'sini ve opsiyonel olarak HayvanID'yi (yönlendirme için) Almak
$tedavi_id = null;
$hayvan_id_yonlendirme = null; // Yönlendirme için

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $tedavi_id = intval($_GET['id']);
}
if (isset($_GET['hayvan_id']) && is_numeric($_GET['hayvan_id'])) { // Eğer silme linkinden geliyorsa
    $hayvan_id_yonlendirme = intval($_GET['hayvan_id']);
}


if ($tedavi_id !== null) {
    // Tedavi silinmeden önce hangi hayvana ait olduğunu öğrenelim (yönlendirme için)
    if ($hayvan_id_yonlendirme === null) { // Eğer URL'de hayvan_id yoksa, DB'den çek
        try {
            $stmt_hayvan = $pdo->prepare("SELECT HayvanID FROM Tedaviler WHERE TedaviID = :tedavi_id");
            $stmt_hayvan->bindParam(':tedavi_id', $tedavi_id, PDO::PARAM_INT);
            $stmt_hayvan->execute();
            $tedavi_detay = $stmt_hayvan->fetch();
            if ($tedavi_detay) {
                $hayvan_id_yonlendirme = $tedavi_detay['HayvanID'];
            }
            $stmt_hayvan->closeCursor();
        } catch (PDOException $e) {
            // Hata olursa hayvan_id_yonlendirme null kalır, genel listeye yönlenir.
        }
    }

    try {
        // Saklı yordamımızı kullanarak tedavi sil
        // sp_Tedavi_Sil(IN p_TedaviID INT)
        $sql_delete = "CALL sp_Tedavi_Sil(:tedavi_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':tedavi_id', $tedavi_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn();
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            $_SESSION['mesaj_tedavi'] = "<div class='alert alert-success'>Tedavi kaydı (ID: {$tedavi_id}) başarıyla silindi.</div>";
        } else {
            $_SESSION['mesaj_tedavi'] = "<div class='alert alert-warning'>Tedavi kaydı silinemedi veya zaten silinmiş (ID: {$tedavi_id}).</div>";
        }

    } catch (PDOException $e) {
        // Yabancı anahtar kısıtlaması (foreign key constraint) hatasını yakalama (örn: FaturaDetaylari)
        if ($e->errorInfo[1] == 1451) {
            $_SESSION['mesaj_tedavi'] = "<div class='alert alert-danger'>HATA: Bu tedavi kaydı (ID: {$tedavi_id}) silinemez çünkü bir faturaya işlenmiş olabilir. Lütfen önce ilgili fatura detayını kontrol edin/silin.</div>";
        } else {
            $_SESSION['mesaj_tedavi'] = "<div class='alert alert-danger'>Tedavi kaydı silinirken bir hata oluştu (ID: {$tedavi_id}): " . $e->getMessage() . "</div>";
        }
    }
} else {
    $_SESSION['mesaj_tedavi'] = "<div class='alert alert-danger'>Geçersiz tedavi ID'si. Silme işlemi yapılamadı.</div>";
}

// Yönlendirme URL'sini belirle
$yonlendirme_url = "tedaviler.php";
if ($hayvan_id_yonlendirme !== null) {
    $yonlendirme_url .= "?hayvan_id=" . $hayvan_id_yonlendirme;
}

header("Location: " . $yonlendirme_url);
exit;
?>