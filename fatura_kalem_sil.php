<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (mesajları session ile taşımak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$fatura_detay_id = null;
$fatura_id_yonlendirme = null; // Geri yönlendirme için ana fatura ID'si

// Silinecek Fatura Detay ID'sini ve Ana Fatura ID'sini Almak
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $fatura_detay_id = intval($_GET['id']);
}
if (isset($_GET['fatura_id']) && is_numeric($_GET['fatura_id'])) {
    $fatura_id_yonlendirme = intval($_GET['fatura_id']);
}

if ($fatura_detay_id !== null && $fatura_id_yonlendirme !== null) {
    try {
        // Saklı yordamımızı kullanarak fatura detayını sil
        // sp_FaturaDetay_Sil(IN p_FaturaDetayID INT)
        $sql_delete = "CALL sp_FaturaDetay_Sil(:fatura_detay_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':fatura_detay_id', $fatura_detay_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn();
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-success'>Fatura kalemi (Detay ID: {$fatura_detay_id}) başarıyla silindi. Fatura toplamı ve stoklar (varsa) güncellendi.</div>";
        } else {
            $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-warning'>Fatura kalemi silinemedi veya zaten silinmiş (Detay ID: {$fatura_detay_id}).</div>";
        }

    } catch (PDOException $e) {
        // Normalde ON DELETE CASCADE olduğu için FaturaDetayları silinirken foreign key hatası beklemeyiz.
        // Ama başka bir beklenmedik hata olursa:
        $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-danger'>Fatura kalemi silinirken bir hata oluştu (Detay ID: {$fatura_detay_id}): " . $e->getMessage() . "</div>";
    }
} else {
    if ($fatura_id_yonlendirme === null) {
        // Eğer ana fatura ID'si de gelmediyse, genel faturalar listesine yönlendir ve hata ver
        $_SESSION['mesaj_fatura'] = "<div class='alert alert-danger'>Geçersiz fatura veya detay ID'si. Silme işlemi yapılamadı.</div>";
        header("Location: faturalar.php");
        exit;
    }
    $_SESSION['mesaj_fatura_goruntule'] = "<div class='alert alert-danger'>Geçersiz fatura detayı ID'si. Silme işlemi yapılamadı.</div>";
}

// Her durumda ilgili faturanın görüntüleme sayfasına geri dön
if ($fatura_id_yonlendirme !== null) {
    header("Location: fatura_goruntule.php?id=" . $fatura_id_yonlendirme);
} else {
    // Bu duruma normalde gelinmemeli, ama bir fallback olarak
    header("Location: faturalar.php");
}
exit;
?>