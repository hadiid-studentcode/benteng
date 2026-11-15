<#
Pasang BENTENG dengan sensor sungguhan dalam sekali jalan.

Menyiapkan server (dependensi, database, token, akun), menyalakan dashboard dan detektor,
lalu memasang skrip BENTENG di sensor OpenWrt lewat SSH dan memeriksa hasilnya.

Syarat: sensor sudah bisa di-ping (lihat docs/PANDUAN-SENSOR.md langkah 2A atau 2B),
PHP 8.4.1+, Composer, Node.js, dan OpenSSH (bawaan Windows 10/11).

Pakai, dari folder repo:
  powershell -ExecutionPolicy Bypass -File benteng-starter\pasang-sensor.ps1 -Jalur vm
  powershell -ExecutionPolicy Bypass -File benteng-starter\pasang-sensor.ps1 -Jalur router

Aman dijalankan ulang: .env, database, dan akun yang sudah ada tidak ditimpa.
#>
param(
    [ValidateSet('vm', 'router')][string]$Jalur = 'vm',
    [string]$Sensor,
    [string]$Server,
    [string]$EmailAnalis = 'analis@satuan.mil'
)

# Perintah luar (php, ssh, scp) diperiksa lewat kode keluarnya. Mode 'Stop' tidak dipakai karena
# Windows PowerShell 5.1 menganggap setiap pesan stderr dari perintah luar sebagai galat.
$ErrorActionPreference = 'Continue'
$Repo = Split-Path -Parent $PSScriptRoot
$App = Join-Path $Repo 'benteng-server'
$Starter = $PSScriptRoot

function Langkah($teks) { Write-Host ''; Write-Host "== $teks" -ForegroundColor Cyan }
function Berhenti($teks) { Write-Host "[GAGAL] $teks" -ForegroundColor Red; exit 1 }

function Baca-Env($kunci) {
    $baris = Select-String -Path "$App\.env" -Pattern "^$kunci=(.*)$" | Select-Object -First 1
    if ($baris) { return $baris.Matches[0].Groups[1].Value.Trim() } else { return '' }
}

function Tulis-Env($kunci, $nilai) {
    $isi = [IO.File]::ReadAllText("$App\.env")
    if ($isi -match "(?m)^$kunci=.*$") {
        $isi = $isi -replace "(?m)^$kunci=.*$", "$kunci=$nilai"
    } else {
        $isi = $isi.TrimEnd() + "`n$kunci=$nilai`n"
    }
    [IO.File]::WriteAllText("$App\.env", $isi, (New-Object Text.UTF8Encoding $false))
}

# --- Alamat sensor dan server --------------------------------------------------------

if ($Jalur -eq 'vm') {
    if (-not $Sensor) { $Sensor = '192.168.56.20' }
    if (-not $Server) { $Server = 'http://192.168.56.1:8000' }
} else {
    if (-not $Sensor) { $Sensor = '192.168.1.1' }
    if (-not $Server) {
        $ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object { $_.IPAddress -like '192.168.1.*' } | Select-Object -First 1
        if (-not $ip) { Berhenti 'Laptop ini tidak punya alamat 192.168.1.x. Colok ke port LAN router, atau isi -Server http://<ip>:8000' }
        $Server = "http://$($ip.IPAddress):8000"
    }
}
Write-Host "Jalur: $Jalur   Sensor: $Sensor   Server: $Server"

# --- 1. Server ------------------------------------------------------------------------

Langkah 'Memeriksa PHP'
$versi = (& php -r 'echo PHP_VERSION;')
if (-not $versi) { Berhenti 'PHP tidak ditemukan.' }
if ([version]($versi -replace '[^0-9.].*$', '') -lt [version]'8.4.1') { Berhenti "PHP $versi terlalu lama. Butuh 8.4.1 atau lebih baru." }
Write-Host "PHP $versi"

Push-Location $App
try {
    Langkah 'Menyiapkan server'
    if (-not (Test-Path 'vendor')) {
        composer install --no-interaction
        if ($LASTEXITCODE -ne 0) { Berhenti 'composer install gagal.' }
    }
    if (-not (Test-Path 'public\build\manifest.json')) {
        npm install; npm run build
        if ($LASTEXITCODE -ne 0) { Berhenti 'npm run build gagal.' }
    }
    if (-not (Test-Path '.env')) {
        Copy-Item '.env.example' '.env'
        php artisan key:generate --no-interaction
    }
    if (-not (Test-Path 'database\database.sqlite')) { New-Item 'database\database.sqlite' -ItemType File | Out-Null }
    php artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { Berhenti 'Migrasi database gagal.' }

    $Token = Baca-Env 'BENTENG_SENSOR_TOKEN'
    if (-not $Token) {
        $Token = (php -r 'echo bin2hex(random_bytes(16));')
        Tulis-Env 'BENTENG_SENSOR_TOKEN' $Token
        Write-Host 'Token sensor baru dibuat di .env'
    }
    if ((Baca-Env 'BENTENG_MODE_DEMO') -eq 'true') {
        Write-Host '[PERINGATAN] Mode demo dimatikan. Data simulasi lama bisa dihapus dengan: php artisan benteng:reset-demo' -ForegroundColor Yellow
    }
    Tulis-Env 'BENTENG_MODE_DEMO' 'false'
    php artisan config:clear | Out-Null

    $jumlahAkun = (php artisan tinker --execute 'echo App\Models\User::count();').Trim()
    if ($jumlahAkun -eq '0') {
        Langkah "Membuat akun analis $EmailAnalis"
        php artisan benteng:pengguna $EmailAnalis --nama='Analis Jaga'
        Write-Host 'Simpan kata sandi di atas, karena tidak bisa ditampilkan lagi.' -ForegroundColor Yellow
    }

    Langkah 'Menyalakan dashboard dan detektor'
    $jalan = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
    if ($jalan) {
        Write-Host 'Port 8000 sudah dipakai; dianggap dashboard sudah berjalan.'
    } else {
        Start-Process powershell -ArgumentList '-NoExit', '-Command', "cd '$App'; php artisan serve --host=0.0.0.0 --port=8000"
        Start-Process powershell -ArgumentList '-NoExit', '-Command', "cd '$App'; php artisan schedule:work"
        Write-Host 'Dua jendela baru dibuka: dashboard dan detektor. Biarkan tetap terbuka.'
        Start-Sleep -Seconds 4
    }
} finally {
    Pop-Location
}

