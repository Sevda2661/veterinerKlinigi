-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 13 Haz 2025, 16:33:09
-- Sunucu sürümü: 8.0.42
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `veterinerklinigi`
--

DELIMITER $$
--
-- Yordamlar
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_FaturaDetay_Ekle` (IN `p_FaturaID` INT, IN `p_TedaviID` INT, IN `p_IlacID` INT, IN `p_Miktar` INT, IN `p_BirimFiyat` DECIMAL(10,2), IN `p_Aciklama` VARCHAR(255))   BEGIN
    INSERT INTO FaturaDetaylari (FaturaID, TedaviID, IlacID, Miktar, BirimFiyat, Aciklama)
    VALUES (p_FaturaID, p_TedaviID, p_IlacID, p_Miktar, p_BirimFiyat, p_Aciklama);
    SELECT LAST_INSERT_ID() AS YeniFaturaDetayID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_FaturaDetay_GetirByID` (IN `p_FaturaDetayID` INT)   BEGIN
    SELECT FD.*, T.Tani, I.IlacAdi
    FROM FaturaDetaylari FD
    LEFT JOIN Tedaviler T ON FD.TedaviID = T.TedaviID
    LEFT JOIN Ilaclar I ON FD.IlacID = I.IlacID
    WHERE FD.FaturaDetayID = p_FaturaDetayID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_FaturaDetay_Guncelle` (IN `p_FaturaDetayID` INT, IN `p_FaturaID` INT, IN `p_TedaviID` INT, IN `p_IlacID` INT, IN `p_Miktar` INT, IN `p_BirimFiyat` DECIMAL(10,2), IN `p_Aciklama` VARCHAR(255))   BEGIN
    UPDATE FaturaDetaylari
    SET FaturaID = p_FaturaID,
        TedaviID = p_TedaviID,
        IlacID = p_IlacID,
        Miktar = p_Miktar,
        BirimFiyat = p_BirimFiyat,
        Aciklama = p_Aciklama
    WHERE FaturaDetayID = p_FaturaDetayID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_FaturaDetay_Sil` (IN `p_FaturaDetayID` INT)   BEGIN
    DELETE FROM FaturaDetaylari WHERE FaturaDetayID = p_FaturaDetayID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Faturalar_Ara` (IN `p_AramaTerimi` VARCHAR(255))   BEGIN
    SET p_AramaTerimi = CONCAT('%', p_AramaTerimi, '%');

    SELECT F.FaturaID, F.MusteriID, CONCAT(M.Adi, ' ', M.Soyadi) AS Musteri, H.Adi AS HayvanAdi, F.FaturaTarihi, F.ToplamTutar, F.OdemeDurumu
    FROM Faturalar F
    JOIN Musteriler M ON F.MusteriID = M.MusteriID
    LEFT JOIN Hayvanlar H ON F.HayvanID = H.HayvanID -- Hayvan NULL olabilir
    WHERE CAST(F.FaturaID AS CHAR) LIKE p_AramaTerimi -- Fatura ID'sine göre arama (string'e çevirerek)
       OR CONCAT(M.Adi, ' ', M.Soyadi) LIKE p_AramaTerimi
       OR M.Adi LIKE p_AramaTerimi
       OR M.Soyadi LIKE p_AramaTerimi
       OR H.Adi LIKE p_AramaTerimi -- Hayvan adına göre (eğer varsa)
       OR F.OdemeDurumu LIKE p_AramaTerimi -- Ödeme durumuna göre
    ORDER BY F.FaturaTarihi DESC, F.FaturaID DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Faturalar_Listele` ()   BEGIN
    SELECT F.FaturaID, CONCAT(M.Adi, ' ', M.Soyadi) AS Musteri, H.Adi AS HayvanAdi, F.FaturaTarihi, F.ToplamTutar, F.OdemeDurumu
    FROM Faturalar F
    JOIN Musteriler M ON F.MusteriID = M.MusteriID
    LEFT JOIN Hayvanlar H ON F.HayvanID = H.HayvanID
    ORDER BY F.FaturaTarihi DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_FaturaninDetaylari_Listele` (IN `p_FaturaID` INT)   BEGIN
    SELECT FD.FaturaDetayID, T.Tani, I.IlacAdi, FD.Miktar, FD.BirimFiyat, FD.Aciklama, (FD.Miktar * FD.BirimFiyat) AS AraToplam
    FROM FaturaDetaylari FD
    LEFT JOIN Tedaviler T ON FD.TedaviID = T.TedaviID
    LEFT JOIN Ilaclar I ON FD.IlacID = I.IlacID
    WHERE FD.FaturaID = p_FaturaID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Fatura_Ekle` (IN `p_MusteriID` INT, IN `p_HayvanID` INT, IN `p_FaturaTarihi` DATE, IN `p_OdemeDurumu` VARCHAR(20))   BEGIN
    INSERT INTO Faturalar (MusteriID, HayvanID, FaturaTarihi, ToplamTutar, OdemeDurumu)
    VALUES (p_MusteriID, p_HayvanID, p_FaturaTarihi, 0.00, p_OdemeDurumu);
    SELECT LAST_INSERT_ID() AS YeniFaturaID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Fatura_GetirByID` (IN `p_FaturaID` INT)   BEGIN
    SELECT F.*, M.Adi AS MusteriAdi, M.Soyadi AS MusteriSoyadi, H.Adi AS HayvanAdi
    FROM Faturalar F
    JOIN Musteriler M ON F.MusteriID = M.MusteriID
    LEFT JOIN Hayvanlar H ON F.HayvanID = H.HayvanID
    WHERE F.FaturaID = p_FaturaID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Fatura_Guncelle` (IN `p_FaturaID` INT, IN `p_MusteriID` INT, IN `p_HayvanID` INT, IN `p_FaturaTarihi` DATE, IN `p_OdemeDurumu` VARCHAR(20))   BEGIN
    UPDATE Faturalar
    SET MusteriID = p_MusteriID,
        HayvanID = p_HayvanID,
        FaturaTarihi = p_FaturaTarihi,
        OdemeDurumu = p_OdemeDurumu
    WHERE FaturaID = p_FaturaID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Fatura_Sil` (IN `p_FaturaID` INT)   BEGIN
    DELETE FROM Faturalar WHERE FaturaID = p_FaturaID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_HayvaninRandevulari_Listele` (IN `p_HayvanID` INT)   BEGIN
    SELECT R.RandevuID, CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi, R.RandevuTarihi, R.RandevuSaati, R.RandevuNedeni
    FROM Randevular R
    JOIN Veterinerler V ON R.VeterinerID = V.VeterinerID
    WHERE R.HayvanID = p_HayvanID
    ORDER BY R.RandevuTarihi DESC, R.RandevuSaati DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_HayvaninTedavileri_Listele` (IN `p_HayvanID` INT)   BEGIN
    SELECT 
        T.TedaviID, 
        T.HayvanID,         -- HayvanID
        H.Adi AS HayvanAdi, -- Hayvan Adı
        T.VeterinerID,      -- VeterinerID
        CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi, 
        T.Tani, 
        T.TedaviTarihi
    FROM Tedaviler T
    JOIN Hayvanlar H ON T.HayvanID = H.HayvanID
    JOIN Veterinerler V ON T.VeterinerID = V.VeterinerID
    WHERE T.HayvanID = p_HayvanID
    ORDER BY T.TedaviTarihi DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Hayvanlar_Ara` (IN `p_AramaTerimi` VARCHAR(255))   BEGIN
    SET p_AramaTerimi = CONCAT('%', p_AramaTerimi, '%');

    SELECT H.*, M.Adi AS MusteriAdi, M.Soyadi AS MusteriSoyadi, fn_HayvanYasiHesapla(H.DogumTarihi) AS Yas
    FROM Hayvanlar H
    JOIN Musteriler M ON H.MusteriID = M.MusteriID
    WHERE H.Adi LIKE p_AramaTerimi
       OR H.Turu LIKE p_AramaTerimi
       OR H.Cinsi LIKE p_AramaTerimi
       OR CONCAT(M.Adi, ' ', M.Soyadi) LIKE p_AramaTerimi -- Sahip adına göre arama
    ORDER BY H.KayitTarihi DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Hayvanlar_Listele` ()   BEGIN
    SELECT H.*, M.Adi AS MusteriAdi, M.Soyadi AS MusteriSoyadi
    FROM Hayvanlar H
    JOIN Musteriler M ON H.MusteriID = M.MusteriID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Hayvan_Ekle` (IN `p_MusteriID` INT, IN `p_Adi` VARCHAR(100), IN `p_Turu` VARCHAR(50), IN `p_Cinsi` VARCHAR(50), IN `p_DogumTarihi` DATE, IN `p_Cinsiyet` VARCHAR(10))   BEGIN
    INSERT INTO Hayvanlar (MusteriID, Adi, Turu, Cinsi, DogumTarihi, Cinsiyet)
    VALUES (p_MusteriID, p_Adi, p_Turu, p_Cinsi, p_DogumTarihi, p_Cinsiyet);
    SELECT LAST_INSERT_ID() AS YeniHayvanID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Hayvan_GetirByID` (IN `p_HayvanID` INT)   BEGIN
    SELECT H.*, M.Adi AS MusteriAdi, M.Soyadi AS MusteriSoyadi
    FROM Hayvanlar H
    JOIN Musteriler M ON H.MusteriID = M.MusteriID
    WHERE H.HayvanID = p_HayvanID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Hayvan_Guncelle` (IN `p_HayvanID` INT, IN `p_MusteriID` INT, IN `p_Adi` VARCHAR(100), IN `p_Turu` VARCHAR(50), IN `p_Cinsi` VARCHAR(50), IN `p_DogumTarihi` DATE, IN `p_Cinsiyet` VARCHAR(10))   BEGIN
    UPDATE Hayvanlar
    SET MusteriID = p_MusteriID,
        Adi = p_Adi,
        Turu = p_Turu,
        Cinsi = p_Cinsi,
        DogumTarihi = p_DogumTarihi,
        Cinsiyet = p_Cinsiyet
    WHERE HayvanID = p_HayvanID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Hayvan_Sil` (IN `p_HayvanID` INT)   BEGIN
    DELETE FROM Hayvanlar WHERE HayvanID = p_HayvanID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Ilaclar_Ara` (IN `p_AramaTerimi` VARCHAR(255))   BEGIN
    SET p_AramaTerimi = CONCAT('%', p_AramaTerimi, '%');

    SELECT * 
    FROM Ilaclar
    WHERE IlacAdi LIKE p_AramaTerimi
    ORDER BY IlacAdi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Ilaclar_Listele` ()   BEGIN
    SELECT * FROM Ilaclar;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Ilac_Ekle` (IN `p_IlacAdi` VARCHAR(255), IN `p_StokMiktari` INT, IN `p_BirimSatisFiyati` DECIMAL(10,2))   BEGIN
    INSERT INTO Ilaclar (IlacAdi, StokMiktari, BirimSatisFiyati)
    VALUES (p_IlacAdi, p_StokMiktari, p_BirimSatisFiyati);
    SELECT LAST_INSERT_ID() AS YeniIlacID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Ilac_GetirByID` (IN `p_IlacID` INT)   BEGIN
    SELECT * FROM Ilaclar WHERE IlacID = p_IlacID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Ilac_Guncelle` (IN `p_IlacID` INT, IN `p_IlacAdi` VARCHAR(255), IN `p_StokMiktari` INT, IN `p_BirimSatisFiyati` DECIMAL(10,2))   BEGIN
    UPDATE Ilaclar
    SET IlacAdi = p_IlacAdi,
        StokMiktari = p_StokMiktari,
        BirimSatisFiyati = p_BirimSatisFiyati -- YENİ ALAN
    WHERE IlacID = p_IlacID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Ilac_Sil` (IN `p_IlacID` INT)   BEGIN
    DELETE FROM Ilaclar WHERE IlacID = p_IlacID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Musteriler_Ara` (IN `p_AramaTerimi` VARCHAR(255))   BEGIN
    SET p_AramaTerimi = CONCAT('%', p_AramaTerimi, '%'); -- Arama teriminin başına ve sonuna % ekle

    SELECT * 
    FROM Musteriler
    WHERE Adi LIKE p_AramaTerimi
       OR Soyadi LIKE p_AramaTerimi
       OR TelefonNumarasi LIKE p_AramaTerimi
       OR Adres LIKE p_AramaTerimi -- Adreste de arama yapsın
    ORDER BY Adi, Soyadi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Musteriler_Listele` ()   BEGIN
    SELECT * FROM Musteriler;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_MusterininFaturalari_Listele` (IN `p_MusteriID` INT)   BEGIN
    SELECT F.FaturaID, H.Adi AS HayvanAdi, F.FaturaTarihi, F.ToplamTutar, F.OdemeDurumu
    FROM Faturalar F
    LEFT JOIN Hayvanlar H ON F.HayvanID = H.HayvanID
    WHERE F.MusteriID = p_MusteriID
    ORDER BY F.FaturaTarihi DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_MusterininHayvanlari_Listele` (IN `p_MusteriID` INT)   BEGIN
    SELECT * FROM Hayvanlar WHERE MusteriID = p_MusteriID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Musteri_Ekle` (IN `p_Adi` VARCHAR(100), IN `p_Soyadi` VARCHAR(100), IN `p_TelefonNumarasi` VARCHAR(20), IN `p_Adres` TEXT)   BEGIN
    INSERT INTO Musteriler (Adi, Soyadi, TelefonNumarasi, Adres)
    VALUES (p_Adi, p_Soyadi, p_TelefonNumarasi, p_Adres);
    SELECT LAST_INSERT_ID() AS YeniMusteriID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Musteri_GetirByID` (IN `p_MusteriID` INT)   BEGIN
    SELECT * FROM Musteriler WHERE MusteriID = p_MusteriID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Musteri_Guncelle` (IN `p_MusteriID` INT, IN `p_Adi` VARCHAR(100), IN `p_Soyadi` VARCHAR(100), IN `p_TelefonNumarasi` VARCHAR(20), IN `p_Adres` TEXT)   BEGIN
    UPDATE Musteriler
    SET Adi = p_Adi,
        Soyadi = p_Soyadi,
        TelefonNumarasi = p_TelefonNumarasi,
        Adres = p_Adres
    WHERE MusteriID = p_MusteriID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Musteri_Sil` (IN `p_MusteriID` INT)   BEGIN
    DELETE FROM Musteriler WHERE MusteriID = p_MusteriID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Randevular_Ara` (IN `p_AramaTerimi` VARCHAR(255))   BEGIN
    SET p_AramaTerimi = CONCAT('%', p_AramaTerimi, '%');

    SELECT R.RandevuID, H.Adi AS HayvanAdi, H.HayvanID, V.VeterinerID, CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi, R.RandevuTarihi, R.RandevuSaati, R.RandevuNedeni
    FROM Randevular R
    JOIN Hayvanlar H ON R.HayvanID = H.HayvanID
    JOIN Veterinerler V ON R.VeterinerID = V.VeterinerID
    WHERE H.Adi LIKE p_AramaTerimi
       OR CONCAT(V.Adi, ' ', V.Soyadi) LIKE p_AramaTerimi
       OR V.Adi LIKE p_AramaTerimi
       OR V.Soyadi LIKE p_AramaTerimi
       OR R.RandevuNedeni LIKE p_AramaTerimi
       -- OR DATE_FORMAT(R.RandevuTarihi, '%d.%m.%Y') LIKE p_AramaTerimi -- Tarihe göre arama için (format önemli)
    ORDER BY R.RandevuTarihi DESC, R.RandevuSaati DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Randevular_Listele` ()   BEGIN
    SELECT R.RandevuID, H.Adi AS HayvanAdi, CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi, R.RandevuTarihi, R.RandevuSaati, R.RandevuNedeni
    FROM Randevular R
    JOIN Hayvanlar H ON R.HayvanID = H.HayvanID
    JOIN Veterinerler V ON R.VeterinerID = V.VeterinerID
    ORDER BY R.RandevuTarihi, R.RandevuSaati;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Randevu_Ekle` (IN `p_HayvanID` INT, IN `p_VeterinerID` INT, IN `p_RandevuTarihi` DATE, IN `p_RandevuSaati` TIME, IN `p_RandevuNedeni` TEXT)   BEGIN
    INSERT INTO Randevular (HayvanID, VeterinerID, RandevuTarihi, RandevuSaati, RandevuNedeni)
    VALUES (p_HayvanID, p_VeterinerID, p_RandevuTarihi, p_RandevuSaati, p_RandevuNedeni);
    SELECT LAST_INSERT_ID() AS YeniRandevuID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Randevu_GetirByID` (IN `p_RandevuID` INT)   BEGIN
    SELECT R.*, H.Adi AS HayvanAdi, V.Adi AS VeterinerAdi, V.Soyadi AS VeterinerSoyadi
    FROM Randevular R
    JOIN Hayvanlar H ON R.HayvanID = H.HayvanID
    JOIN Veterinerler V ON R.VeterinerID = V.VeterinerID
    WHERE R.RandevuID = p_RandevuID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Randevu_Guncelle` (IN `p_RandevuID` INT, IN `p_RandevuTarihi` DATE, IN `p_RandevuSaati` TIME, IN `p_RandevuNedeni` TEXT)   BEGIN
    UPDATE Randevular
    SET RandevuTarihi = p_RandevuTarihi,
        RandevuSaati = p_RandevuSaati,
        RandevuNedeni = p_RandevuNedeni
    WHERE RandevuID = p_RandevuID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Randevu_Sil` (IN `p_RandevuID` INT)   BEGIN
    DELETE FROM Randevular WHERE RandevuID = p_RandevuID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Tedaviler_Ara` (IN `p_AramaTerimi` VARCHAR(255))   BEGIN
    SET p_AramaTerimi = CONCAT('%', p_AramaTerimi, '%');

    SELECT T.TedaviID, T.HayvanID, H.Adi AS HayvanAdi, T.VeterinerID, CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi, T.Tani, T.TedaviTarihi
    FROM Tedaviler T
    JOIN Hayvanlar H ON T.HayvanID = H.HayvanID
    JOIN Veterinerler V ON T.VeterinerID = V.VeterinerID
    WHERE H.Adi LIKE p_AramaTerimi
       OR CONCAT(V.Adi, ' ', V.Soyadi) LIKE p_AramaTerimi
       OR V.Adi LIKE p_AramaTerimi
       OR V.Soyadi LIKE p_AramaTerimi
       OR T.Tani LIKE p_AramaTerimi
       -- OR DATE_FORMAT(T.TedaviTarihi, '%d.%m.%Y') LIKE p_AramaTerimi -- Tarihe göre arama için
    ORDER BY T.TedaviTarihi DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Tedaviler_Listele` ()   BEGIN
    SELECT T.TedaviID, H.Adi AS HayvanAdi, CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi, T.Tani, T.TedaviTarihi
    FROM Tedaviler T
    JOIN Hayvanlar H ON T.HayvanID = H.HayvanID
    JOIN Veterinerler V ON T.VeterinerID = V.VeterinerID
    ORDER BY T.TedaviTarihi DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Tedavi_Ekle` (IN `p_HayvanID` INT, IN `p_VeterinerID` INT, IN `p_Tani` TEXT, IN `p_TedaviTarihi` DATE)   BEGIN
    INSERT INTO Tedaviler (HayvanID, VeterinerID, Tani, TedaviTarihi)
    VALUES (p_HayvanID, p_VeterinerID, p_Tani, p_TedaviTarihi);
    SELECT LAST_INSERT_ID() AS YeniTedaviID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Tedavi_GetirByID` (IN `p_TedaviID` INT)   BEGIN
    SELECT T.*, H.Adi AS HayvanAdi, CONCAT(V.Adi, ' ', V.Soyadi) AS VeterinerAdiSoyadi
    FROM Tedaviler T
    JOIN Hayvanlar H ON T.HayvanID = H.HayvanID
    JOIN Veterinerler V ON T.VeterinerID = V.VeterinerID
    WHERE T.TedaviID = p_TedaviID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Tedavi_Guncelle` (IN `p_TedaviID` INT, IN `p_HayvanID` INT, IN `p_VeterinerID` INT, IN `p_Tani` TEXT, IN `p_TedaviTarihi` DATE)   BEGIN
    UPDATE Tedaviler
    SET HayvanID = p_HayvanID,
        VeterinerID = p_VeterinerID,
        Tani = p_Tani,
        TedaviTarihi = p_TedaviTarihi
    WHERE TedaviID = p_TedaviID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Tedavi_Sil` (IN `p_TedaviID` INT)   BEGIN
    DELETE FROM Tedaviler WHERE TedaviID = p_TedaviID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_VeterinerinRandevulari_Listele` (IN `p_VeterinerID` INT)   BEGIN
    SELECT R.RandevuID, H.Adi AS HayvanAdi, R.RandevuTarihi, R.RandevuSaati, R.RandevuNedeni
    FROM Randevular R
    JOIN Hayvanlar H ON R.HayvanID = H.HayvanID
    WHERE R.VeterinerID = p_VeterinerID
    ORDER BY R.RandevuTarihi DESC, R.RandevuSaati DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Veterinerler_Ara` (IN `p_AramaTerimi` VARCHAR(255))   BEGIN
    SET p_AramaTerimi = CONCAT('%', p_AramaTerimi, '%');

    SELECT * 
    FROM Veterinerler
    WHERE Adi LIKE p_AramaTerimi
       OR Soyadi LIKE p_AramaTerimi
       OR TelefonNumarasi LIKE p_AramaTerimi
    ORDER BY Adi, Soyadi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Veterinerler_Listele` ()   BEGIN
    SELECT * FROM Veterinerler;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Veteriner_Ekle` (IN `p_Adi` VARCHAR(100), IN `p_Soyadi` VARCHAR(100), IN `p_TelefonNumarasi` VARCHAR(20))   BEGIN
    INSERT INTO Veterinerler (Adi, Soyadi, TelefonNumarasi)
    VALUES (p_Adi, p_Soyadi, p_TelefonNumarasi);
    SELECT LAST_INSERT_ID() AS YeniVeterinerID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Veteriner_GetirByID` (IN `p_VeterinerID` INT)   BEGIN
    SELECT * FROM Veterinerler WHERE VeterinerID = p_VeterinerID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Veteriner_Guncelle` (IN `p_VeterinerID` INT, IN `p_Adi` VARCHAR(100), IN `p_Soyadi` VARCHAR(100), IN `p_TelefonNumarasi` VARCHAR(20))   BEGIN
    UPDATE Veterinerler
    SET Adi = p_Adi,
        Soyadi = p_Soyadi,
        TelefonNumarasi = p_TelefonNumarasi
    WHERE VeterinerID = p_VeterinerID;
    SELECT ROW_COUNT() AS GuncellenenSatirSayisi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Veteriner_Sil` (IN `p_VeterinerID` INT)   BEGIN
    DELETE FROM Veterinerler WHERE VeterinerID = p_VeterinerID;
    SELECT ROW_COUNT() AS SilinenSatirSayisi;
