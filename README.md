# Warehouse Management System (WMS)

---

## System Requirements

- **Web Server & Database**: [XAMPP](https://www.apachefriends.org/) (Apache + MySQL / MariaDB)
- **PHP Version**: PHP 8.0 or higher
- **PHP Extension**: `pdo_mysql`, `session`, `gd` (opsional untuk pemrosesan gambar)
- **Browser Modern**: Google Chrome, Microsoft Edge, atau Mozilla Firefox (dengan izin kamera untuk fitur scan barcode/QR).

---

## Installation Steps

### 1. Pindahkan Folder Proyek
Ekstrak atau pindahkan folder `inventory` ke dalam direktori `htdocs` instalasi XAMPP Anda:
```text
C:/xampp/htdocs/inventory
```

### 2. Jalankan Apache & MySQL
Buka **XAMPP Control Panel**, kemudian klik tombol **Start** pada modul:
- **Apache**
- **MySQL**

### 3. Import Database
1. Buka browser dan akses phpMyAdmin di [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Buat database baru dengan nama: `inventory_system` *(collation: `utf8mb4_general_ci`)*.
3. Pilih database `inventory_system`, lalu klik tab **Import** pada menu atas.
4. Klik tombol **Choose File** / **Browse**, lalu pilih file:
   ```text
   C:/xampp/htdocs/inventory/inventory_system.sql
   ```
5. Gulir ke bawah dan klik tombol **Import** (tunggu hingga seluruh tabel dan data selesai diimpor).

### 4. Verifikasi Konfigurasi
Buka berkas `includes/config.php` jika Anda menggunakan konfigurasi database yang berbeda dari default XAMPP:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'inventory_system');
define('DB_USER', 'root');
define('DB_PASS', ''); // default password di XAMPP biasanya kosong
define('BASE_URL', '/inventory');
```

### 5. Buka Aplikasi
Akses aplikasi melalui browser:
**[http://localhost/inventory](http://localhost/inventory)**

---

## Akun Pengguna & Hak Akses (User Credentials)

Aplikasi memiliki matriks peran (*Role-Based Access Control*):

| Peran (Role) | Hak Akses Utama |
| :--- | :--- |
| **Super Admin** | Akses penuh seluruh sistem: Master Data, Inventory, Movements, Manajemen User, Pembersihan Log, & Hapus Permanen. |
| **Admin** | Pengelolaan Master Data, Inventory, Movements, dan Audit Logs. |
| **Engineer** | Pengelolaan data Inventory (Edit, Split, Merge), Movements, & Check-In. |
| **Technician** | Transaksi Movements (Inbound, Outbound, Transfers) & Check-In. |
| **Customer** | Khusus melihat daftar peralatan (*Equipment Tracking*) milik perusahaannya sendiri & Check-In. |
| **Guest** | Mode peninjau (*View-Only*) untuk katalog umum. |

---

## Struktur Direktori

```text
inventory/
├── actions/                  # Endpoint AJAX dan pemrosesan mutasi backend
├── css/                      # Desain tema visual, responsivitas, dan aturan cetak
├── img/                      # Aset ikon dan logo aplikasi
├── includes/                 # Komponen layout modular, helper functions, & db config
├── js/                       # Engine JavaScript client (table grid, barcode/QR scanner)
├── pages/                    # Controller tampilan halaman utama
├── uploads/                  # Media foto barang dan avatar profil pengguna
│   └── avatars/
├── index.php                 # Router dan entry point
├── login.php / logout.php    # Autentikasi sesi
├── inventory_system.sql      # Dump skema & data database
└── README.md                 # Dokumentasi ini
```
