<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (mesajları session ile taşımak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Silinecek Randevunun ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $randevu_id = intval($_GET['id']);

    try {
        // Saklı yordamımızı kullanarak randevu sil
        // sp_Randevu_Sil(IN p_RandevuID INT)
        $sql_delete = "CALL sp_Randevu_Sil(:randevu_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':randevu_id', $randevu_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn(); // Saklı yordamdan dönen ROW_COUNT() değeri
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            $_SESSION['mesaj_randevu'] = "<div class='alert alert-success'>Randevu (ID: {$randevu_id}) başarıyla silindi.</div>";
        } else {
            $_SESSION['mesaj_randevu'] = "<div class='alert alert-warning'>Randevu silinemedi veya zaten silinmiş (ID: {$randevu_id}).</div>";
        }

    } catch (PDOException $e) {
        // Beklenmedik bir hata olursa:
        $_SESSION['mesaj_randevu'] = "<div class='alert alert-danger'>Randevu silinirken bir hata oluştu (ID: {$randevu_id}): " . $e->getMessage() . "</div>";
    }
} else {
    // ID yoksa veya geçerli değilse
    $_SESSION['mesaj_randevu'] = "<div class='alert alert-danger'>Geçersiz randevu ID'si. Silme işlemi yapılamadı.</div>";
}

// Her durumda randevu listesine geri dön
header("Location: randevular.php");
exit; // Yönlendirmeden sonra scriptin çalışmasını durdurmak önemlidir.
?>