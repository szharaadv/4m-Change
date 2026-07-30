# 4M Change Management System

Aplikasi web PHP untuk manajemen permohonan perubahan 4M
(Man, Material, Method, Machine) dengan alur approval bertahap.

---

## Cara Setup di XAMPP

### 1. Salin folder ke htdocs

Salin folder `4m-change` ke:
```
C:\xampp\htdocs\4m-change\
```

Struktur akhir:
```
htdocs/
└── 4m-change/
    ├── index.php
    ├── database.sql
    ├── README.md
    ├── assets/
    │   ├── css/style.css
    │   ├── js/app.js
    │   └── uploads/        ← dibuat otomatis saat upload
    ├── config/
    │   └── database.php
    ├── helpers/
    │   ├── auth.php
    │   └── common.php
    ├── modules/
    │   ├── auth/
    │   │   ├── login.php
    │   │   ├── process_login.php
    │   │   └── logout.php
    │   ├── changes/
    │   │   ├── index.php
    │   │   ├── create.php
    │   │   ├── store.php
    │   │   ├── detail.php
    │   │   ├── edit.php
    │   │   ├── update.php
    │   │   ├── process_approval.php
    │   │   └── export_csv.php
    │   └── dashboard/
    │       └── index.php
    └── templates/
        ├── header.php
        ├── navbar.php
        └── footer.php
```

---

### 2. Setup Database via phpMyAdmin

1. Buka browser → http://localhost/phpmyadmin
2. Klik tab **SQL**
3. Buka file `database.sql`, salin semua isinya
4. Paste ke kotak SQL di phpMyAdmin
5. Klik **Go**

Atau import langsung:
- Klik **Import** → pilih file `database.sql` → klik **Go**

Database `db_4m_change` akan dibuat otomatis beserta semua tabel dan data demo.

> **Upgrade dari install lama?** Jangan jalankan `database.sql` ulang.
> Jalankan `migrations/migration_002_three_roles_and_permissions.sql` untuk
> menggabungkan role lama (manager/qc/qc_prod) menjadi `user`, membuat tabel
> `role_permissions`, dan mengisi permission default. Pastikan minimal satu
> akun ber-role `superadmin`.

---

### 3. Konfigurasi Database (jika perlu)

Edit file `config/database.php`:
```php
$host = '127.0.0.1';   // Host MySQL (biasanya 127.0.0.1)
$db   = 'db_4m_change'; // Nama database
$user = 'root';          // Username MySQL
$pass = '';              // Password MySQL (kosong di XAMPP default)
```

---

### 4. Jalankan Aplikasi

Buka browser → **http://localhost/4m-change**

---

## Peran (Roles)

Sistem memakai **3 role**:

| Role         | Keterangan                                                                 |
|--------------|-----------------------------------------------------------------------------|
| `superadmin` | Akses penuh **selalu**. Satu-satunya role yang bisa mengatur hak akses role lain (menu **Roles**). |
| `admin`      | Hak aksesnya diatur oleh superadmin (default: penuh operasional + kelola user/routing/audit). |
| `user`       | Hak aksesnya diatur oleh superadmin (default: buat/edit/approve/export change request). |

Superadmin membuka menu **Roles** untuk memberi/mencabut permission per role
(lihat, buat, edit, approve manager, approve QC, export, kelola user, kelola
routing, lihat audit log). Superadmin selalu punya semua permission dan
tidak bisa dinonaktifkan.

Siapa yang melakukan approval tahap Manager vs QC ditentukan oleh **Routing
per departemen** + permission approve — bukan lagi oleh nama role.

## Akun Demo

| Username   | Password | Role        |
|------------|----------|-------------|
| superadmin | admin123 | superadmin  |
| admin      | admin123 | admin       |
| user       | admin123 | user        |

---

## Alur Approval

```
Draft → Submitted → Manager Approved → QC Approved → Closed
                ↘ Rejected (dari manager atau qc) → Edit & Resubmit → Submitted
```

| Status           | Siapa yang bisa approve        |
|------------------|-------------------------------|
| Submitted         | Manager / Admin               |
| Manager Approved  | QC / Admin                    |
| QC Approved       | QC / Admin (Final Submit)     |

---

## Fitur

- Login dengan session PHP
- Dashboard statistik (total, closed bulan ini, need customer, rejected)
- History change request dengan filter kategori, part name, status
- Tab: Semua / Perlu Approval Saya / Sudah Saya Proses
- Form New Change: 4M Category, Basic Info, Change Detail, Approval Info, Upload Foto
- Detail: info lengkap, progress approval bertahap, riwayat aktivitas
- Approval & Reject dengan catatan
- Edit & Resubmit untuk change yang Rejected
- Export CSV (dengan BOM untuk Excel)
- Upload foto before/after

---

## Membuka di VS Code

1. Buka VS Code
2. **File → Open Folder** → pilih `C:\xampp\htdocs\4m-change`
3. Disarankan install extension:
   - **PHP Intelephense** — autocomplete PHP
   - **PHP Debug** — debugging
   - **HTMLHint** — lint HTML
   - **Better Comments** — highlight komentar

---

## Catatan Production

- Ganti password plain text di database dengan `password_hash()`
- Aktifkan HTTPS
- Set `PDO::ERRMODE_EXCEPTION` tapi jangan tampilkan pesan error ke user
- Tambahkan CSRF token di semua form POST
- Atur permission folder `assets/uploads/` menjadi 755

---

## Struktur Database

| Tabel               | Keterangan                              |
|---------------------|-----------------------------------------|
| users               | Akun pengguna dengan role (superadmin/admin/user) |
| role_permissions    | Matriks hak akses per role (diatur superadmin) |
| change_requests     | Data permohonan perubahan 4M            |
| change_photos       | Foto before/after per change request    |
| change_attachments  | File lampiran lainnya                   |
| change_approvals    | Log setiap langkah approval/reject      |
| change_histories    | Riwayat semua aktivitas per request     |
