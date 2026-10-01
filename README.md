# E.L.V MANAGER

> **Tactical File Manager untuk WordPress --- Cyberpunk Neon UI,
> Terminal Shell & Full-Screen File Management**

**E.L.V MANAGER v1.2.2** adalah file manager berbasis PHP dalam satu
file yang menggunakan **elFinder** sebagai core file manager, dengan
antarmuka cyberpunk/neon, terminal shell, navigasi parent directory, dan
editor kode berbasis CodeMirror.

------------------------------------------------------------------------

## ✦ Features

-   ⚡ **Single-file PHP** --- cukup satu file `elvmanager.php`.
-   🗂️ **Full file manager** berbasis elFinder.
-   💻 **Built-in terminal / shell**.
-   🧭 **Parent directory navigation** dan root override melalui
    session.
-   🎨 **Cyberpunk Neon UI** dengan tampilan immersive/full-screen.
-   📝 **Code editor** menggunakan CodeMirror.
-   🌐 Dukungan WordPress melalui `wp-load.php`.
-   🇮🇩 Locale default `id`.
-   🔐 Session cookie dengan `HttpOnly`, `SameSite=Lax`, dan strict
    session mode.
-   📱 Responsive viewport untuk penggunaan pada perangkat mobile.
-   🔧 Mode **read-only** yang dapat diaktifkan melalui konfigurasi.
-   🚫 Beberapa operasi elFinder dapat dinonaktifkan dari konfigurasi.
-   📦 elFinder, jQuery, jQuery UI, dan CodeMirror dimuat melalui CDN.

------------------------------------------------------------------------

## ⚙️ Requirements

  Requirement                                                    Minimum
  ------------------- --------------------------------------------------
  PHP                                                               7.4+
  WordPress             5.8+ *(jika digunakan sebagai bagian WordPress)*
  Web Server                         Apache / Nginx / server PHP lainnya
  JavaScript                                                     Enabled
  PHP `proc_open()`                            Dibutuhkan untuk terminal

> Jika terminal tidak diperlukan, pertimbangkan untuk menonaktifkan atau
> menghapus fitur terminal pada deployment publik.

------------------------------------------------------------------------

## 🚀 Installation

### 1. Download

Clone repository:

``` bash
git clone https://github.com/NONAME-ELV/E..L.V-Manager-v1.2.2.git
```

Atau download file `elvmanager.php` secara langsung dari repository.

### 2. Upload

Upload:

``` text
elvmanager.php
```

ke lokasi server yang dapat menjalankan PHP.

Untuk instalasi WordPress, file dapat ditempatkan pada direktori yang
sesuai dengan struktur deployment Anda.

### 3. Konfigurasi

Konfigurasi utama berada di bagian awal file:

``` php
$ELV = array(
    'root'                 => '',
    'wp_load'              => '',
    'bootstrap_wp'         => true,
    'allow_wp_elv_manager' => true,
    'read_only'            => false,
    'locale'               => 'id',
    'hidden'               => array(),
);
```

### 4. Root directory

Secara default, E.L.V MANAGER mencoba menentukan root berdasarkan
WordPress atau `DOCUMENT_ROOT`.

Untuk menentukan root secara manual:

``` php
'root' => '/path/to/your/root',
```

### 5. WordPress bootstrap

Jika digunakan bersama WordPress:

``` php
'bootstrap_wp' => true,
```

E.L.V MANAGER akan mencoba menemukan `wp-load.php` dari lokasi file dan
direktori parent-nya.

Jika lokasi `wp-load.php` perlu ditentukan secara manual:

``` php
'wp_load' => '/path/to/wordpress/wp-load.php',
```

------------------------------------------------------------------------

## 🖥️ Terminal

E.L.V MANAGER memiliki halaman terminal:

``` text
?elv_view=terminal
```

Terminal menjalankan command melalui PHP `proc_open()`.

Fitur terminal mencakup:

-   Command execution
-   `cd`
-   Current working directory
-   Command history
-   `↑ / ↓` untuk history
-   `Ctrl + L` untuk clear
-   `Ctrl + C` untuk cancel
-   `Tab` untuk autocomplete
-   Timeout command

> **WARNING:** Terminal memberikan kemampuan menjalankan command sistem
> dengan hak akses user PHP/web server. Jangan expose fitur ini ke
> internet tanpa sistem autentikasi dan authorization yang kuat.

------------------------------------------------------------------------

## 📝 Code Editor

File tertentu dapat diedit menggunakan **CodeMirror**.

Mode yang tersedia antara lain:

-   PHP
-   HTML
-   CSS
-   JavaScript
-   XML
-   SQL
-   Markdown
-   C / C++
-   Java
-   JSON
-   Shell
-   Python
-   Ruby
-   Perl

Editor dipilih berdasarkan MIME type file.

------------------------------------------------------------------------

## 🔒 Read-Only Mode

Untuk mencegah operasi modifikasi file melalui elFinder:

``` php
'read_only' => true,
```

Dalam mode ini operasi seperti berikut dinonaktifkan:

