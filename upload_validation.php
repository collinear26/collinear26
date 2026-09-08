<?php
// upload_validation.php — Shared file-upload content validation. Hindi sapat
// ang extension check lang (madaling palitan ng attacker ang filename) o ang
// browser-provided MIME type (nasa client side, hindi mapagkakatiwalaan) —
// dito, ang AKTWAL na laman ng file ang sinusuri gamit ang fileinfo extension
// bago tanggapin bilang valid.

// Default maximum upload size para sa mga document attachments (hindi
// kasama ang messages, na may sarili nang 10MB na limit). 15MB ay sapat na
// para sa mga karaniwang scanned na dokumento (PDF/DOCX/imahe) nang hindi
// masyadong mataas kumpara sa layunin ng system.
if (!defined('MAX_DOCUMENT_UPLOAD_BYTES')) {
    define('MAX_DOCUMENT_UPLOAD_BYTES', 15 * 1024 * 1024);
}

/**
 * Tinitignan ang AKTWAL na content-type ng file (hindi extension, hindi
 * browser-supplied MIME) gamit ang fileinfo, at kino-compare laban sa
 * inaasahang mime types ng ibinigay na extension.
 *
 * @param string $tmp_path Path ng uploaded temp file (mula sa $_FILES[...]['tmp_name'])
 * @param string $extension Lowercase extension na dapat i-verify (hal. 'pdf')
 * @return bool True kung tugma ang aktwal na content sa inaasahang uri
 */
function validate_uploaded_file_content($tmp_path, $extension) {
    // Allow-list ng mga tunay na MIME signature na dapat lumabas kapag
    // binasa ng fileinfo ang AKTWAL na bytes ng file (hindi ang extension).
    $allowed_mimes = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        // Ang DOCX ay isang ZIP archive sa ilalim ng kaputol — normal lang na
        // makita itong "application/zip" o "application/octet-stream" sa
        // ilang fileinfo magic database, kaya isama ang mga posibleng resulta.
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];

    if (!isset($allowed_mimes[$extension]) || !is_file($tmp_path)) {
        return false;
    }

    if (!function_exists('finfo_open')) {
        // Kung wala talagang fileinfo extension sa PHP build na ito (sobrang
        // bihira), huwag na lang i-block ang legit na uploads — babalik sa
        // extension-only na check na dating ginagamit.
        return true;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = $finfo ? finfo_file($finfo, $tmp_path) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($detected === false) {
        return false;
    }

    // Karagdagang check para sa DOCX/DOC: kahit "application/zip" o
    // "application/octet-stream" ang nakita, tignan pa rin kung may
    // kasunod na executable signature (hal. "MZ" para sa .exe) sa unang
    // bytes — kahit tama ang extension, tanggihan kung ito pala'y isang
    // palitan-lang-ng-pangalan na executable.
    $handle = fopen($tmp_path, 'rb');
    if ($handle) {
        $first_bytes = fread($handle, 4);
        fclose($handle);
        if ($first_bytes !== false && (str_starts_with($first_bytes, 'MZ') || str_starts_with($first_bytes, "\x7fELF"))) {
            return false;
        }
    }

    return in_array($detected, $allowed_mimes[$extension], true);
}

/**
 * I-validate ang isang uploaded file nang buo: extension whitelist, AKTWAL
 * na content/MIME, at file size — ibinabalik ang error message (string) kung
 * may problema, o null kung valid ang lahat.
 *
 * @param array $file Isang entry mula sa $_FILES (hal. $_FILES['document_file'])
 * @param array $allowed_extensions Listahan ng pinapayagang extensions
 * @param int $max_bytes Maximum na pinapayagang laki, sa bytes
 * @return string|null
 */
function validate_document_upload($file, $allowed_extensions, $max_bytes = MAX_DOCUMENT_UPLOAD_BYTES) {
    $original_name = basename($file['name']);
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed_extensions, true)) {
        return 'Invalid file type. Only ' . strtoupper(implode(', ', $allowed_extensions)) . ' files are allowed.';
    }

    if ($file['size'] > $max_bytes) {
        return 'File is too large. Maximum size is ' . round($max_bytes / (1024 * 1024)) . 'MB.';
    }

    if (!validate_uploaded_file_content($file['tmp_name'], $extension)) {
        return 'This file\'s actual content does not match its extension (' . strtoupper($extension) . '). Please upload a genuine ' . strtoupper($extension) . ' file.';
    }

    return null;
}
