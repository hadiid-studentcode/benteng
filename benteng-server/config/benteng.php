<?php

return [
    // Token rahasia yang sama dengan TOKEN di /usr/bin/benteng-agent pada sensor.
    'sensor_token' => env('BENTENG_SENSOR_TOKEN', ''),

    // Sensor dianggap online bila benteng-responder menarik perintah dalam sekian
    // detik terakhir. Responder menarik tiap 5 detik, jadi 30 detik = 6x terlewat.
    'responder_online_detik' => 30,

    // Perintah yang belum di-ack lewat dari sekian detik ditandai "tertahan".
    'perintah_tertahan_detik' => 30,

    // Detektor terjadwal tiap menit; lewat dari ini dianggap tidak berjalan.
    'detektor_online_detik' => 150,

    // Keterangan sensor untuk diagram topologi (opsional, per ID sensor).
    'sensor_info' => [
        'BTG-02' => ['model' => 'OpenWrt 24.10 x86', 'lan' => '192.168.1.1/24', 'lokasi' => 'Lab VirtualBox'],
    ],

    // Keterangan server pusat untuk diagram topologi.
    'server_info' => ['nama' => 'Server pusat', 'alamat' => '192.168.56.1:8000', 'keterangan' => 'Laravel · on-premise'],

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
                'Catat pemilik/pengguna perangkat dan sejak kapan perangkat dipakai.',
                'Jangan instal ulang dulu: serahkan ke petugas siber satuan atas untuk diperiksa, bukti bisa hilang.',
                'Periksa apakah perangkat lain menghubungi domain yang sama.',
                'Bila domain itu ternyata layanan sah (mis. pembaruan aplikasi), tandai false positive.',
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
                'Bila perangkat menjalankan aplikasi sah yang memang memakai nama acak (mis. CDN), tandai false positive.',
            ],
        ],
        'perangkat_asing' => [
            'arti' => 'Ada perangkat baru yang tersambung ke jaringan satuan dan belum terdaftar sebagai perangkat sah. '
                .'Bisa jadi laptop/HP tamu, perangkat pribadi personel, atau perangkat milik pihak luar.',
            'risiko' => 'Perangkat tak dikenal dapat dipakai untuk menyadap lalu lintas jaringan, memindai perangkat lain, '
                .'atau menjadi pintu masuk malware ke jaringan satuan.',
            'langkah' => [
                'Cek fisik: siapa pemilik perangkat dengan MAC dan nama host tersebut.',
                'Bila sah (milik satuan atau sudah diizinkan), klik "Daftarkan perangkat sah".',
                'Bila pemilik tidak jelas, karantina perangkat dan laporkan ke petugas siber satuan atas.',
            ],
        ],
    ],
];
