<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (mesajları session ile taşımak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Silinecek İlacın ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $ilac_id = intval($_GET['id']);

    try {
        // Saklı yordamımızı kullanarak ilacı sil
        // sp_Ilac_Sil(IN p_IlacID INT)
        $sql_delete = "CALL sp_Ilac_Sil(:ilac_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':ilac_id', $ilac_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn();
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            $_SESSION['mesaj_ilac'] = "<div class='alert alert-success'>İlaç (ID: {$ilac_id}) başarıyla silindi.</div>";
        } else {
            $_SESSION['mesaj_ilac'] = "<div class='alert alert-warning'>İlaç silinemedi veya zaten silinmiş (ID: {$ilac_id}).</div>";
        }

    } catch (PDOException $e) {
        // Yabancı anahtar kısıtlaması (foreign key constraint) hatasını yakalama (örn: FaturaDetaylari)
        if ($e->errorInfo[1] == 1451) {
            $_SESSION['mesaj_ilac'] = "<div class='alert alert-danger'>HATA: Bu ilaç (ID: {$ilac_id}) silinemez çünkü bir faturaya işlenmiş olabilir. Lütfen önce ilgili fatura detayını kontrol edin/silin.</div>";
        } else {
            $_SESSION['mesaj_ilac'] = "<div class='alert alert-danger'>İlaç silinirken bir hata oluştu (ID: {$ilac_id}): " . $e->getMessage() . "</div>";
        }
    }
} else {
    $_SESSION['mesaj_ilac'] = "<div class='alert alert-danger'>Geçersiz ilaç ID'si. Silme işlemi yapılamadı.</div>";
}

// Her durumda ilaç listesine geri dön
header("Location: ilaclar.php");
exit;
?>