# --- 2. Akses SSH ke sensor -------------------------------------------------------------

Langkah "Menghubungi sensor $Sensor"
if (-not (Test-Connection $Sensor -Count 2 -Quiet)) { Berhenti "Sensor $Sensor tidak membalas ping. Ikuti langkah 2A atau 2B di docs/PANDUAN-SENSOR.md." }

$Kunci = "$env:USERPROFILE\.ssh\id_ed25519"
if (-not (Test-Path $Kunci)) {
    Write-Host 'Membuat kunci SSH laptop. Tekan Enter untuk semua pertanyaan.'
    ssh-keygen -t ed25519 -f $Kunci -q
}
$SshOpsi = @('-o', 'StrictHostKeyChecking=accept-new', '-o', 'ConnectTimeout=8', '-o', 'LogLevel=ERROR')

& ssh @SshOpsi -o BatchMode=yes "root@$Sensor" 'true' 2>$null
if ($LASTEXITCODE -ne 0) {
    ssh-keygen -R $Sensor 2>$null | Out-Null
    Write-Host 'Memasang kunci SSH ke sensor. Masukkan kata sandi root sensor sekali.'
    Get-Content "$Kunci.pub" | & ssh @SshOpsi "root@$Sensor" 'cat >> /etc/dropbear/authorized_keys && chmod 600 /etc/dropbear/authorized_keys'
    & ssh @SshOpsi -o BatchMode=yes "root@$Sensor" 'true' 2>$null
    if ($LASTEXITCODE -ne 0) { Berhenti 'Masuk SSH tanpa kata sandi belum berhasil. Periksa kata sandi root sensor.' }
}
Write-Host 'SSH ke sensor siap.'

# --- 3. Pasang skrip di sensor ---------------------------------------------------------

Langkah 'Menyalin file ke sensor'
# Path relatif dengan garis miring biasa: scp -O di Windows menyimpan path absolut sebagai nama file.
$File = @(
    'pasang.sh', 'sensor/benteng-collect', 'sensor/benteng-collect.init', 'sensor/benteng-agent', 'sensor/benteng-banner',
    'responden/benteng-responder', 'responden/benteng-responder.init', 'responden/benteng-nft.conf',
    'responden/benteng-portal.nft', 'responden/www-benteng/index.html'
)
& ssh @SshOpsi "root@$Sensor" 'rm -rf /tmp/benteng-pasang && mkdir -p /tmp/benteng-pasang'
Push-Location $Starter
& scp -O @SshOpsi @File "root@${Sensor}:/tmp/benteng-pasang/"
$salin = $LASTEXITCODE
Pop-Location
if ($salin -ne 0) { Berhenti 'Gagal menyalin file ke sensor.' }
& ssh @SshOpsi "root@$Sensor" 'test -f /tmp/benteng-pasang/pasang.sh && test -f /tmp/benteng-pasang/index.html'
if ($LASTEXITCODE -ne 0) { Berhenti 'File tidak lengkap di sensor setelah disalin.' }

Langkah 'Memasang BENTENG di sensor'
$mode = if ($Jalur -eq 'vm') { 'vm' } else { 'router' }
& ssh @SshOpsi "root@$Sensor" "tr -d '\r' < /tmp/benteng-pasang/pasang.sh > /tmp/benteng-pasang/p.sh && sh /tmp/benteng-pasang/p.sh '$Token' '$Server' $mode"
$hasil = $LASTEXITCODE

Write-Host ''
if ($hasil -eq 0) {
    Write-Host 'Selesai. BENTENG terpasang.' -ForegroundColor Green
    Write-Host '1. Buka http://localhost:8000 dan login sebagai analis.'
    Write-Host '2. Dalam 1-2 menit, menu Sensor menampilkan BTG-02 Online.'
    Write-Host '3. Jalankan alat uji di perangkat uji: python3 benteng-trafik-uji.py --menit 6'
    Write-Host '   (lihat langkah 6 dan 7 di docs/PANDUAN-SENSOR.md)'
} else {
    Write-Host 'Sensor terpasang, tetapi ada pemeriksaan yang gagal. Lihat pesan [GAGAL] di atas' -ForegroundColor Red
    Write-Host 'dan tabel "Masalah yang sering muncul" di docs/PANDUAN-SENSOR.md.'
    if ($Jalur -eq 'vm') { Write-Host 'Penyebab paling sering: firewall Windows memblokir php.exe untuk jaringan Public.' }
    exit 1
}
