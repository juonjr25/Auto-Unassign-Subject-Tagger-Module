# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.2.0] – 2025-07-14

### Added

- Logic untuk memaksa kolom `To` email menjadi **static** dan `readonly` saat membuka reply yang subjectnya terdapat notiket `Ticket#<ID>`.
- Fitur untuk membersihkan semua isi kolom `CC` saat membuka reply yang subjectnya terdapat notiket `Ticket#<ID>`.
- Penambahan parser untuk **subject email encoded** (RFC2047), agar tetap bisa mendeteksi dan menambahkan `Ticket#<ID>` meskipun subject dalam format MIME encoded.

---

## [1.1.0] – 2025-07-03

### Added

- Parsing khusus untuk email ConnectWise agar isi email tampil **lebih rapi** dan hanya menampilkan pesan terbaru.
- Deteksi 3 jenis template email:
  - **NOTE**
  - **INTERNAL NOTE**
  - **ASSIGN UPDATE**
- Email body diolah agar tidak menampilkan isi balasan sebelumnya (quoted), hanya bagian penting.
- Penambahan header `INTERNAL NOTE` dan struktur tampilan seperti avatar + waktu.

---

## [1.0.0] – 2025-06-09

### Added

- Versi awal module **AutoUnassign**.
- Otomatis menghapus assign agent (`user_id = null`) saat customer membalas email.
- Perubahan status percakapan menjadi **Active** agar bisa diambil oleh agent lain.
- Penambahan `Ticket#<ID>` di depan subject email jika belum ada, untuk mempermudah pelacakan tiket.
