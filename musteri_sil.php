<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (mesajları session ile taşımak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Silinecek Müşterinin ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $musteri_id = intval($_GET['id']);

    try {
        // Saklı yordamımızı kullanarak müşteri sil
        // sp_Musteri_Sil(IN p_MusteriID INT)
        $sql_delete = "CALL sp_Musteri_Sil(:musteri_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn(); // Saklı yordamdan dönen ROW_COUNT() değeri
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            $_SESSION['mesaj_musteri'] = "<div class='alert alert-success'>Müşteri (ID: {$musteri_id}) başarıyla silindi.</div>";
        } else {
            $_SESSION['mesaj_musteri'] = "<div class='alert alert-warning'>Müşteri silinemedi veya zaten silinmiş (ID: {$musteri_id}).</div>";
        }

    } catch (PDOException $e) {
        // Yabancı anahtar kısıtlaması (foreign key constraint) hatasını yakalama
        // MySQL'de bu genellikle 1451 hata kodudur.
        if ($e->errorInfo[1] == 1451) {
            $_SESSION['mesaj_musteri'] = "<div class='alert alert-danger'>HATA: Bu müşteri (ID: {$musteri_id}) silinemez çünkü bu müşteriye bağlı kayıtlar (örneğin hayvanlar, faturalar) bulunmaktadır. Lütfen önce bu kayıtları silin veya başka bir kayda bağlayın.</div>";
        } else {
            $_SESSION['mesaj_musteri'] = "<div class='alert alert-danger'>Müşteri silinirken bir hata oluştu (ID: {$musteri_id}): " . $e->getMessage() . "</div>";
        }
    }
} else {
    // ID yoksa veya geçerli değilse
    $_SESSION['mesaj_musteri'] = "<div class='alert alert-danger'>Geçersiz müşteri ID'si. Silme işlemi yapılamadı.</div>";
}

// Her durumda müşteri listesine geri dön
header("Location: musteriler.php");
exit; // Yönlendirmeden sonra scriptin çalışmasını durdurmak önemlidir.
?>