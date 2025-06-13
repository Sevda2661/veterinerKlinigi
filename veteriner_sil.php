<?php
// Veritabanı bağlantısı için db_config.php'yi dahil et
require_once __DIR__ . '/config/db_config.php';

// Session başlatmak (mesajları session ile taşımak için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Silinecek Veterinerin ID'sini Almak (GET isteği ile)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $veteriner_id = intval($_GET['id']);

    // ÖNEMLİ: ON DELETE RESTRICT nedeniyle, önce bağlı kayıt var mı diye kontrol etmeye gerek yok,
    // veritabanı zaten hata verecektir. Biz bu hatayı yakalayacağız.
    // İsteğe bağlı olarak, daha kullanıcı dostu bir mesaj için ön kontrol yapılabilir.

    try {
        // Saklı yordamımızı kullanarak veteriner sil
        // sp_Veteriner_Sil(IN p_VeterinerID INT)
        $sql_delete = "CALL sp_Veteriner_Sil(:veteriner_id)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':veteriner_id', $veteriner_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $silinen_satir_sayisi = $stmt_delete->fetchColumn(); // Saklı yordamdan dönen ROW_COUNT() değeri
        $stmt_delete->closeCursor();

        if ($silinen_satir_sayisi > 0) {
            $_SESSION['mesaj_veteriner'] = "<div class='alert alert-success'>Veteriner (ID: {$veteriner_id}) başarıyla silindi.</div>";
        } else {
            $_SESSION['mesaj_veteriner'] = "<div class='alert alert-warning'>Veteriner silinemedi veya zaten silinmiş (ID: {$veteriner_id}).</div>";
        }

    } catch (PDOException $e) {
        // Yabancı anahtar kısıtlaması (foreign key constraint) hatasını yakalama
        // MySQL'de bu genellikle 1451 hata kodudur.
        if ($e->errorInfo[1] == 1451) {
            $_SESSION['mesaj_veteriner'] = "<div class='alert alert-danger'>HATA: Bu veteriner (ID: {$veteriner_id}) silinemez çünkü bu veterinere bağlı kayıtlar (örneğin randevular, tedaviler) bulunmaktadır. Lütfen önce bu kayıtları silin veya başka bir veterinere bağlayın.</div>";
        } else {
            $_SESSION['mesaj_veteriner'] = "<div class='alert alert-danger'>Veteriner silinirken bir hata oluştu (ID: {$veteriner_id}): " . $e->getMessage() . "</div>";
        }
    }
} else {
    // ID yoksa veya geçerli değilse
    $_SESSION['mesaj_veteriner'] = "<div class='alert alert-danger'>Geçersiz veteriner ID'si. Silme işlemi yapılamadı.</div>";
}

// Her durumda veteriner listesine geri dön
header("Location: veterinerler.php");
exit; // Yönlendirmeden sonra scriptin çalışmasını durdurmak önemlidir.
?>