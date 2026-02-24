# ForumTR - PHP Forum Sistemi

Tam özellikli PHP + MySQL forum sistemi.

## Özellikler
- ✅ Kullanıcı kayıt ve giriş
- ✅ Kategoriler
- ✅ Konu açma ve yorum yapma
- ✅ Beğeni sistemi (AJAX)
- ✅ Çözüm işaretleme
- ✅ Admin paneli (kullanıcı, kategori, konu yönetimi)
- ✅ Konu sabitleme ve kilitleme
- ✅ Profil sayfaları
- ✅ Sayfalama
- ✅ Responsive tasarım

## Kurulum

### 1. Dosyaları Kopyalayın
Tüm dosyaları web sunucunuzun klasörüne kopyalayın (örn: `htdocs/forum/`).

### 2. Veritabanı Oluşturun
phpMyAdmin veya MySQL konsolunda:
```sql
SOURCE database.sql;
```

### 3. Config Dosyasını Düzenleyin
`config.php` dosyasını açın ve şu bilgileri güncelleyin:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // MySQL kullanıcı adınız
define('DB_PASS', '');           // MySQL şifreniz
define('DB_NAME', 'forum_db');
define('SITE_URL', 'http://localhost/forum');  // Site URL'niz
```

### 4. Test Girişi
- **Admin:** kullanıcı adı `admin`, şifre `password`
- **Kullanıcı:** kullanıcı adı `kullanici1`, şifre `password`

## Gereksinimler
- PHP 7.4+
- MySQL 5.7+ / MariaDB
- Apache/Nginx (mod_rewrite opsiyonel)

## Dosya Yapısı
```
forum/
├── config.php          # Veritabanı ve site ayarları
├── index.php           # Anasayfa
├── login.php           # Giriş
├── register.php        # Kayıt
├── logout.php          # Çıkış
├── category.php        # Kategori konuları
├── topic.php           # Konu detayı
├── topic_new.php       # Yeni konu
├── profile.php         # Kullanıcı profili
├── like.php            # Beğeni AJAX
├── database.sql        # Veritabanı yapısı
├── includes/
│   ├── header.php      # Üst şablon
│   └── footer.php      # Alt şablon
├── assets/
│   ├── css/style.css   # Ana CSS
│   └── js/main.js      # Ana JS
└── admin/
    ├── index.php        # Admin dashboard
    ├── users.php        # Kullanıcı yönetimi
    ├── categories.php   # Kategori yönetimi
    └── topics.php       # Konu yönetimi
```
