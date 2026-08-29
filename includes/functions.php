<?php
/**
 * EBAUB CSE Gallery - Helper Functions
 * Image compression (WebP), auth guard, sanitizing.
 */

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function require_admin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * THE PERFORMANCE LOGIC (as planned in the blueprint):
 * 1. Validate type + size
 * 2. If image  -> resize (max 1600px) + convert to WebP (~80% smaller)
 * 3. If video  -> save as-is (MP4 only, max 25MB)
 * Returns: ['ok'=>bool, 'path'=>..., 'type'=>'image'|'video', 'error'=>...]
 */
function process_upload(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (error code ' . $file['error'] . ')'];
    }

    $mime = mime_content_type($file['tmp_name']);
    $sizeMB = $file['size'] / (1024 * 1024);

    $isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/webp']);
    $isVideo = ($mime === 'video/mp4');

    if (!$isImage && !$isVideo) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WebP images or MP4 videos allowed. Got: ' . $mime];
    }
    if ($isImage && $sizeMB > MAX_IMAGE_MB) {
        return ['ok' => false, 'error' => 'Image too large (max ' . MAX_IMAGE_MB . 'MB)'];
    }
    if ($isVideo && $sizeMB > MAX_VIDEO_MB) {
        return ['ok' => false, 'error' => 'Video too large (max ' . MAX_VIDEO_MB . 'MB)'];
    }

    $unique = date('Ymd_His') . '_' . bin2hex(random_bytes(4));

    if ($isVideo) {
        $filename = $unique . '.mp4';
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
        return ['ok' => true, 'path' => UPLOAD_URL . $filename, 'type' => 'video'];
    }

    /* ---- Image: compress + convert to WebP ---- */

    /* SAFETY FALLBACK: if the GD extension is disabled on this server,
       don't crash — just save the original image without compression. */
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        $ext = match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
            'image/webp' => '.webp',
        };
        $filename = $unique . $ext;
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
        return ['ok' => true, 'path' => UPLOAD_URL . $filename, 'type' => 'image'];
    }

    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file['tmp_name']) : null,
    };
    if (!$src) {
        /* Could not decode (e.g. webp read not supported) -> save as-is */
        $ext = $mime === 'image/webp' ? '.webp' : ($mime === 'image/png' ? '.png' : '.jpg');
        $filename = $unique . $ext;
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
        return ['ok' => true, 'path' => UPLOAD_URL . $filename, 'type' => 'image'];
    }

    $w = imagesx($src); $h = imagesy($src);
    $maxDim = 1600;
    if ($w > $maxDim || $h > $maxDim) {
        $ratio = min($maxDim / $w, $maxDim / $h);
        $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $resized;
    }

    $filename = $unique . '.webp';
    imagewebp($src, UPLOAD_DIR . $filename, 80); // quality 80 = great size/quality balance
    imagedestroy($src);

    return ['ok' => true, 'path' => UPLOAD_URL . $filename, 'type' => 'image'];
}

/**
 * Homepage slider: 3 fixed slots (uploads/hero/slide_1..3).
 * Replaces the slot file, compresses to WebP when GD is available.
 */
function process_hero_upload(array $file, int $slot): array {
    $slot = max(1, min(3, $slot));
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (error code ' . $file['error'] . ')'];
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        return ['ok' => false, 'error' => 'Slider accepts images only (JPG, PNG, WebP)'];
    }
    if ($file['size'] / (1024 * 1024) > MAX_IMAGE_MB) {
        return ['ok' => false, 'error' => 'Image too large (max ' . MAX_IMAGE_MB . 'MB)'];
    }

    $dir = __DIR__ . '/../uploads/hero/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    foreach (glob($dir . 'slide_' . $slot . '.*') as $old) @unlink($old);

    /* Fallback: no GD -> save original */
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        $ext = $mime === 'image/png' ? '.png' : ($mime === 'image/webp' ? '.webp' : '.jpg');
        move_uploaded_file($file['tmp_name'], $dir . 'slide_' . $slot . $ext);
        return ['ok' => true];
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : null,
    };
    if (!$src) {
        $ext = $mime === 'image/png' ? '.png' : ($mime === 'image/webp' ? '.webp' : '.jpg');
        move_uploaded_file($file['tmp_name'], $dir . 'slide_' . $slot . $ext);
        return ['ok' => true];
    }

    $w = imagesx($src); $h = imagesy($src);
    $maxW = 1920;
    if ($w > $maxW) {
        $nh = (int)($h * $maxW / $w);
        $r = imagecreatetruecolor($maxW, $nh);
        imagecopyresampled($r, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
        imagedestroy($src);
        $src = $r;
    }
    imagewebp($src, $dir . 'slide_' . $slot . '.webp', 80);
    imagedestroy($src);
    return ['ok' => true];
}

