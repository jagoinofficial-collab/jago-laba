<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
$_SESSION = [];
check(Workspace::allowed() === [], 'tanpa login tidak ada ruang kerja');
$_SESSION['user_id'] = 'AUTHV2-test-owner';
$_SESSION['role'] = 'ROLE-OWNER_SUPER_ADMIN';
check(Workspace::can(Workspace::KONVEKSI) && Workspace::can(Workspace::MARKETPLACE), 'owner memiliki kedua pilihan');
check(Workspace::active() === null, 'login baru tidak memilih otomatis');
Workspace::select(Workspace::MARKETPLACE);
check(Workspace::active() === Workspace::MARKETPLACE, 'pemilihan marketplace tersimpan dalam sesi');
$_SESSION['role'] = 'ROLE-USER';
check(!Workspace::can(Workspace::MARKETPLACE) && Workspace::active() === null, 'staf tidak dapat memakai marketplace aktif lama');
$denied = false;
try { Workspace::select(Workspace::MARKETPLACE); } catch (RuntimeException $e) { $denied = true; }
check($denied, 'pemilihan ruang kerja tidak berizin ditolak');
Workspace::select(Workspace::KONVEKSI);
check(Workspace::active() === Workspace::KONVEKSI, 'staf dapat memilih konveksi');
$token = Workspace::csrf();
$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
Workspace::verifyCsrf();
check(true, 'token CSRF valid diterima');
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid';
$denied = false;
try { Workspace::verifyCsrf(); } catch (RuntimeException $e) { $denied = true; }
check($denied, 'token CSRF invalid ditolak');
