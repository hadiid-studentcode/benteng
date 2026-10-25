#!/usr/bin/env python3
"""
BENTENG - pembangkit lalu lintas UJI (dijalankan di VM korban lab).

Tujuan: menghasilkan pola DNS yang HARUS dikenali oleh detektor BENTENG,
supaya alur deteksi bisa dibuktikan saat demo. Ini BUKAN malware:

- tidak menjalankan perintah apa pun dari luar,
- tidak membawa/mengunduh payload,
- tidak menyembunyikan diri,
- hanya menanyakan nama domain ke resolver (persis seperti aplikasi biasa).

Yang dilakukan hanya dua hal yang tampak dari sisi DNS:
1. "beacon": menanyakan satu domain tetap secara berkala dengan interval
   sangat teratur (ciri perangkat yang menunggu perintah dari server C2);
2. "DGA": menanyakan banyak nama domain acak dalam waktu singkat (ciri
   malware yang memakai Domain Generation Algorithm).

Jalankan di VM korban:
    python3 benteng-trafik-uji.py --menit 6                    # demo: beacon + DGA
    python3 benteng-trafik-uji.py --menit 30 --jitter 0.2      # beacon disamarkan +-20%
    python3 benteng-trafik-uji.py --mode normal --menit 60     # uji alarm palsu: akses web acak

Mode "normal" meniru pengguna biasa: menanyakan domain populer pada waktu acak.
Setelah selesai, dashboard seharusnya TIDAK menampilkan insiden baru.

Hentikan kapan saja dengan Ctrl+C.
"""

import argparse
import random
import socket
import string
import sys
import time

# Domain "beacon" memakai TLD .example yang memang dicadangkan untuk contoh
# (RFC 2606), jadi tidak pernah benar-benar ada pemiliknya.
BEACON_DOMAIN = "c2-sim.example"
DGA_TLDS = ("example", "test", "invalid")

# Domain populer untuk mode "normal". Hanya ditanyakan ke DNS, tidak dibuka.
DOMAIN_NORMAL = (
    "www.detik.com", "www.kompas.com", "www.youtube.com", "www.tokopedia.com",
    "shopee.co.id", "www.kemhan.go.id", "tniad.mil.id", "web.whatsapp.com",
    "www.instagram.com", "id.wikipedia.org", "www.cnnindonesia.com", "www.bca.co.id",
    "www.liputan6.com", "api.github.com", "outlook.office365.com", "www.tiktok.com",
)


def lookup(nama):
    """Menanyakan satu nama domain ke resolver. Gagal pun tidak masalah;
    yang penting query-nya tercatat oleh sensor."""
    try:
        socket.getaddrinfo(nama, 80)
    except OSError:
        pass


def nama_dga():
    panjang = random.randint(12, 20)
    acak = "".join(random.choice(string.ascii_lowercase) for _ in range(panjang))
    return acak + "." + random.choice(DGA_TLDS)


def mode_normal(selesai, per_menit):
    """Akses acak bergaya manusia (proses Poisson); domain awal daftar lebih sering."""
    bobot = [1 / (i + 1) for i in range(len(DOMAIN_NORMAL))]
    n = 0
    while time.time() < selesai:
        time.sleep(random.expovariate(per_menit / 60))
        nama = random.choices(DOMAIN_NORMAL, weights=bobot)[0]
        lookup(nama)
        n += 1
        if n % 20 == 0:
            print(f"[uji] query normal: {n}")
    print(f"[uji] selesai. query normal={n}. Dashboard seharusnya tidak menampilkan insiden baru.")


def main():
    p = argparse.ArgumentParser(description="Pembangkit lalu lintas uji BENTENG")
    p.add_argument("--mode", choices=("serangan", "normal"), default="serangan",
                   help="serangan = beacon + DGA (default), normal = akses web acak untuk uji alarm palsu")
    p.add_argument("--menit", type=float, default=6, help="lama berjalan (menit)")
    p.add_argument("--interval", type=float, default=30, help="jeda beacon (detik)")
    p.add_argument("--jitter", type=float, default=0.02,
                   help="variasi jeda beacon, mis. 0.2 = +-20%% (default 0.02)")
    p.add_argument("--jeda-dga", type=float, default=1.5, help="jeda antar query DGA (detik)")
    p.add_argument("--per-menit", type=float, default=4, help="rata-rata query per menit pada mode normal")
    args = p.parse_args()

    selesai = time.time() + args.menit * 60
    if args.mode == "normal":
        print(f"[uji] mode normal, berhenti dalam {args.menit} menit. Ctrl+C untuk berhenti.")
        try:
            mode_normal(selesai, args.per_menit)
        except KeyboardInterrupt:
            print("\n[uji] dihentikan.")
        return 0
    beacon_berikut = time.time()
    n_beacon = 0
    n_dga = 0

    print(f"[uji] mulai, berhenti dalam {args.menit} menit. Ctrl+C untuk berhenti.")
    try:
        while time.time() < selesai:
            sekarang = time.time()

            # Beacon teratur: interval tetap dengan sedikit variasi (default 2%),
            # supaya terlihat "terlalu rapi" seperti mesin, bukan manusia.
            if sekarang >= beacon_berikut:
                lookup(BEACON_DOMAIN)
                n_beacon += 1
                variasi = args.interval * args.jitter
                beacon_berikut = sekarang + args.interval + random.uniform(-variasi, variasi)
                print(f"[uji] beacon #{n_beacon} -> {BEACON_DOMAIN}")

            # Semburan domain acak (DGA).
            lookup(nama_dga())
            n_dga += 1
            if n_dga % 10 == 0:
                print(f"[uji] query DGA: {n_dga}")

            time.sleep(args.jeda_dga)
    except KeyboardInterrupt:
        print("\n[uji] dihentikan.")

    print(f"[uji] selesai. beacon={n_beacon}, query DGA={n_dga}")


if __name__ == "__main__":
    sys.exit(main())
