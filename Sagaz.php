<?php

// Cores ANSI
$branco   = "\e[97m";
$preto    = "\e[30m\e[1m";
$vermelho = "\e[91m";
$verde    = "\e[92m";
$amarelo  = "\e[93m";
$azul     = "\e[94m";
$ciano    = "\e[96m";
$cln      = "\e[0m";
$bold     = "\e[1m";

function sagaz_banner() {
    echo "\e[97m
  SAGAZ AntiCheat Scanner
  discord.gg/sagazofc   |   2025-2026

   ███████╗ █████╗  ██████╗  █████╗ ███████╗
   ██╔════╝██╔══██╗██╔════╝ ██╔══██╗██╔════╝
   ███████╗███████║██║  ███╗███████║███████╗
   ╚════██║██╔══██║██║   ██║██╔══██║╚════██║
   ███████║██║  ██║╚██████╔╝██║  ██║███████║
   ╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═╝╚══════╝

  \e[96mCoded & Maintained by SAGAZ Team\e[0m
  \n";
}

function verificar_adb() {
    global $vermelho, $verde, $cln, $bold;
    $devices = shell_exec('adb devices 2>&1');
    if (strpos($devices, 'device') === false || strpos($devices, 'unauthorized') !== false) {
        echo $bold . $vermelho . "  ✗ Nenhum dispositivo autorizado via ADB!\n" . $cln;
        echo "  Conecte via USB ou faça pareamento Wi-Fi.\n\n";
        exit;
    }
    echo $bold . $verde . "  ✓ Dispositivo ADB conectado\n" . $cln;
}

function checar_root_simples() {
    global $vermelho, $verde, $amarelo, $cln, $bold, $ciano;

    echo "\n" . $bold . $ciano . "  SAGAZ - DETECÇÃO RÁPIDA DE ROOT/BYPASS\n";
    echo "  ========================================\n\n" . $cln;

    // 1. Bootloader / Verified Boot
    $boot = trim(shell_exec('adb shell getprop ro.boot.verifiedbootstate 2>/dev/null'));
    if ($boot === 'orange' || $boot === 'yellow') {
        echo $vermelho . "  ✗ Bootloader desbloqueado / modificado ($boot)\n" . $cln;
    } else if ($boot === 'green') {
        echo $verde . "  ✓ Boot state: GREEN\n" . $cln;
    }

    // 2. SELinux
    $selinux = trim(shell_exec('adb shell getenforce 2>/dev/null'));
    if ($selinux === 'Permissive') {
        echo $vermelho . "  ✗ SELinux PERMISSIVE (root/bypass detectado)\n" . $cln;
    } else if ($selinux === 'Enforcing') {
        echo $verde . "  ✓ SELinux ENFORCING\n" . $cln;
    }

    // 3. Propriedades críticas
    $props_sus = ['ro.debuggable' => '1', 'ro.secure' => '0', 'ro.boot.veritymode' => 'disabled'];
    foreach ($props_sus as $p => $v) {
        $val = trim(shell_exec("adb shell getprop $p 2>/dev/null"));
        if ($val === $v) {
            echo $vermelho . "  ✗ $p = $v → Bypass/Root detectado\n" . $cln;
        }
    }

    // 4. Magisk / KernelSU / APatch vestígios rápidos
    $magisk_check = shell_exec('adb shell "test -d /data/adb/magisk && echo 1" 2>/dev/null');
    $ksu_check    = shell_exec('adb shell "test -d /data/adb/ksu    && echo 1" 2>/dev/null');
    $apatch_check = shell_exec('adb shell "test -d /data/adb/ap     && echo 1" 2>/dev/null');

    if (trim($magisk_check)) echo $vermelho . "  ✗ Magisk detectado\n" . $cln;
    if (trim($ksu_check))    echo $vermelho . "  ✗ KernelSU detectado\n" . $cln;
    if (trim($apatch_check)) echo $vermelho . "  ✗ APatch detectado\n" . $cln;

    // 5. Apps de bypass comuns
    $apps_sus = ['trickystore', 'shamiko', 'magisk', 'lsposed', 'shizuku'];
    $pkgs = shell_exec('adb shell pm list packages 2>/dev/null');
    foreach ($apps_sus as $app) {
        if (strpos($pkgs, $app) !== false) {
            echo $amarelo . "  ⚠ App suspeito: $app\n" . $cln;
        }
    }

    echo "\n";
}

function checar_freefire($pacote, $nome) {
    global $vermelho, $verde, $amarelo, $cln, $bold, $ciano;

    echo $bold . $ciano . "  → Analisando $nome ($pacote)\n\n" . $cln;

    // MReplays - passador de replay
    $mreplays = "/sdcard/Android/data/$pacote/files/MReplays";
    $bin_check = shell_exec("adb shell 'ls $mreplays/*.bin 2>/dev/null | head -1'");
    if (trim($bin_check)) {
        $recent = shell_exec("adb shell 'stat -c %Y $mreplays/*.bin 2>/dev/null | sort -nr | head -1'");
        $pasta_ts = shell_exec("adb shell 'stat -c %Y $mreplays 2>/dev/null'");
        
        if (trim($recent) && trim($pasta_ts)) {
            $diff = (int)trim($pasta_ts) - (int)trim($recent);
            if ($diff > 300) { // pasta modificada muito antes do último .bin
                echo $vermelho . "  ✗ Suspeita GRAVE: Pasta MReplays manipulada (passador de replay)\n" . $cln;
            }
        }
    }

    // Shaders recentes (wallhack / holograma)
    $shader_dir = "/sdcard/Android/data/$pacote/files/contentcache/Optional/android/gameassetbundles";
    $shader_check = shell_exec("adb shell 'find $shader_dir -type f -mtime -1 2>/dev/null | head -1'");
    if (trim($shader_check)) {
        echo $vermelho . "  ✗ Arquivo shader modificado nas últimas 24h → Possível wallhack\n" . $cln;
    }

    // OptionalAvatarRes (skins / visual mods)
    $opt_dir = "/sdcard/Android/data/$pacote/files/contentcache/Optional/android/optionalavatarres";
    $opt_ts = shell_exec("adb shell 'stat -c %Y $opt_dir 2>/dev/null'");
    if (trim($opt_ts)) {
        $agora = time();
        if ($agora - (int)trim($opt_ts) < 7200) { // < 2 horas
            echo $amarelo . "  ⚠ Pasta optionalavatarres modificada recentemente → Suspeito\n" . $cln;
        }
    }

    // OBB deletada ou recente
    $obb = "/sdcard/Android/obb/$pacote";
    $obb_check = shell_exec("adb shell 'ls $obb/*.obb 2>/dev/null'");
    if (!trim($obb_check)) {
        echo $vermelho . "  ✗ OBB principal ausente → Alta suspeita de modificação\n" . $cln;
    }

    echo "\n";
}

system("clear");
sagaz_banner();

verificar_adb();

echo $bold . $azul . "  [1] Free Fire Normal    [2] Free Fire MAX    [S] Sair\n\n" . $cln;

echo $ciano . "  → Escolha: " . $verde;
$op = trim(fgets(STDIN));

if ($op === '1') {
    checar_root_simples();
    checar_freefire("com.dts.freefireth", "Free Fire");
} elseif ($op === '2') {
    checar_root_simples();
    checar_freefire("com.dts.freefiremax", "Free Fire MAX");
} elseif (strtoupper($op) === 'S') {
    echo "\n  SAGAZ encerrado. Boa caçada.\n\n";
    exit;
} else {
    echo $vermelho . "  Opção inválida.\n" . $cln;
}

echo "\n  Pressione Enter para sair...";
fgets(STDIN);
