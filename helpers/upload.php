<?php

declare(strict_types=1);

function validate_image_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded.'];
    }

    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return ['success' => false, 'message' => 'Image must be smaller than 5MB.'];
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    return ['success' => true, 'mime' => $mimeType];
}

function compress_and_store_image(array $file, string $targetDirectory): array
{
    ensure_directory($targetDirectory);

    $validation = validate_image_upload($file);
    if (!$validation['success']) {
        return $validation;
    }

    $extension = match ($validation['mime']) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    $filename = uniqid('proof_', true) . '.' . $extension;
    $destination = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;

    $image = match ($validation['mime']) {
        'image/png' => imagecreatefrompng($file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file['tmp_name']) : imagecreatefrompng($file['tmp_name']),
        default => imagecreatefromjpeg($file['tmp_name']),
    };

    if (!$image) {
        return ['success' => false, 'message' => 'Unable to process image.'];
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 1280;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = (int) round(($maxWidth / $width) * $height);
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($validation['mime'] === 'image/png' || $validation['mime'] === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    } else {
        $resized = $image;
    }

    switch ($validation['mime']) {
        case 'image/png':
            imagepng($resized, $destination, 7);
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                imagewebp($resized, $destination, 80);
            } else {
                imagejpeg($resized, $destination, 80);
            }
            break;
        default:
            imagejpeg($resized, $destination, 82);
            break;
    }

    imagedestroy($image);
    if ($resized !== $image) {
        imagedestroy($resized);
    }

    return [
        'success' => true,
        'file_name' => $filename,
        'relative_path' => str_replace(realpath(__DIR__ . '/../') ?: '', '', realpath($destination) ?: $destination),
        'absolute_path' => $destination,
    ];
}
