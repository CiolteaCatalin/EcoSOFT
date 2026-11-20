<?php
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(419); exit('Invalid CSRF token.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function money(float $value, string $currency='RON'): string { return number_format($value, 2, ',', '.') . ' ' . $currency; }
function offer_number(PDO $pdo): array {
    $year = date('Y');
    $stmt = $pdo->prepare('SELECT MAX(sequence_no) FROM offers WHERE offer_year=?');
    $stmt->execute([$year]);
    $next = ((int)$stmt->fetchColumn()) + 1;
    return ['number' => sprintf('OF-%s-%04d', $year, $next), 'year' => (int)$year, 'seq' => $next];
}
function upload_product_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed.');
    if ($file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Imaginea este prea mare. Max 5MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Format imagine invalid.');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $dir = __DIR__ . '/../uploads/products';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) throw new RuntimeException('Nu s-a putut salva imaginea.');
    return 'uploads/products/' . $name;
}