END$$

--
-- İşlevler
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_HayvanYasiHesapla` (`p_DogumTarihi` DATE) RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
    DECLARE v_YasYil INT;
    DECLARE v_YasAy INT;
    DECLARE v_Sonuc VARCHAR(50);

    IF p_DogumTarihi IS NULL OR p_DogumTarihi > CURDATE() THEN
        RETURN 'Bilinmiyor';
    END IF;

    SET v_YasYil = TIMESTAMPDIFF(YEAR, p_DogumTarihi, CURDATE());
    SET v_YasAy = TIMESTAMPDIFF(MONTH, p_DogumTarihi, CURDATE()) % 12;
    
    IF DAY(CURDATE()) < DAY(p_DogumTarihi) THEN
        SET v_YasAy = v_YasAy - 1;
        IF v_YasAy < 0 THEN
            SET v_YasAy = 11;
            SET v_YasYil = v_YasYil -1;
        END IF;
    END IF;
    
    IF v_YasYil < 0 THEN SET v_YasYil = 0; END IF; -- Negatif yıl olmasın

    SET v_Sonuc = CONCAT(v_YasYil, ' yıl, ', v_YasAy, ' ay');
    RETURN v_Sonuc;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `faturadetaylari`
--

CREATE TABLE `faturadetaylari` (
  `FaturaDetayID` int NOT NULL,
  `FaturaID` int NOT NULL,
  `TedaviID` int DEFAULT NULL,
  `IlacID` int DEFAULT NULL,
  `Miktar` int NOT NULL DEFAULT '1',
  `BirimFiyat` decimal(10,2) NOT NULL,
  `Aciklama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `faturadetaylari`
