🎯 TUJUAN

Bikin sistem logging di Laravel yang:

Track semua endpoint dipanggil
Bisa debug & audit
Bisa dimonitor di Grafana
🧭 OVERVIEW ARSITEKTUR
Request → Middleware → Simpan Log → Kirim ke Storage → Visualisasi (Grafana)
🪜 PLANNING STEP BY STEP
1. Tangkap semua request (Middleware)

Buat 1 middleware global:

Ambil data penting:
endpoint
method
status code
user_id
IP
duration (waktu response)

👉 Ini adalah “source of truth” logging kamu

2. Tentukan format log (Structured JSON)

Gunakan format JSON, contoh:

{
  "endpoint": "/api/user",
  "method": "GET",
  "status": 200,
  "duration_ms": 120,
  "user_id": 1
}

👉 Kenapa:

mudah dibaca mesin
langsung kompatibel ke Grafana / Loki
3. Simpan log (pilih 1 dulu)
🟢 Tahap awal (simple)
Simpan ke file Laravel log

👉 cepat, tanpa setup tambahan

🟡 Tahap menengah
Simpan ke database (api_logs)

👉 untuk audit & query manual

🔵 Tahap production (RECOMMENDED)
Kirim ke log system:
Loki (Grafana)
atau OpenTelemetry collector

👉 ini yang scalable

📊 INTEGRASI KE GRAFANA
Cara paling realistis:
🔥 Stack:
Grafana
Loki
Promtail (ambil log dari Laravel)
Alur:
Laravel → log file (JSON)
        ↓
Promtail
        ↓
Loki
        ↓
Grafana Dashboard
Step high-level:
1. Laravel output JSON log
ubah logging.php → format JSON
2. Jalankan Loki + Grafana

Biasanya via Docker

3. Jalankan Promtail
baca file:
storage/logs/laravel.log
4. Connect Grafana ke Loki
Tambah datasource Loki
5. Buat dashboard

Contoh query:

jumlah request per endpoint
error rate (status >= 500)
latency (duration_ms)
⚡ BEST PRACTICE (WAJIB)
❌ Jangan log semua mentah
filter endpoint (skip health check)
❌ Jangan log data sensitif
password
token
cookie
✅ Gunakan async (queue)
logging jangan blocking request
✅ Tambahkan request_id
untuk tracing antar service
🚀 ROADMAP IMPLEMENTASI
Phase 1 (1–2 jam)
Middleware logging
Simpan ke file
Phase 2
Format JSON
Tambah filtering & masking
Phase 3
Setup Loki + Grafana
Kirim log via Promtail
Phase 4 (advanced)
Tambah alerting di Grafana
Tambah tracing (OpenTelemetry)
💡 OUTPUT AKHIR YANG IDEAL

Di Grafana kamu bisa lihat:

📈 request per detik
❌ error rate
⏱️ latency endpoint
👤 aktivitas user
🧠 Insight penting

Kalau cuma log ke file → itu logging biasa
Kalau masuk Grafana → itu jadi observability system
