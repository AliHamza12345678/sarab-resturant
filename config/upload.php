<?php
/**
 * Central image upload handler.
 *
 * Security model (defense in depth):
 *   1. Reject anything that isn't a real image, verified server-side via
 *      finfo (actual file content) AND getimagesize() — never trust the
 *      client-supplied MIME type or the file extension alone.
 *   2. Whitelist only jpg/jpeg/png/webp.
 *   3. Enforce a max file size.
 *   4. Generate a random, unpredictable filename — never use the
 *      original filename (avoids path traversal / overwrite attacks).
 *   5. Re-encode the image through GD before saving. This throws away
 *      the original file bytes entirely and rebuilds a clean image from
 *      the decoded pixel data, which strips EXIF metadata and defeats
 *      "polyglot" files (e.g. a GIF/PHP or ZIP/JPEG hybrid) that some
 *      attacks rely on to smuggle executable code inside an "image".
 *   6. Uploaded files live under /uploads with a .htaccess that disables
 *      script execution in that folder, so even if something slipped
 *      through, the server would never execute it as code.
 */

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5MB
const UPLOAD_ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const UPLOAD_MAX_DIMENSION = 2000; // px, longest side after resize

/**
 * Handle a single uploaded image file.
 *
 * @param string $fieldName   The $_FILES key, e.g. 'image'
 * @param string $subdir      Subfolder under /uploads, e.g. 'menu', 'categories', 'settings', 'avatars'
 * @param string|null $oldRelativePath Previous stored path (e.g. 'uploads/menu/xxx.jpg') to delete on success
 * @return array{success:bool, path:?string, error:?string}
 */
function handle_image_upload(string $fieldName, string $subdir, ?string $oldRelativePath = null): array
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'path' => null, 'error' => 'no_file']; // caller decides if that's OK (e.g. edit without changing image)
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE   => 'The file is larger than the server allows.',
            UPLOAD_ERR_FORM_SIZE  => 'The file is larger than allowed.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server upload error. Please try again later.',
            UPLOAD_ERR_CANT_WRITE => 'Server upload error. Please try again later.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server configuration.',
        ];
        return ['success' => false, 'path' => null, 'error' => $map[$file['error']] ?? 'Upload failed.'];
    }

    if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        return ['success' => false, 'path' => null, 'error' => 'File must be under ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . 'MB.'];
    }

    // Verify the REAL content type from the file bytes (never trust $_FILES[...]['type'], the client sets that)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($realMime, UPLOAD_ALLOWED_TYPES)) {
        return ['success' => false, 'path' => null, 'error' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    // Verify it's genuinely a decodable image (defeats disguised non-image files)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'path' => null, 'error' => 'The file is not a valid image.'];
    }
    [$width, $height] = $imageInfo;
    if ($width < 1 || $height < 1) {
        return ['success' => false, 'path' => null, 'error' => 'The file is not a valid image.'];
    }

    // Decode via GD according to the REAL detected type
    switch ($realMime) {
        case 'image/jpeg':
            $srcImage = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $srcImage = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $srcImage = @imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            $srcImage = false;
    }
    if ($srcImage === false) {
        return ['success' => false, 'path' => null, 'error' => 'Could not process this image. Please try a different file.'];
    }

    // Downscale if larger than our max dimension (keeps storage/bandwidth sane)
    if ($width > UPLOAD_MAX_DIMENSION || $height > UPLOAD_MAX_DIMENSION) {
        $scale = UPLOAD_MAX_DIMENSION / max($width, $height);
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($realMime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($srcImage);
        $srcImage = $resized;
    }

    $ext = UPLOAD_ALLOWED_TYPES[$realMime];
    $filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;

    $uploadRoot = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($uploadRoot)) {
        mkdir($uploadRoot, 0755, true);
        ensure_uploads_htaccess(dirname(__DIR__) . '/uploads');
    }

    $destPath = $uploadRoot . '/' . $filename;
    $saved = match ($realMime) {
        'image/jpeg' => imagejpeg($srcImage, $destPath, 85),
        'image/png'  => imagepng($srcImage, $destPath, 6),
        'image/webp' => imagewebp($srcImage, $destPath, 85),
        default      => false,
    };
    imagedestroy($srcImage);

    if (!$saved) {
        return ['success' => false, 'path' => null, 'error' => 'Could not save the uploaded image.'];
    }

    $relativePath = 'uploads/' . $subdir . '/' . $filename;

    // Clean up the old file, if any, now that the new one is safely saved
    if (!empty($oldRelativePath)) {
        delete_uploaded_image($oldRelativePath);
    }

    return ['success' => true, 'path' => $relativePath, 'error' => null];
}

/**
 * Delete a previously uploaded image (best-effort — never fatal if missing).
 * Only deletes files that live inside our own /uploads folder, to avoid any
 * possibility of a stored path being abused to delete arbitrary files.
 */
function delete_uploaded_image(?string $relativePath): void
{
    if (empty($relativePath)) return;
    $relativePath = ltrim($relativePath, '/');
    if (strpos($relativePath, 'uploads/') !== 0 || strpos($relativePath, '..') !== false) {
        return; // not one of ours / suspicious path — ignore
    }
    $fullPath = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

/**
 * Make sure /uploads has a .htaccess that disables script execution,
 * so even a successfully-uploaded file can never run as PHP.
 */
function ensure_uploads_htaccess(string $uploadsRoot): void
{
    $htaccess = $uploadsRoot . '/.htaccess';
    if (!is_file($htaccess)) {
        if (!is_dir($uploadsRoot)) mkdir($uploadsRoot, 0755, true);
        file_put_contents($htaccess, "# Uploaded files are never executable, no matter what extension they get.\n"
            . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phar|pl|py|cgi|asp|aspx|sh|exe)$\">\n"
            . "  Require all denied\n"
            . "</FilesMatch>\n"
            . "php_flag engine off\n"
            . "Options -Indexes -ExecCGI\n");
    }
}
