#!/usr/bin/env python3
"""
BENTENG - simulator perangkat IoT/OT (dijalankan di VM lab, mis. benteng-cctv).

Meniru jejak jaringan kamera CCTV atau perangkat pintar yang rutin melapor ke
server cloud vendornya. Ini BUKAN perangkat asli dan BUKAN malware: skrip hanya
menanyakan nama domain ke resolver, tidak pernah membuka koneksi ke server itu,
tidak mengirim data, dan tidak menjalankan perintah dari luar.

Sensor BENTENG di tepi jaringan hanya melihat MAC, nama host, dan query DNS,
jadi jejak ini cukup untuk menguji deteksinya. Nama domain mengikuti layanan
cloud vendor; jadwalnya rekaan untuk keperluan lab.

Jalankan terus-menerus di VM:
    python3 benteng-iot-sim.py --profil cctv-hikvision

Hentikan kapan saja dengan Ctrl+C.
"""

import argparse
import random
import socket
import sys
import time

# Daftar (domain, jeda detik). Beacon utama 30 detik supaya pola terdeteksi
# dalam 5-6 menit saat demo; NTP masuk daftar izin detektor, jadi tidak memicu alarm.
PROFIL = {
    "cctv-hikvision": [("litedev.hik-connect.com", 30), ("pool.ntp.org", 600)],
    "nvr-dahua": [("www.easy4ip.com", 30), ("pool.ntp.org", 600)],
    "colokan-tuya": [("a1.tuyaus.com", 30), ("pool.ntp.org", 900)],
}


def lookup(nama):
    """Menanyakan satu nama domain ke resolver. Gagal pun tidak masalah."""
    try:
        socket.getaddrinfo(nama, 443)
    except OSError:
        pass


def main():
    p = argparse.ArgumentParser(description="Simulator perangkat IoT/OT untuk lab BENTENG")
    p.add_argument("--profil", choices=sorted(PROFIL), default="cctv-hikvision", help="jenis perangkat yang ditiru")
    p.add_argument("--jitter", type=float, default=0.05, help="variasi jeda, mis. 0.05 = +-5%% (default 0.05)")
    args = p.parse_args()

    jadwal = PROFIL[args.profil]
    berikut = {domain: time.time() for domain, _ in jadwal}

    print(f"[iot] profil {args.profil} berjalan. Ctrl+C untuk berhenti.", flush=True)
    try:
        while True:
            for domain, jeda in jadwal:
                if time.time() >= berikut[domain]:
                    lookup(domain)
                    print(f"[iot] {time.strftime('%H:%M:%S')} query {domain}", flush=True)
                    berikut[domain] = time.time() + jeda * random.uniform(1 - args.jitter, 1 + args.jitter)
            time.sleep(1)
    except KeyboardInterrupt:
        print("\n[iot] dihentikan.")

    return 0


if __name__ == "__main__":
    sys.exit(main())
