<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (mesajları session ile taşımak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$fatura_id = null;
$musteri_id_yonlendirme = null; // Yönlendirme için opsiyonel müşteri ID'si

// Silinecek Fatura ID'sini ve opsiyonel Müşteri ID'sini Almak
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $fatura_id = intval($_GET['id']);
}
if (isset($_GET['musteri_id']) && is_numeric($_GET['musteri_id'])) {
    $musteri_id_yonlendirme = intval($_GET['musteri_id']);
}


if ($fatura_id !== null) {
    // Faturayı silmeden önce hangi müşteriye ait olduğunu öğrenelim (yönlendirme için)
    // Eğer URL'de musteri_id yoksa ve genel listeden siliniyorsa
    if ($musteri_id_yonlendirme === null) {
        try {
            $stmt_musteri = $pdo->prepare("SELECT MusteriID FROM Faturalar WHERE FaturaID = :fatura_id");
            $stmt_musteri->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
            $stmt_musteri->execute();
            $fatura_detay = $stmt_musteri->fetch();
            if ($fatura_detay) {
                $musteri_id_yonlendirme = $fatura_detay['MusteriID'];
            }
            $stmt_musteri->closeCursor();
        } catch (PDOException $e) {
            // Hata olursa $musteri_id_yonlendirme null kalır, genel listeye yönlenir.
        }
    }


    try {
        // Saklı yordamımızı kullanarak faturayı sil
        // sp_Fatura_Sil(IN p_FaturaID INT)
        // Bu işlem ON DELETE CASCADE ile FaturaDetaylari'nı da siler.
        // FaturaDetaylari silinirken de trigger'lar stokları günceller.
        $sql_delete = "CALL sp_Fatura_Sil(:fatura_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':fatura_id', $fatura_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn();
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            $_SESSION['mesaj_fatura'] = "<div class='alert alert-success'>Fatura (ID: {$fatura_id}) ve tüm detayları başarıyla silindi. İlgili stoklar güncellendi.</div>";
        } else {
            $_SESSION['mesaj_fatura'] = "<div class='alert alert-warning'>Fatura silinemedi veya zaten silinmiş (ID: {$fatura_id}).</div>";
        }

    } catch (PDOException $e) {
        // Normalde ON DELETE CASCADE ve triggerlar nedeniyle burada bir foreign key hatası beklemeyiz.
        // Ama başka bir beklenmedik hata olursa:
        $_SESSION['mesaj_fatura'] = "<div class='alert alert-danger'>Fatura silinirken bir hata oluştu (ID: {$fatura_id}): " . $e->getMessage() . "</div>";
    }
} else {
    $_SESSION['mesaj_fatura'] = "<div class='alert alert-danger'>Geçersiz fatura ID'si. Silme işlemi yapılamadı.</div>";
}

// Yönlendirme URL'sini belirle
$yonlendirme_url = "faturalar.php";
if ($musteri_id_yonlendirme !== null) {
    // Eğer fatura bir müşterinin özel listesinden siliniyorsa, o listeye geri dön.
    // Ancak sp_MusterininFaturalari_Listele yordamı FaturaID'yi içeriyor ama MusteriID'yi direkt içermiyor.
    // Bu yüzden silme linkinde müşteri ID'sini taşımak önemli.
    // `faturalar.php`'deki silme linki bunu yapacak şekilde güncellenmişti.
    $yonlendirme_url .= "?musteri_id=" . $musteri_id_yonlendirme;
}

header("Location: " . $yonlendirme_url);
exit;
?>