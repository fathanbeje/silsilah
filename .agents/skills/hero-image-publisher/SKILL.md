---
name: hero-image-publisher
description: Workflow and turnkey script to convert raw photos into optimized WebP format (saving 50%+ bandwidth), upload to WordPress media library on VPS, create hero slider post with dynamic caption, and purge Nginx cache for MI Almaarif 02 Singosari.
---

# Hero Image Publisher & Optimizer

Skill untuk mengonversi foto dokumentasi kegiatan menjadi format WebP modern dengan kompresi optimal, mengunggah ke WordPress VPS MI Almaarif 02 Singosari, memublikasikan sebagai slide utama pada hero homepage, serta membersihkan cache Nginx secara otomatis.

---

## Kapan Menggunakan Skill Ini

Gunakan skill ini ketika:
- Pengguna meminta mengganti atau menambahkan foto baru pada slider hero di beranda website (`https://mia02sgs.sch.id`).
- Pengguna meminta mengonversi foto (JPEG, PNG) ke format WebP dan mengoptimasi ukurannya agar cepat dimuat (LCP ramah Core Web Vitals).
- Pengguna memberikan caption atau judul tertentu untuk foto hero (misalnya kegiatan praktik, upacara, pramuka, prestasi, dll.).

---

## 1. Perintah Otomatis (Turnkey CLI)

Telah disediakan skrip otomasi lengkap yang mengeksekusi seluruh siklus hidup (optimasi lokal, upload VPS, registrasi WordPress, purge cache, dan verifikasi live):

```powershell
python .agents/skills/hero-image-publisher/publish_hero.py "path/to/foto.jpg" --caption "Judul / Caption Foto Hero"
```

### Opsi Tambahan:
- `--quality <int>`: Tingkat kualitas WebP (default: `82`, kisaran rekomendasi: 80–85).
- `--no-vps`: Hanya mengonversi dan menyimpan ke folder lokal `images/` tanpa mengunggah ke server VPS.

Contoh eksekusi:
```powershell
python .agents/skills/hero-image-publisher/publish_hero.py "C:/Users/Administrator/Pictures/kegiatan-upacara.jpg" --caption "Upacara Hari Santri Nasional"
```

---

## 2. Standar Optimasi & Spesifikasi Hero

1. **Format & Kualitas WebP**:
   - Engine: Python Pillow (`method=6`, `quality=82`, sRGB color space).
   - Efisiensi: Reduksi ukuran file sebesar 50%–65% dibanding format JPEG awal tanpa penurunan ketajaman visual kasat mata.
   - Ukuran target: Di bawah 150 KB untuk dimensi penuh 1024px.

2. **Rasio Visual Hero Frame**:
   - Frame CSS: `aspect-ratio: 16 / 10; max-height: 440px;` dengan `border-radius: 120px 16px 16px 16px`.
   - Perilaku Foto: `object-fit: cover; object-position: center;`.
   - Pastikan objek penting (wajah siswa, aktivitas tangan, dll.) berada di area tengah (center safe zone).

3. **Responsif Otomatis (`srcset`)**:
   - Fungsi WordPress `wp_generate_attachment_metadata()` otomatis memproduksi sub-ukuran WebP:
     - `768x512` (tablet & lanskap mobile)
     - `600x450` (news/grid)
     - `300x200` (kartu/mobile compact)

4. **Kecepatan LCP (Largest Contentful Paint)**:
   - Slide urutan pertama (#1) otomatis menerima:
     - Tag `<link rel="preload" as="image" href="..." fetchpriority="high">` di dalam `<head>`.
     - Atribut `loading="eager"` dan `fetchpriority="high"` pada elemen `<img>`.
   - Slide berikutnya (#2, #3) menerima `loading="lazy"` dan `fetchpriority="low"`.

---

## 3. Alur Kerja Teknis (Pipeline Architecture)

```
[Foto Asli JPG/PNG]
         ↓
[Python PIL: WebP Quality 82, Method 6] ──→ Simpan lokal: theme-mading-mia02/images/<slug>.webp
         ↓
[SCP via Port 2288 & Key vps_deploy_ed25519] ──→ VPS: /tmp/<slug>.webp
         ↓
[PHP WordPress Context pada VPS]:
   ├── Salin ke wp-content/uploads/YYYY/MM/<slug>.webp
   ├── wp_insert_attachment (mime: image/webp)
   ├── wp_generate_attachment_metadata (hasilkan srcset responsif)
   ├── update_post_meta alt text
   ├── wp_insert_post (post_type: slider, status: publish, date: now)
   └── set_post_thumbnail
         ↓
[Purge Nginx Proxy Cache]:
   rm -rf /www/server/nginx/proxy_cache_dir/* && /etc/init.d/nginx reload
         ↓
[Live HTTP Verification]:
   Cek HTTP 200, keberadaan caption, slide aktif, dan tag preload di https://mia02sgs.sch.id
```

---

## 4. Prosedur Manual (Bila Diperlukan Debugging)

Jika script otomasi tidak dapat dijalankan secara langsung, berikut langkah manual via SSH:

### A. Konversi Gambar Lokal
```python
from PIL import Image
img = Image.open("input.jpg").convert("RGB")
img.save("output.webp", "WEBP", quality=82, method=6)
```

### B. Salin ke VPS
```powershell
scp -P 2288 -i C:/Users/Administrator/.ssh/vps_deploy_ed25519 output.webp root@103.177.95.140:/tmp/output.webp
```

### C. Eksekusi Registrasi Media di VPS
```bash
ssh -p 2288 -i C:/Users/Administrator/.ssh/vps_deploy_ed25519 root@103.177.95.140 "bash"
# Jalankan script PHP wp-load untuk insert attachment & slider post
```

### D. Bersihkan Cache Nginx
```bash
rm -rf /www/server/nginx/proxy_cache_dir/* && /etc/init.d/nginx reload
```

---

## 5. Standar Anti-Slop & Rasa Desain Madrasah
- **Keaslian**: Selalu gunakan dokumentasi foto riil kegiatan siswa dan lingkungan MI Almaarif 02 Singosari, hindari stok foto generik atau AI mockups.
- **Tipografi**: Caption hero menggunakan nama kegiatan yang jelas dan lugas tanpa tanda em-dash (—).
- **Aksesibilitas**: Alt text pada tag gambar selalu diisi sesuai caption untuk pembaca layar (screen reader WCAG AA).