--

INSERT INTO `faturadetaylari` (`FaturaDetayID`, `FaturaID`, `TedaviID`, `IlacID`, `Miktar`, `BirimFiyat`, `Aciklama`) VALUES
(7, 6, NULL, 4, 3, 20.00, 'İlaç: ruminant'),
(8, 8, NULL, 4, 2, 20.00, 'İlaç: ruminant');

--
-- Tetikleyiciler `faturadetaylari`
--
DELIMITER $$
CREATE TRIGGER `trg_FaturaDetay_AD_StokIade` AFTER DELETE ON `faturadetaylari` FOR EACH ROW BEGIN
    IF OLD.IlacID IS NOT NULL THEN
        UPDATE Ilaclar
        SET StokMiktari = StokMiktari + OLD.Miktar
        WHERE IlacID = OLD.IlacID;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_FaturaDetay_AD_ToplamGuncelle` AFTER DELETE ON `faturadetaylari` FOR EACH ROW BEGIN
    UPDATE Faturalar
    SET ToplamTutar = (SELECT COALESCE(SUM(Miktar * BirimFiyat), 0) FROM FaturaDetaylari WHERE FaturaID = OLD.FaturaID)
    WHERE FaturaID = OLD.FaturaID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_FaturaDetay_AI_StokAzalt` AFTER INSERT ON `faturadetaylari` FOR EACH ROW BEGIN
    IF NEW.IlacID IS NOT NULL THEN
        UPDATE Ilaclar
        SET StokMiktari = StokMiktari - NEW.Miktar
        WHERE IlacID = NEW.IlacID;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_FaturaDetay_AI_ToplamGuncelle` AFTER INSERT ON `faturadetaylari` FOR EACH ROW BEGIN
    UPDATE Faturalar
    SET ToplamTutar = (SELECT SUM(Miktar * BirimFiyat) FROM FaturaDetaylari WHERE FaturaID = NEW.FaturaID)
    WHERE FaturaID = NEW.FaturaID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_FaturaDetay_AU_StokGuncelle` AFTER UPDATE ON `faturadetaylari` FOR EACH ROW BEGIN
    -- Eğer eski kayıtta ilaç varsa ve miktar/ilaç değiştiyse, eski miktarı iade et
    IF OLD.IlacID IS NOT NULL THEN
        UPDATE Ilaclar
        SET StokMiktari = StokMiktari + OLD.Miktar
        WHERE IlacID = OLD.IlacID;
    END IF;

    -- Eğer yeni kayıtta ilaç varsa, yeni miktarı stoktan düş
    IF NEW.IlacID IS NOT NULL THEN
        UPDATE Ilaclar
        SET StokMiktari = StokMiktari - NEW.Miktar
        WHERE IlacID = NEW.IlacID;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_FaturaDetay_AU_ToplamGuncelle` AFTER UPDATE ON `faturadetaylari` FOR EACH ROW BEGIN
    IF OLD.FaturaID = NEW.FaturaID THEN
        -- Sadece mevcut faturanın toplamını güncelle
        UPDATE Faturalar
        SET ToplamTutar = (SELECT SUM(Miktar * BirimFiyat) FROM FaturaDetaylari WHERE FaturaID = NEW.FaturaID)
        WHERE FaturaID = NEW.FaturaID;
    ELSE
        -- Eski faturanın toplamını güncelle
        UPDATE Faturalar
        SET ToplamTutar = (SELECT COALESCE(SUM(Miktar * BirimFiyat), 0) FROM FaturaDetaylari WHERE FaturaID = OLD.FaturaID)
        WHERE FaturaID = OLD.FaturaID;
        -- Yeni faturanın toplamını güncelle
        UPDATE Faturalar
        SET ToplamTutar = (SELECT SUM(Miktar * BirimFiyat) FROM FaturaDetaylari WHERE FaturaID = NEW.FaturaID)
        WHERE FaturaID = NEW.FaturaID;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `faturalar`
--

CREATE TABLE `faturalar` (
  `FaturaID` int NOT NULL,
  `MusteriID` int NOT NULL,
  `HayvanID` int DEFAULT NULL,
  `FaturaTarihi` date NOT NULL,
  `ToplamTutar` decimal(10,2) DEFAULT '0.00',
  `OdemeDurumu` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Ödenmedi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `faturalar`
--

INSERT INTO `faturalar` (`FaturaID`, `MusteriID`, `HayvanID`, `FaturaTarihi`, `ToplamTutar`, `OdemeDurumu`) VALUES
(6, 5, 4, '2025-06-13', 60.00, 'Ödenmedi'),
(7, 6, 5, '2025-06-13', 0.00, 'Ödenmedi'),
(8, 6, 5, '2025-06-13', 40.00, 'Ödenmedi');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `hayvanlar`
--

CREATE TABLE `hayvanlar` (
  `HayvanID` int NOT NULL,
  `MusteriID` int NOT NULL,
  `Adi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Turu` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Cinsi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DogumTarihi` date DEFAULT NULL,
  `Cinsiyet` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `KayitTarihi` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `hayvanlar`
