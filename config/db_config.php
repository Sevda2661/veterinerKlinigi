<?php
// Veritabanı Bağlantı Bilgileri
define('DB_SERVER', 'localhost');     // MySQL sunucunuzun adresi (XAMPP için genellikle 'localhost')
define('DB_USERNAME', 'root');        // MySQL kullanıcı adınız (XAMPP için varsayılan 'root')
define('DB_PASSWORD', 'sevda1234');            // MySQL şifreniz (XAMPP için varsayılan boştur)
define('DB_NAME', 'VeterinerKlinigi'); // Oluşturduğun veritabanının adı

// Veritabanı Bağlantısını Oluşturma (PDO Kullanarak)
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    // PDO hata modunu istisna (exception) olarak ayarla. Bu, hataları yakalamamızı kolaylaştırır.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Varsayılan veri çekme modunu ilişkilendirilebilir dizi olarak ayarla (isteğe bağlı, $row['sutun_adi'] gibi kullanmak için)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // echo "Veritabanına başarıyla bağlanıldı."; // Test için, sonra silebilirsin
} catch(PDOException $e){
    // Bağlantı hatası durumunda çalışacak kod
    // die() fonksiyonu scriptin çalışmasını durdurur ve mesajı gösterir.
    // Gerçek bir uygulamada bu hata mesajı kullanıcıya doğrudan gösterilmemeli, bir log dosyasına yazılmalıdır.
    die("HATA: Veritabanına bağlanılamadı. " . $e->getMessage());
}
?>