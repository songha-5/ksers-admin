<?php

function ksers_submission_types() {
    return [
        'trainee_document' => [
            'title_ko' => '트레이니 증빙 서류',
            'title_en' => 'Trainee supporting document',
            'extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
            'max_bytes' => 20 * 1024 * 1024,
            'video' => false,
        ],
        'abstract_video_1' => [
            'title_ko' => '영상 발표 (Video Presentation)',
            'title_en' => 'Video Presentation',
            'extensions' => ['mp4', 'mov', 'm4v', 'avi', 'wmv', 'mkv', 'webm', 'mpeg', 'mpg'],
            'mimes' => [
                'video/mp4', 'video/quicktime', 'video/x-m4v',
                'video/x-msvideo', 'video/avi', 'video/msvideo',
                'video/x-ms-wmv', 'video/x-matroska', 'video/webm', 'video/mpeg',
                'application/octet-stream',
            ],
            'max_bytes' => 2 * 1024 * 1024 * 1024,
            'video' => true,
        ],
        'abstract_video_2' => [
            'title_ko' => '멘토-멘티 영상 (Mentor-Mentee Video)',
            'title_en' => 'Mentor-Mentee Video',
            'extensions' => ['mp4', 'mov', 'm4v', 'avi', 'wmv', 'mkv', 'webm', 'mpeg', 'mpg'],
            'mimes' => [
                'video/mp4', 'video/quicktime', 'video/x-m4v',
                'video/x-msvideo', 'video/avi', 'video/msvideo',
                'video/x-ms-wmv', 'video/x-matroska', 'video/webm', 'video/mpeg',
                'application/octet-stream',
            ],
            'max_bytes' => 2 * 1024 * 1024 * 1024,
            'video' => true,
        ],
        'supporting_document' => [
            'title_ko' => '증빙서류',
            'title_en' => 'Supporting Document',
            'extensions' => ['pdf', 'jpg', 'jpeg'],
            'mimes' => [
                'application/pdf',
                'image/jpeg',
                'application/octet-stream',
            ],
            'max_bytes' => 5 * 1024 * 1024,
            'video' => false,
        ],
        'qualification_certificate' => [
            'title_ko' => '자격 증빙서류',
            'title_en' => 'Qualification Certificate',
            'extensions' => ['pdf', 'doc', 'docx', 'hwp', 'hwpx', 'jpg', 'jpeg', 'png'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/x-hwp',
                'application/haansofthwp',
                'application/vnd.hancom.hwp',
                'application/vnd.hancom.hwpx',
                'application/hwp+zip',
                'image/jpeg',
                'image/png',
                'application/octet-stream',
            ],
            'max_bytes' => 20 * 1024 * 1024,
            'video' => false,
        ],
        'master_class_cv' => [
            'title_ko' => '마스터클래스 CV (Hands-On)',
            'title_en' => 'Master Class CV (Hands-On)',
            'extensions' => ['pdf', 'doc', 'docx', 'hwp', 'hwpx', 'jpg', 'jpeg', 'png'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/x-hwp',
                'application/haansofthwp',
                'application/vnd.hancom.hwp',
                'application/vnd.hancom.hwpx',
                'application/hwp+zip',
                'image/jpeg',
                'image/png',
                'application/octet-stream',
            ],
            'max_bytes' => 20 * 1024 * 1024,
            'video' => false,
        ],
    ];
}

function ksers_submission_type($kind) {
    $types = ksers_submission_types();
    return isset($types[$kind]) ? $types[$kind] : null;
}

function ksers_submission_material_kinds() {
    return ['abstract_video_1', 'abstract_video_2'];
}

function ksers_video_kind_for_presentation($presentationType) {
    $presentationType = (string)$presentationType;
    if (in_array($presentationType, ['mentor', 'mentee', 'abstract_video_2'], true)) {
        return 'abstract_video_2';
    }
    if (in_array($presentationType, ['video', 'abstract_video_1'], true)) {
        return 'abstract_video_1';
    }
    return '';
}