--

INSERT INTO `hayvanlar` (`HayvanID`, `MusteriID`, `Adi`, `Turu`, `Cinsi`, `DogumTarihi`, `Cinsiyet`, `KayitTarihi`) VALUES
(3, 3, 'SSS', 'KEDİ', 'TEKİR', '2444-05-12', 'Bilinmiyor', '2025-05-27 16:06:12'),
(4, 5, 'sarıman', 'kedi', 'tekir', '2025-05-21', 'Erkek', '2025-06-13 15:38:27'),
(5, 6, 'balcak', 'kedi', 'tekir', '2021-04-15', 'Erkek', '2025-06-13 15:38:44'),
(6, 7, 'yvoie', 'köpek', 'pitbul', '2021-08-12', 'Dişi', '2025-06-13 15:39:14'),
(7, 8, 'ponçik', 'KEDİ', 'tekir', '2023-04-12', 'Dişi', '2025-06-13 15:39:35'),
(8, 9, 'imanlı', 'kedi', 'tekir', '2024-08-13', 'Erkek', '2025-06-13 15:40:00'),
(9, 10, 'minnoş', 'kedi', 'tekir', '2021-05-13', 'Dişi', '2025-06-13 15:40:16'),
(10, 11, 'zuzu', 'kedi', 'tekir', '2005-08-14', 'Dişi', '2025-06-13 15:40:41'),
(11, 12, 'panter', 'kedi', 'tekir', '2009-08-16', 'Dişi', '2025-06-13 15:41:07');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ilaclar`
--

CREATE TABLE `ilaclar` (
  `IlacID` int NOT NULL,
  `IlacAdi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `StokMiktari` int UNSIGNED DEFAULT '0',
  `BirimSatisFiyati` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'İlacın birim satış fiyatı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `ilaclar`