/**
 * Save an uploaded image compressed (WebP when GD available) into uploads/$subdir/.
 * Returns ['ok'=>bool, 'path'=>relative path, 'error'=>...].
 */
function process_simple_image(array $file, string $subdir, int $maxDim = 1400): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (error code ' . $file['error'] . ')'];
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG or WebP images allowed'];
    }
    if ($file['size'] / (1024 * 1024) > MAX_IMAGE_MB) {
        return ['ok' => false, 'error' => 'Image too large (max ' . MAX_IMAGE_MB . 'MB)'];
    }
    $dir = __DIR__ . '/../uploads/' . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $unique = date('Ymd_His') . '_' . bin2hex(random_bytes(4));

    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        $ext = $mime === 'image/png' ? '.png' : ($mime === 'image/webp' ? '.webp' : '.jpg');
        move_uploaded_file($file['tmp_name'], $dir . $unique . $ext);
        return ['ok' => true, 'path' => 'uploads/' . $subdir . '/' . $unique . $ext];
    }
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : null,
    };
    if (!$src) {
        $ext = $mime === 'image/png' ? '.png' : ($mime === 'image/webp' ? '.webp' : '.jpg');
        move_uploaded_file($file['tmp_name'], $dir . $unique . $ext);
        return ['ok' => true, 'path' => 'uploads/' . $subdir . '/' . $unique . $ext];
    }
    $w = imagesx($src); $h = imagesy($src);
    if ($w > $maxDim || $h > $maxDim) {
        $ratio = min($maxDim / $w, $maxDim / $h);
        $r = imagecreatetruecolor((int)($w * $ratio), (int)($h * $ratio));
        imagecopyresampled($r, $src, 0, 0, 0, 0, (int)($w * $ratio), (int)($h * $ratio), $w, $h);
        imagedestroy($src);
        $src = $r;
    }
    imagewebp($src, $dir . $unique . '.webp', 80);
    imagedestroy($src);
    return ['ok' => true, 'path' => 'uploads/' . $subdir . '/' . $unique . '.webp'];
}

/**
 * Parse an event's custom_roles field.
 * Returns array of [ ['label' => string, 'options' => string[]], ... ]
 * Fully backward-compatible with legacy comma-separated strings.
 */
function get_event_custom_fields($event): array {
    $raw = $event['custom_roles'] ?? '';
    if (empty($raw)) return [];

    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded)) {
        $valid = [];
        foreach ($decoded as $item) {
            if (!empty($item['label']) && !empty($item['options']) && is_array($item['options'])) {
                $valid[] = [
                    'label' => trim($item['label']),
                    'options' => array_values(array_filter(array_map('trim', $item['options'])))
                ];
            }
        }
        if ($valid) return $valid;
    }

    /* Fallback: legacy comma-separated string (e.g. "Football, Ludu, Sing") */
    $opts = array_values(array_filter(array_map('trim', explode(',', (string)$raw))));
    if ($opts) {
        return [
            [
                'label' => 'Select Role / Activity',
                'options' => $opts
            ]
        ];
    }
    return [];
}

/**
 * Parse a registration's event_role field.
 * Returns associative array: [ 'Sport' => 'Cricket', 'Team' => 'Team A', ... ]
 * Backward-compatible with plain text roles.
 */
function parse_registration_roles($eventRole): array {
    if (empty($eventRole)) return [];
    $decoded = json_decode($eventRole, true);
    if (is_array($decoded) && !empty($decoded)) {
        return $decoded;
    }
    return ['Role' => (string)$eventRole];
}

/**
 * Return a clean formatted summary string of roles, e.g. "Cricket · Team A"
 */
function get_registration_summary($eventRole): string {
    $roles = parse_registration_roles($eventRole);
    if (!$roles) return '';
    return implode(' · ', array_values($roles));
}