function ksers_abstract_video_link_ensure($pdo, $fileSeq, $abstractSeq, $year) {
    $fileSeq = (int)$fileSeq;
    $abstractSeq = (int)$abstractSeq;
    $year = (int)$year;
    if ($fileSeq < 1 || $abstractSeq < 1 || $year < 1) {
        return false;
    }
    try {
        $existing = $pdo->prepare("SELECT seq FROM abstract_video_link WHERE file_seq = ?");
        $existing->execute([$fileSeq]);
        if ($existing->fetchColumn()) {
            return false;
        }
        $insert = $pdo->prepare(
            "INSERT INTO abstract_video_link (token, file_seq, abstract_seq, year, reg_date) VALUES (?, ?, ?, ?, NOW())"
        );
        $insert->execute([bin2hex(random_bytes(32)), $fileSeq, $abstractSeq, $year]);
        return true;
    } catch (Exception $e) {
        error_log('abstract video link create failed');
        return false;
    }
}

function ksers_submission_dir($kind) {
    $config = ksers_submission_type($kind);
    if (!$config) {
        return '';
    }
    if ($config['video']) {
        $dir = getenv('KSERS_VIDEO_DIR');
        return ($dir !== false && $dir !== '') ? rtrim($dir, '/\\') : '/data/member_videos';
    }
    $dir = getenv('KSERS_MEMBER_UPLOAD_DIR');
    return ($dir !== false && $dir !== '') ? rtrim($dir, '/\\') : '/data/member_uploads';
}

function ksers_submission_folder($kind) {
    $dir = ksers_submission_dir($kind);
    if ($dir === '' || !ksers_submission_type($kind)) {
        return '';
    }
    return $dir . '/' . $kind;
}

function ksers_submission_find($kind, $storedName) {
    $base = basename(str_replace('\\', '/', (string)$storedName));
    $dir = ksers_submission_dir($kind);
    if ($base === '' || $dir === '') {
        return '';
    }
    $nested = $dir . '/' . $kind . '/' . $base;
    if (is_file($nested)) {
        return $nested;
    }
    $flat = $dir . '/' . $base;
    if (is_file($flat)) {
        return $flat;
    }
    return '';
}

function ksers_submission_max_label($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1073741824) {
        $gb = $bytes / 1073741824;
        $text = abs($gb - round($gb)) < 0.05 ? (string)(int)round($gb) : number_format($gb, 1);
        return $text . ' GB';
    }
    return (string)max(1, (int)round($bytes / 1048576)) . ' MB';
}

function ksers_submission_check_file($kind, $file) {
    $config = ksers_submission_type($kind);
    if (!$config) {
        return [t('자료 종류를 선택해 주세요.', 'Choose a file type.'), null];
    }
    if (!is_array($file)) {
        return [t('파일을 선택해 주세요.', 'Choose a file.'), null];
    }
    $uploadError = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    $originalName = ksers_submission_safe_name(isset($file['name']) ? $file['name'] : '');
    $size = isset($file['size']) ? (int)$file['size'] : 0;
    $tmp = isset($file['tmp_name']) ? $file['tmp_name'] : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        return [t('파일 크기가 서버 허용 한도를 초과했습니다.', 'The file is larger than the server limit.'), null];
    }
    if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
        return [t('파일 업로드에 실패했습니다. 파일 크기를 확인해 주세요.', 'Upload failed. Check the file size.'), null];
    }
    if ($originalName === '' || !in_array($extension, $config['extensions'], true)) {
        return [t('허용되지 않는 파일 형식입니다.', 'This file type is not allowed.'), null];
    }
    if ($size < 1 || $size > (int)$config['max_bytes']) {
        return [t('허용된 파일 크기를 초과했습니다.', 'The file exceeds the allowed size.'), null];
    }
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmp);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
    if (isset($config['mimes'])) {
        $allowedMime = $config['mimes'];
    } elseif (!empty($config['video'])) {
        $allowedMime = ['video/mp4', 'video/quicktime', 'application/octet-stream'];
    } else {
        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
    }
    if (!in_array($mime, $allowedMime, true)) {
        return [t('파일 내용과 확장자가 일치하지 않습니다.', 'The file content does not match its extension.'), null];
    }
    return ['', [
        'original' => $originalName,
        'extension' => $extension,
        'mime' => $mime,
        'size' => $size,
        'tmp' => $tmp,
    ]];
}