--

INSERT INTO `ilaclar` (`IlacID`, `IlacAdi`, `StokMiktari`, `BirimSatisFiyati`) VALUES
(4, 'ruminant', 10, 20.00),
(5, 'neflor', 30, 25.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `musteriler`
--

CREATE TABLE `musteriler` (
  `MusteriID` int NOT NULL,
  `Adi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Soyadi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TelefonNumarasi` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Adres` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `musteriler`
--

INSERT INTO `musteriler` (`MusteriID`, `Adi`, `Soyadi`, `TelefonNumarasi`, `Adres`) VALUES
(3, 'AHMET', 'AGIL', '05955846378', 'ESKİŞEHİR'),
(5, 'sevda', 'yalçın', '05355963613', 'eskişehir'),
(6, 'alperen', 'aggümüş', '05986975431', 'eskişehir'),
(7, 'yusuf', 'meydan', '05478962154', 'eskişehir'),
(8, 'erdem', 'derici', '05478216548', 'eskişehir'),
(9, 'melike', 'imalı', '05214896547', 'eskişehir'),
(10, 'elif', 'taşgın', '05365487946', 'eskişehir'),
(11, 'gizem', 'çevik', '05897548654', 'eskişehir'),
(12, 'ayşenur', 'aydın', '05489631546', 'eskişehir'),
(13, 'hilmi', 'yalçın', '05416548512', 'eskişehir');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `müsteriler`
--

CREATE TABLE `müsteriler` (
  `MusteriID` int NOT NULL,
  `Adi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Soyadi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TelefonNumarasi` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Adres` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `randevular`
--

CREATE TABLE `randevular` (
  `RandevuID` int NOT NULL,
  `HayvanID` int NOT NULL,
  `VeterinerID` int NOT NULL,
  `RandevuTarihi` date NOT NULL,
  `RandevuSaati` time NOT NULL,
  `RandevuNedeni` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `randevular`
--

INSERT INTO `randevular` (`RandevuID`, `HayvanID`, `VeterinerID`, `RandevuTarihi`, `RandevuSaati`, `RandevuNedeni`) VALUES
(6, 4, 3, '2025-06-13', '10:00:00', 'bakım'),
(7, 11, 3, '2025-06-13', '10:25:00', 'bakım'),
(8, 9, 2, '2025-06-13', '11:00:00', 'bakım'),
(9, 5, 2, '2025-08-12', '10:00:00', 'kontrol');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `tedaviler`
--

CREATE TABLE `tedaviler` (
  `TedaviID` int NOT NULL,
  `HayvanID` int NOT NULL,
  `VeterinerID` int NOT NULL,
  `Tani` text COLLATE utf8mb4_unicode_ci,
  `TedaviTarihi` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `tedaviler`
--

INSERT INTO `tedaviler` (`TedaviID`, `HayvanID`, `VeterinerID`, `Tani`, `TedaviTarihi`) VALUES
(6, 3, 2, 'aşı', '2025-06-13'),
(7, 8, 3, 'aşı', '2025-06-13'),
(8, 10, 3, 'aşı', '2025-06-13');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `veterinerler`
--

CREATE TABLE `veterinerler` (
  `VeterinerID` int NOT NULL,
  `Adi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Soyadi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TelefonNumarasi` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `veterinerler`
--

INSERT INTO `veterinerler` (`VeterinerID`, `Adi`, `Soyadi`, `TelefonNumarasi`) VALUES
(2, 'melike', 'imalı', '05355963613'),
(3, 'zekiye', 'yalçın', '05396460368');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `faturadetaylari`
--
ALTER TABLE `faturadetaylari`
  ADD PRIMARY KEY (`FaturaDetayID`),
  ADD KEY `FaturaID` (`FaturaID`),
  ADD KEY `TedaviID` (`TedaviID`),
  ADD KEY `IlacID` (`IlacID`);

--
-- Tablo için indeksler `faturalar`
--
ALTER TABLE `faturalar`
  ADD PRIMARY KEY (`FaturaID`),
  ADD KEY `MusteriID` (`MusteriID`),
  ADD KEY `HayvanID` (`HayvanID`);

--
-- Tablo için indeksler `hayvanlar`
--
ALTER TABLE `hayvanlar`
  ADD PRIMARY KEY (`HayvanID`),
  ADD KEY `MusteriID` (`MusteriID`);

--
-- Tablo için indeksler `ilaclar`
--
ALTER TABLE `ilaclar`
  ADD PRIMARY KEY (`IlacID`),
  ADD UNIQUE KEY `IlacAdi` (`IlacAdi`);

--
-- Tablo için indeksler `musteriler`
--
ALTER TABLE `musteriler`
  ADD PRIMARY KEY (`MusteriID`),
  ADD UNIQUE KEY `TelefonNumarasi` (`TelefonNumarasi`);

--
-- Tablo için indeksler `müsteriler`
--
ALTER TABLE `müsteriler`
  ADD PRIMARY KEY (`MusteriID`);

--
-- Tablo için indeksler `randevular`
--
ALTER TABLE `randevular`
  ADD PRIMARY KEY (`RandevuID`),
  ADD UNIQUE KEY `UK_Randevu` (`HayvanID`,`VeterinerID`,`RandevuTarihi`,`RandevuSaati`),
  ADD KEY `VeterinerID` (`VeterinerID`);

--
-- Tablo için indeksler `tedaviler`
--
ALTER TABLE `tedaviler`
  ADD PRIMARY KEY (`TedaviID`),
  ADD KEY `HayvanID` (`HayvanID`),
  ADD KEY `VeterinerID` (`VeterinerID`);

--
-- Tablo için indeksler `veterinerler`
--
ALTER TABLE `veterinerler`
  ADD PRIMARY KEY (`VeterinerID`),
  ADD UNIQUE KEY `TelefonNumarasi` (`TelefonNumarasi`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `faturadetaylari`
--
ALTER TABLE `faturadetaylari`
  MODIFY `FaturaDetayID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `faturalar`
--
ALTER TABLE `faturalar`
  MODIFY `FaturaID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `hayvanlar`
--
ALTER TABLE `hayvanlar`
  MODIFY `HayvanID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `ilaclar`
--
ALTER TABLE `ilaclar`
  MODIFY `IlacID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `musteriler`
--
ALTER TABLE `musteriler`
  MODIFY `MusteriID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `müsteriler`
--
ALTER TABLE `müsteriler`
  MODIFY `MusteriID` int NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `randevular`
--
ALTER TABLE `randevular`
  MODIFY `RandevuID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `tedaviler`
--
ALTER TABLE `tedaviler`
  MODIFY `TedaviID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `veterinerler`
--
ALTER TABLE `veterinerler`
  MODIFY `VeterinerID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `faturadetaylari`
--
ALTER TABLE `faturadetaylari`
  ADD CONSTRAINT `faturadetaylari_ibfk_1` FOREIGN KEY (`FaturaID`) REFERENCES `faturalar` (`FaturaID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `faturadetaylari_ibfk_2` FOREIGN KEY (`TedaviID`) REFERENCES `tedaviler` (`TedaviID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `faturadetaylari_ibfk_3` FOREIGN KEY (`IlacID`) REFERENCES `ilaclar` (`IlacID`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `faturalar`
--
ALTER TABLE `faturalar`
  ADD CONSTRAINT `faturalar_ibfk_1` FOREIGN KEY (`MusteriID`) REFERENCES `musteriler` (`MusteriID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `faturalar_ibfk_2` FOREIGN KEY (`HayvanID`) REFERENCES `hayvanlar` (`HayvanID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `hayvanlar`
--
ALTER TABLE `hayvanlar`
  ADD CONSTRAINT `hayvanlar_ibfk_1` FOREIGN KEY (`MusteriID`) REFERENCES `musteriler` (`MusteriID`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `randevular`
--
ALTER TABLE `randevular`
  ADD CONSTRAINT `randevular_ibfk_1` FOREIGN KEY (`HayvanID`) REFERENCES `hayvanlar` (`HayvanID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `randevular_ibfk_2` FOREIGN KEY (`VeterinerID`) REFERENCES `veterinerler` (`VeterinerID`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `tedaviler`
--
ALTER TABLE `tedaviler`
  ADD CONSTRAINT `tedaviler_ibfk_1` FOREIGN KEY (`HayvanID`) REFERENCES `hayvanlar` (`HayvanID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tedaviler_ibfk_2` FOREIGN KEY (`VeterinerID`) REFERENCES `veterinerler` (`VeterinerID`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