-   Upload
-   Create directory
-   Create file
-   Delete
-   Rename
-   Paste
-   Duplicate
-   Edit
-   Resize
-   CHMOD
-   Archive
-   Extract
-   Cut
-   Copy

Mode default:

``` php
'read_only' => false,
```

------------------------------------------------------------------------

## 🎨 UI

E.L.V MANAGER menggunakan desain **Cyberpunk Neon** dengan elemen:

-   Dark interface
-   Cyan neon
-   Magenta neon
-   Violet accent
-   Terminal-style typography
-   Full-screen file manager
-   Immersive navigation
-   E.L.V / HxN branding

Terminal menggunakan font `Share Tech Mono` dan `Rajdhani`.

------------------------------------------------------------------------

## 🌐 External Dependencies

Project memuat beberapa dependency dari CDN:

### elFinder

``` text
https://cdn.jsdelivr.net/gh/Studio-42/elFinder@2.1.66/
```

### jQuery

``` text
https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js
```

### jQuery UI

``` text
https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js
```

### CodeMirror

``` text
https://cdn.jsdelivr.net/npm/codemirror@5.65.16/
```

Pastikan server memiliki akses internet jika deployment bergantung pada
CDN tersebut.

------------------------------------------------------------------------

## ⚠️ Security

**Baca bagian ini sebelum deployment.**

Versi `v1.2.2` yang ada di repository saat ini mendefinisikan:

``` php
function elv_authed()
{
    return true;
}
```

Artinya fungsi autentikasi tersebut selalu menganggap request telah
terautentikasi.

Selain itu, terminal menggunakan `proc_open()` untuk menjalankan command
pada server.

### Risiko

Jika file ini dapat diakses publik tanpa lapisan proteksi tambahan,
pihak yang tidak berwenang berpotensi:

-   membaca file server,
-   mengubah atau menghapus file,
-   mengunggah file,
-   menjalankan command sistem,
-   mengakses file WordPress,
-   memodifikasi konfigurasi aplikasi.

### Rekomendasi deployment

Sebelum deployment publik:

1.  Tambahkan autentikasi yang benar.
2.  Batasi akses berdasarkan user/role.
3.  Gunakan HTTPS.
4.  Batasi root directory.
5.  Jangan memberikan akses root filesystem jika tidak diperlukan.
6.  Pertimbangkan menonaktifkan terminal.
7.  Aktifkan `read_only` jika hanya membutuhkan fungsi browsing.
8.  Batasi permission user web server.
9.  Jangan menyimpan file ini di lokasi yang dapat diakses publik jika
    tidak diperlukan.
10. Audit endpoint connector sebelum digunakan pada production.

**Jangan menganggap tampilan login/secure access sebagai proteksi yang
cukup apabila authorization backend masih mengembalikan `true`.**

------------------------------------------------------------------------

## 🧩 Configuration Example

Contoh konfigurasi yang lebih aman untuk penggunaan file browser:

``` php
$ELV = array(
    'root'                 => '/var/www/example',
    'wp_load'              => '',
    'bootstrap_wp'         => true,
    'allow_wp_elv_manager' => true,
    'read_only'            => true,
    'locale'               => 'id',
    'hidden'               => array(),
);
```

Jika membutuhkan kemampuan upload/edit/delete, ubah `read_only` menjadi
`false` hanya setelah authorization dan filesystem permission sudah
diamankan.

------------------------------------------------------------------------

## 📁 Project Structure

Versi ini menggunakan pendekatan single-file:

``` text
E..L.V-Manager-v1.2.2/
└── elvmanager.php
```

Core elFinder juga di-embed di dalam file PHP, sedangkan beberapa
frontend dependency dimuat melalui CDN.

------------------------------------------------------------------------

## 🛠️ Tech Stack

-   PHP
-   WordPress integration
-   elFinder
-   jQuery
-   jQuery UI
-   CodeMirror
-   HTML5
-   CSS3
-   JavaScript
-   PHP Sessions

------------------------------------------------------------------------

## 📌 Version

**Current version:** `1.2.2`

``` text
Plugin Name: E.L.V MANAGER
Version:     1.2.2
Requires WP: 5.8+
Requires PHP: 7.4+
License:     GPL-2.0-or-later
Author:      HxN
```

------------------------------------------------------------------------

## 👤 Author

**HxN**

E.L.V MANAGER --- Tactical File System

------------------------------------------------------------------------

## 📜 License

This project declares:

``` text
GPL-2.0-or-later
```

See the source repository for the complete licensing context and bundled
third-party components.

------------------------------------------------------------------------

## ⚡ Disclaimer

E.L.V MANAGER is a powerful server-side file management tool. The
administrator is responsible for securing the deployment, configuring
filesystem permissions, restricting access, and protecting the server
from unauthorized use.

**Do not expose an unrestricted installation to an untrusted network.**

------------------------------------------------------------------------

## 🔗 Source

Repository:

https://github.com/NONAME-ELV/E..L.V-Manager-v1.2.2/

Main file:

https://raw.githubusercontent.com/NONAME-ELV/E..L.V-Manager-v1.2.2/refs/heads/main/elvmanager.php