function ksers_submission_place($kind, $meta, $year, $memberSeq) {
    $folder = ksers_submission_folder($kind);
    if ($folder === '') {
        return [t('파일 저장 위치를 준비하지 못했습니다.', 'Could not prepare file storage.'), '', ''];
    }
    if (!is_dir($folder) && !mkdir($folder, 0750, true) && !is_dir($folder)) {
        return [t('파일 저장 위치를 준비하지 못했습니다.', 'Could not prepare file storage.'), '', ''];
    }
    $stored = (int)$year . '_' . (int)$memberSeq . '_' . $kind . '_' . bin2hex(random_bytes(12)) . '.' . $meta['extension'];
    $path = $folder . '/' . $stored;
    if (!move_uploaded_file($meta['tmp'], $path)) {
        return [t('파일 저장에 실패했습니다.', 'Could not store the file.'), '', ''];
    }
    return ['', $stored, $path];
}

function ksers_submission_label($kind) {
    $config = ksers_submission_type($kind);
    return $config ? t($config['title_ko'], $config['title_en']) : $kind;
}

function ksers_submission_csrf() {
    if (empty($_SESSION['submission_csrf'])) {
        $_SESSION['submission_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['submission_csrf'];
}

function ksers_submission_csrf_valid($token) {
    return is_string($token)
        && !empty($_SESSION['submission_csrf'])
        && hash_equals($_SESSION['submission_csrf'], $token);
}

function ksers_submission_safe_name($name) {
    $name = basename(str_replace('\\', '/', (string)$name));
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
    if (strlen($name) <= 255) {
        return $name;
    }
    $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $short = '';
    foreach ($chars as $char) {
        if (strlen($short . $char) > 255) {
            break;
        }
        $short .= $char;
    }
    return $short;
}

function ksers_zip_u64($value) {
    $value = (int)$value;
    $low = $value & 0xFFFFFFFF;
    $high = ($value >> 32) & 0xFFFFFFFF;
    return pack('V2', $low, $high);
}

function ksers_abstract_download_fallback($name) {
    $name = str_replace(['"', '\\', "\r", "\n"], '_', (string)$name);
    if ($name !== '' && preg_match('/^[\x20-\x7E]+$/', $name)) {
        return $name;
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', (string)$ext);
    $receipt = 'abstract';
    if (preg_match('/^(A\d+-\d+)_/u', $name, $match)) {
        $receipt = $match[1];
    }
    return $receipt . ($ext !== '' ? '.' . $ext : '');
}

function ksers_abstract_download_entry($receipt, $original, $stored, &$used) {
    $name = basename(str_replace('\\', '/', (string)$original));
    $name = preg_replace('/[\x00-\x1F\x7F\\\\\/:*?"<>|]+/u', '_', $name);
    $name = trim((string)$name, " .");
    if ($name === '' || $name === '.' || $name === '..') {
        $ext = strtolower(pathinfo((string)$stored, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $name = 'abstract' . ($ext !== '' ? '.' . $ext : '');
    }
    $entry = $receipt . '_' . $name;
    if (strlen($entry) > 180) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $ext !== '' ? '.' . $ext : '';
        $limit = 180 - strlen($receipt) - 1 - strlen($suffix);
        if ($limit < 1) {
            $limit = 1;
        }
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $chars = preg_split('//u', $stem, -1, PREG_SPLIT_NO_EMPTY);
        $short = '';
        if (is_array($chars)) {
            foreach ($chars as $char) {
                if (strlen($short . $char) > $limit) {
                    break;
                }
                $short .= $char;
            }
        }
        if ($short === '') {
            $short = 'file';
        }
        $entry = $receipt . '_' . $short . $suffix;
    }
    $base = $entry;
    $n = 2;
    while (isset($used[$entry])) {
        $entry = $base . '_' . $n;
        $n++;
    }
    $used[$entry] = true;
    return $entry;
}

function ksers_zip_entry_name($original, $seq, &$used) {
    $name = ksers_submission_safe_name($original);
    $name = str_replace(['\\', '/'], '_', $name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'file-' . (int)$seq;
    }
    if (isset($used[$name])) {
        $name = (int)$seq . '_' . $name;
    }
    $used[$name] = true;
    return $name;
}

function ksers_zip_length($entries) {
    $total = 56 + 20 + 22;
    foreach ($entries as $entry) {
        $nameLength = strlen($entry['name']);
        $total += 30 + $nameLength + 20 + (int)$entry['size'] + 24;
        $total += 46 + $nameLength + 28;
    }
    return $total;
}

function ksers_zip_stored_write($output, $entries) {
    $now = getdate();
    $time = ($now['hours'] << 11) | ($now['minutes'] << 5) | (int)($now['seconds'] / 2);
    $date = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];
    $central = '';
    $offset = 0;
    $count = 0;
    foreach ($entries as $entry) {
        $name = $entry['name'];
        $nameLength = strlen($name);
        $input = fopen($entry['path'], 'rb');
        if ($input === false) {
            return false;
        }
        $local = pack('V', 0x04034b50)
            . pack('v', 45)
            . pack('v', 0x0808)
            . pack('v', 0)
            . pack('v', $time)
            . pack('v', $date)
            . pack('V', 0)
            . pack('V', 0xFFFFFFFF)
            . pack('V', 0xFFFFFFFF)
            . pack('v', $nameLength)
            . pack('v', 20)
            . $name
            . pack('v', 0x0001)
            . pack('v', 16)
            . ksers_zip_u64(0)
            . ksers_zip_u64(0);
        if (fwrite($output, $local) === false) {
            fclose($input);
            return false;
        }
        $crc = hash_init('crc32b');
        $size = 0;
        while (!feof($input)) {
            $chunk = fread($input, 1048576);
            if ($chunk === false || $chunk === '') {
                break;
            }
            hash_update($crc, $chunk);
            if (fwrite($output, $chunk) === false) {
                fclose($input);
                return false;
            }
            $size += strlen($chunk);
        }
        fclose($input);
        $crcValue = hexdec(hash_final($crc));
        $descriptor = pack('V', 0x08074b50)
            . pack('V', $crcValue)
            . ksers_zip_u64($size)
            . ksers_zip_u64($size);
        if (fwrite($output, $descriptor) === false) {
            return false;
        }
        $central .= pack('V', 0x02014b50)
            . pack('v', 45)
            . pack('v', 45)
            . pack('v', 0x0808)
            . pack('v', 0)
            . pack('v', $time)
            . pack('v', $date)
            . pack('V', $crcValue)
            . pack('V', 0xFFFFFFFF)
            . pack('V', 0xFFFFFFFF)
            . pack('v', $nameLength)
            . pack('v', 28)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 0)
            . pack('V', 0xFFFFFFFF)
            . $name
            . pack('v', 0x0001)
            . pack('v', 24)
            . ksers_zip_u64($size)
            . ksers_zip_u64($size)
            . ksers_zip_u64($offset);
        $offset += 30 + $nameLength + 20 + $size + 24;
        $count++;
    }
    $centralLength = strlen($central);
    $zip64 = pack('V', 0x06064b50)
        . ksers_zip_u64(44)
        . pack('v', 45)
        . pack('v', 45)
        . pack('V', 0)
        . pack('V', 0)
        . ksers_zip_u64($count)
        . ksers_zip_u64($count)
        . ksers_zip_u64($centralLength)
        . ksers_zip_u64($offset);
    $locator = pack('V', 0x07064b50)
        . pack('V', 0)
        . ksers_zip_u64($offset + $centralLength)
        . pack('V', 1);
    $end = pack('V', 0x06054b50)
        . pack('v', 0)
        . pack('v', 0)
        . pack('v', 0xFFFF)
        . pack('v', 0xFFFF)
        . pack('V', 0xFFFFFFFF)
        . pack('V', 0xFFFFFFFF)
        . pack('v', 0);
    return fwrite($output, $central . $zip64 . $locator . $end) !== false;
}
