<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Validate uploaded file against size, MIME type, and content.
 * Returns true if the file is considered safe.
 */
function isUploadSafe($file): bool {
    // 1. Handle Multiple File Uploads (recursive check)
    if (isset($file['name']) && is_array($file['name'])) {
        foreach ($file['name'] as $key => $name) {
            $singleFile = [
                'name'     => $file['name'][$key],
                'type'     => $file['type'][$key],
                'tmp_name' => $file['tmp_name'][$key],
                'error'    => $file['error'][$key],
                'size'     => $file['size'][$key]
            ];
            if (!isUploadSafe($singleFile)) {
                return false;
            }
        }
        return true;
    }

    // 2. Ensure required keys exist for single file
    if (!isset($file['error'], $file['size'], $file['tmp_name'], $file['name'])) {
        return false;
    }

    // 3. Skip check if no file was uploaded (e.g. changing password but not image)
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return true;
    }

    // 4. Check for other upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // 5. Enforce max size
    $maxSize = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }

    // 6. Determine MIME type safely
    if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed = defined('ALLOWED_MIME_TYPES') ? ALLOWED_MIME_TYPES : ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            return false;
        }

        // 7. Basic content check – reject PHP code
        $firstBytes = file_get_contents($file['tmp_name'], false, null, 0, 4096);
        if (preg_match('/<\?php/i', $firstBytes)) {
            return false;
        }

        // 8. Optional: scan for known malicious patterns
        if (function_exists('detectMaliciousInput')) {
            if (detectMaliciousInput($firstBytes)) {
                return false;
            }
        }
    }

    return true;
}
?>
