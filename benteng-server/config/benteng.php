<?php

return [
    // Token rahasia yang sama dengan TOKEN di /usr/bin/benteng-agent pada sensor.
    'sensor_token' => env('BENTENG_SENSOR_TOKEN', ''),

    // Mode demo untuk juri: data simulasi dan sensor tiruan, tanpa VM atau sensor sungguhan.
    // Diaktifkan otomatis oleh `php artisan benteng:siapkan-demo` pada instalasi baru.
    'mode_demo' => (bool) env('BENTENG_MODE_DEMO', false),

    // Sensor dianggap online bila benteng-responder menarik perintah dalam sekian
    // detik terakhir. Responder menarik tiap 5 detik, jadi 30 detik = 6x terlewat.
    'responder_online_detik' => 30,

    // Perintah yang belum di-ack lewat dari sekian detik ditandai "tertahan".
    'perintah_tertahan_detik' => 30,

    // Detektor terjadwal tiap menit; lewat dari ini dianggap tidak berjalan.
    'detektor_online_detik' => 150,

    // Keterangan sensor untuk diagram topologi (opsional, per ID sensor).
    'sensor_info' => [
        'BTG-02' => ['model' => 'Sensor tepi jaringan', 'lan' => '192.168.1.1/24', 'lokasi' => 'Lab VirtualBox'],
    ],

    // Keterangan server pusat untuk diagram topologi.
    'server_info' => ['nama' => 'Server pusat', 'alamat' => '192.168.56.1:8000', 'keterangan' => 'Laravel · on-premise'],

    // Label jenis insiden untuk tampilan.
    'jenis' => [
        'beaconing' => 'Beaconing',
        'dga' => 'Domain acak (DGA)',
        'perangkat_asing' => 'Perangkat asing',
        'domain_terblokir' => 'Domain terblokir',
    ],

    // Nama teknik MITRE ATT&CK yang dipakai detektor.
    'attck' => [
        'T1071.004' => 'Application Layer Protocol: DNS',
        'T1568.002' => 'Dynamic Resolution: Domain Generation Algorithms',
        'T1200' => 'Hardware Additions',
    ],

    /*
     * Playbook per jenis insiden: penjelasan bahasa awam dan langkah yang
     * disarankan untuk satuan yang tidak punya personel siber khusus.
     */
    'playbook' => [
        'beaconing' => [
            'arti' => 'Perangkat ini menghubungi domain yang sama dengan jeda yang sangat teratur, seperti jam. '
                .'Pola ini khas malware yang "melapor" ke server pengendalinya (C2) sambil menunggu perintah.',
            'risiko' => 'Penyerang dapat mengendalikan perangkat dari jauh, mengambil data, atau memakainya '
                .'sebagai batu loncatan ke perangkat lain di jaringan satuan.',
            'langkah' => [
                'Karantina perangkat agar terputus dari jaringan.',
                'Bila perangkat harus tetap bekerja (mis. kamera CCTV), klik "Blokir domain" agar perangkat apa pun di jaringan tidak bisa menghubungi server itu.',
                'Catat pemilik/pengguna perangkat dan sejak kapan perangkat dipakai.',
                'Jangan instal ulang dulu: serahkan ke petugas siber satuan atas untuk diperiksa, bukti bisa hilang.',
                'Periksa apakah perangkat lain menghubungi domain yang sama.',
                'Bila domain itu ternyata layanan sah (mis. pembaruan aplikasi), klik "Bukan ancaman".',
            ],
        ],
        'dga' => [
            'arti' => 'Perangkat ini menanyakan banyak nama domain acak yang tidak bermakna. '
                .'Malware memakai teknik ini (Domain Generation Algorithm) untuk mencari server pengendali '
                .'yang alamatnya terus berganti agar sulit diblokir.',
            'risiko' => 'Perangkat kemungkinan besar sudah terinfeksi dan sedang mencoba terhubung ke penyerang.',
            'langkah' => [
                'Karantina perangkat agar terputus dari jaringan.',
                'Catat pemilik/pengguna perangkat dan aplikasi yang baru dipasang.',
                'Jangan instal ulang dulu: serahkan ke petugas siber satuan atas untuk diperiksa, bukti bisa hilang.',
                'Bila perangkat menjalankan aplikasi sah yang memang memakai nama acak (mis. CDN), klik "Bukan ancaman".',
            ],
        ],
        'domain_terblokir' => [
            'arti' => 'Perangkat ini masih mencoba menghubungi domain yang sudah diblokir analis. '
                .'Sensor menolak permintaannya, jadi koneksinya gagal, tetapi percobaan itu menandakan ada program di perangkat yang masih berjalan.',
            'risiko' => 'Perangkat kemungkinan terinfeksi malware yang sama dengan perangkat pertama, '
                .'atau menjalankan aplikasi yang sama yang melapor ke domain tersebut.',
            'langkah' => [
                'Karantina perangkat bila pemiliknya tidak bisa memastikan aplikasi apa yang menghubungi domain itu.',
                'Periksa apakah perangkat ini punya aplikasi atau riwayat yang sama dengan perangkat pertama.',
                'Bila ternyata wajar (mis. perangkat yang sama jenisnya dan sudah diperiksa), klik "Bukan ancaman".',
            ],
        ],
        'perangkat_asing' => [
            'arti' => 'Ada perangkat baru yang tersambung ke jaringan satuan dan belum terdaftar sebagai perangkat sah. '
                .'Bisa jadi laptop/HP tamu, perangkat pribadi personel, atau perangkat milik pihak luar.',
            'risiko' => 'Perangkat tak dikenal dapat dipakai untuk menyadap lalu lintas jaringan, memindai perangkat lain, '
                .'atau menjadi pintu masuk malware ke jaringan satuan.',
            'langkah' => [
                'Cek fisik: siapa pemilik perangkat dengan MAC dan nama host tersebut.',
                'Bila sah (milik satuan atau sudah diizinkan), klik "Daftarkan sah".',
                'Bila pemilik tidak jelas, karantina perangkat dan laporkan ke petugas siber satuan atas.',
            ],
        ],
    ],
];
