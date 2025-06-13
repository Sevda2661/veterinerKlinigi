<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (isteğe bağlı, mesajları session ile taşımak için)
// session_start(); // Eğer mesajları session ile taşıyacaksanız bu satırı açın

$mesaj_silme = ""; // Başlangıçta mesaj boş

// Silinecek Hayvanın ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $hayvan_id = intval($_GET['id']);

    try {
        // Saklı yordamımızı kullanarak hayvan sil
        // sp_Hayvan_Sil(IN p_HayvanID INT)
        $sql_delete = "CALL sp_Hayvan_Sil(:hayvan_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':hayvan_id', $hayvan_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn(); // Saklı yordamdan dönen ROW_COUNT() değeri
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            // $mesaj_silme = "<div class='alert alert-success'>Hayvan ve ilişkili kayıtları başarıyla silindi.</div>";
            // Session ile mesaj taşımak daha iyi olur, çünkü hemen yönlendireceğiz.
            if (session_status() == PHP_SESSION_NONE) session_start(); // Eğer session başlatılmadıysa başlat
            $_SESSION['mesaj_hayvan'] = "<div class='alert alert-success'>Hayvan (ID: {$hayvan_id}) ve ilişkili kayıtları başarıyla silindi.</div>";
        } else {
            if (session_status() == PHP_SESSION_NONE) session_start();
            $_SESSION['mesaj_hayvan'] = "<div class='alert alert-warning'>Hayvan silinemedi veya zaten silinmiş (ID: {$hayvan_id}).</div>";
        }

    } catch (PDOException $e) {
        // Normalde ON DELETE CASCADE nedeniyle foreign key hatası beklemeyiz,
        // ama başka bir beklenmedik hata olursa diye:
        if (session_status() == PHP_SESSION_NONE) session_start();
        $_SESSION['mesaj_hayvan'] = "<div class='alert alert-danger'>Hayvan silinirken bir hata oluştu (ID: {$hayvan_id}): " . $e->getMessage() . "</div>";
    }
} else {
    // ID yoksa veya geçerli değilse
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['mesaj_hayvan'] = "<div class='alert alert-danger'>Geçersiz hayvan ID'si. Silme işlemi yapılamadı.</div>";
}

// Her durumda hayvan listesine geri dön
header("Location: hayvanlar.php");
exit; // Yönlendirmeden sonra scriptin çalışmasını durdurmak önemlidir.
?>