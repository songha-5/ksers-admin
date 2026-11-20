<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($memberSeq < 1) {
    header('Location: /service/login.php');
    exit;
}
ksers_require_participant($pdo);

$year = isset($eventYear) ? (int)$eventYear : 2027;
$topicStmt = $pdo->prepare("SELECT seq, name_ko, name_en FROM abstract_topic WHERE year = ? AND state = 1 ORDER BY sort_no, seq");
$topicStmt->execute([$year]);
$topics = $topicStmt->fetchAll(PDO::FETCH_ASSOC);
$countries = ksers_country_rows($pdo);
$countryName = [];
$koreaCountry = [];
foreach ($countries as $countryRow) {
    $countryName[(int)$countryRow['minr']] = code_name($countryRow);
    if (!empty(ksers_country_profile($countryRow)['korea'])) {
        $koreaCountry[(int)$countryRow['minr']] = true;
    }
}
$storeDir = getenv('KSERS_ABSTRACT_DIR') ?: '/data/abstracts';
$presentationChoices = [
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
    'video' => 'Video',
    'mentor' => 'Mentor-Mentee Video',
];
$presentationTypes = $presentationChoices + [
    'poster' => t('포스터', 'Poster'),
    'either' => t('상관없음', 'No preference'),
];
$abstractDeadlineOpen = (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))
    <= new DateTimeImmutable('2027-01-14 23:59:59', new DateTimeZone('Asia/Seoul'));

$presentationLabel = function ($code) use ($presentationTypes) {
    return isset($presentationTypes[$code]) ? $presentationTypes[$code] : $code;
};
$collectAuthors = function ($post) use ($countryName, $koreaCountry) {
    $firstNames = isset($post['author_first']) && is_array($post['author_first']) ? array_values($post['author_first']) : [];
    $lastNames = isset($post['author_last']) && is_array($post['author_last']) ? array_values($post['author_last']) : [];
    $affiliations = isset($post['author_affiliation']) && is_array($post['author_affiliation']) ? array_values($post['author_affiliation']) : [];
    $departments = isset($post['author_department']) && is_array($post['author_department']) ? array_values($post['author_department']) : [];
    $countriesPosted = isset($post['author_country']) && is_array($post['author_country']) ? array_values($post['author_country']) : [];
    $emails = isset($post['author_email']) && is_array($post['author_email']) ? array_values($post['author_email']) : [];
    $presenting = isset($post['author_presenting']) && is_array($post['author_presenting']) ? array_values($post['author_presenting']) : [];
    $corresponding = isset($post['author_corresponding']) && is_array($post['author_corresponding']) ? array_values($post['author_corresponding']) : [];
    $mentor = isset($post['author_mentor']) && is_array($post['author_mentor']) ? array_values($post['author_mentor']) : [];
    $count = count($firstNames);
    $rows = [];
    if ($count < 1) {
        return [[], t('저자를 한 명 이상 추가해 주세요.', 'Add at least one author.')];
    }
    if ($count > 20) {
        return [[], t('저자는 최대 20명까지 입력할 수 있습니다.', 'Up to 20 authors can be entered.')];
    }
    $sameCount = $count === count($lastNames)
        && $count === count($affiliations)
        && $count === count($departments)
        && $count === count($countriesPosted)
        && $count === count($emails)
        && $count === count($presenting)
        && $count === count($corresponding)
        && $count === count($mentor);
    if (!$sameCount) {
        return [[], t('저자 정보를 다시 입력해 주세요.', 'Enter the author information again.')];
    }
    $presentingCount = 0;
    $correspondingCount = 0;
    $mentorCount = 0;
    $rowError = '';
    $length = function ($value) {
        $chars = preg_match_all('/./u', $value, $unused);
        return $chars === false ? 0 : $chars;
    };
    for ($i = 0; $i < $count; $i++) {
        $row = [
            'first' => trim((string)$firstNames[$i]),
            'last' => trim((string)$lastNames[$i]),
            'affiliation' => trim((string)$affiliations[$i]),
            'department' => trim((string)$departments[$i]),
            'country' => (int)$countriesPosted[$i],
            'email' => trim((string)$emails[$i]),
            'presenting' => (int)$presenting[$i] === 1 ? 1 : 0,
            'corresponding' => (int)$corresponding[$i] === 1 ? 1 : 0,
            'mentor' => (int)$mentor[$i] === 1 ? 1 : 0,
        ];
        $rows[] = $row;
        if ($rowError !== '') {
            continue;
        }
        if ($row['first'] === '' || $row['last'] === '' || $row['affiliation'] === '') {
            $rowError = t('저자의 영문 성명과 소속은 필수입니다.', 'Each author needs an English name and affiliation.');
        } elseif ($length($row['first']) > 80 || $length($row['last']) > 80 || $length($row['affiliation']) > 200 || $length($row['department']) > 120) {
            $rowError = t('저자 입력 길이를 줄여 주세요.', 'Shorten the author text and try again.');
        } elseif ($row['presenting'] && $row['mentor']) {
            $rowError = t('발표자와 Mentor는 동시에 지정할 수 없습니다.', 'The presenting author and the mentor must be different people.');
        } elseif (!$row['presenting'] && !$row['corresponding'] && !$row['mentor']) {
            $rowError = t('각 저자의 역할을 선택해 주세요. Presenting, Corresponding, Mentor 중 하나 이상을 선택해야 합니다.', 'Choose a role for each author. Select Presenting, Corresponding, Mentor, or a combination that is allowed.');
        } elseif ($row['presenting'] && !isset($countryName[$row['country']])) {
            $rowError = t('발표자의 국가는 필수입니다.', 'The presenting author needs a country.');
        } elseif (!$row['presenting'] && $row['country'] !== 0 && !isset($countryName[$row['country']])) {
            $rowError = t('저자 국가를 다시 선택해 주세요.', 'Choose the author country again.');
        } else {
            $overseas = $row['country'] !== 0 && !isset($koreaCountry[$row['country']]);
            $needsEmail = $row['presenting'] || $row['corresponding'] || $overseas;
            if ($needsEmail && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $rowError = $overseas && !$row['presenting'] && !$row['corresponding']
                    ? t('해외 국가를 선택한 저자는 이메일이 필수입니다.', 'An author outside Korea needs an email address.')
                    : t('발표자와 교신저자의 이메일을 입력해 주세요.', 'Enter an email for the presenting author and corresponding author.');
            } elseif (!$needsEmail && $row['email'] !== '' && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $rowError = t('저자 이메일 형식을 확인해 주세요.', 'Check the author email address.');
            }
        }
        if ($row['presenting']) {
            $presentingCount++;
        }
        if ($row['corresponding']) {
            $correspondingCount++;
        }
        if ($row['mentor']) {
            $mentorCount++;
        }
    }
    if ($rowError !== '') {
        return [$rows, $rowError];
    }
    if ($presentingCount !== 1) {
        return [$rows, $presentingCount > 1
            ? t('발표자는 한 명만 선택할 수 있습니다.', 'Choose one presenting author.')
            : t('발표자를 한 명 지정해 주세요.', 'Choose one presenting author.')];
    }
    if ($correspondingCount < 1) {
        return [$rows, t('교신저자를 한 명 이상 지정해 주세요.', 'Choose at least one corresponding author.')];
    }
    if ($mentorCount > 1) {
        return [$rows, t('Mentor는 한 명만 지정할 수 있습니다.', 'Choose one mentor.')];
    }
    return [$rows, ''];
};

$loadOwn = function ($seq) use ($pdo, $memberSeq, $year) {
    $stmt = $pdo->prepare("SELECT * FROM abstract_info WHERE seq = ? AND member_seq = ? AND year = ?");
    $stmt->execute([(int)$seq, $memberSeq, $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
};

$error = '';
$postedAuthorRows = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'delete') {
        if (!$abstractDeadlineOpen) {
            header('Location: abstract.php?delete=closed');
            exit;
        }
        $ids = isset($_POST['abs']) && is_array($_POST['abs']) ? $_POST['abs'] : [];
        $deleteStmt = $pdo->prepare("SELECT seq, stored_name FROM abstract_info WHERE seq = ? AND member_seq = ? AND year = ? AND status = 'received'");
        $removeStmt = $pdo->prepare("DELETE FROM abstract_info WHERE seq = ? AND member_seq = ? AND year = ? AND status = 'received'");
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id < 1) {
                continue;
            }
            $deleteStmt->execute([$id, $memberSeq, $year]);
            $row = $deleteStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                continue;
            }
            ksers_abstract_deleted_notify($pdo, $id);
            $pdo->prepare("DELETE FROM abstract_author WHERE abstract_seq = ?")->execute([$id]);
            $removeStmt->execute([$id, $memberSeq, $year]);
            $stored = basename((string)$row['stored_name']);
            $path = $storeDir . '/' . $stored;
            if ($stored !== '' && is_file($path)) {
                unlink($path);
            }
        }
        ksers_sync_user_class($pdo, $memberSeq);
        header('Location: abstract.php');
        exit;
    }

    if ($action === 'save') {
        $editSeq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
        $existing = $editSeq > 0 ? $loadOwn($editSeq) : null;
        if ($editSeq > 0 && !$existing) {
            $error = t('본인 초록만 수정할 수 있습니다.', 'You can edit only your own abstract.');
        } elseif ($existing && $existing['status'] !== 'received') {
            $error = t('심사가 시작된 초록은 수정할 수 없습니다.', 'This abstract can no longer be edited.');
        } elseif ($existing && !$abstractDeadlineOpen) {
            $error = t('2027년 1월 14일(목)까지 초록을 수정할 수 있습니다.', 'You may edit your abstract until January 14 (Thu), 2027.');
        } else {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $presentation = isset($_POST['presentation_type']) ? $_POST['presentation_type'] : '';
            $topic = isset($_POST['topic']) ? trim($_POST['topic']) : '';
            list($authorRows, $authorError) = $collectAuthors($_POST);
            $postedAuthorRows = $authorRows;
            $authors = '';
            $authorCountry = 0;
            $affiliation = '';
            foreach ($authorRows as $authorRow) {
                $authors .= ($authors === '' ? '' : ', ') . trim($authorRow['first'] . ' ' . $authorRow['last']);
                if ($authorRow['presenting']) {
                    $authorCountry = $authorRow['country'];
                    $affiliation = $authorRow['affiliation'];
                }
            }
            if (function_exists('mb_substr')) {
                $authors = mb_substr($authors, 0, 1000, 'UTF-8');
            }
            $length = function ($value) {
                $count = preg_match_all('/./u', $value, $unused);
                return $count === false ? 0 : $count;
            };
            $file = isset($_FILES['abstract_file']) ? $_FILES['abstract_file'] : null;
            $hasFile = $file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
            $mentorTotal = 0;
            $mentorOnly = false;
            foreach ($authorRows as $authorRow) {
                if (empty($authorRow['mentor'])) {
                    continue;
                }
                $mentorTotal++;
                if (empty($authorRow['presenting']) && empty($authorRow['corresponding'])) {
                    $mentorOnly = true;
                }
            }

            if ($topics) {
                $topicName = '';
                foreach ($topics as $topicRow) {
                    if ((string)$topicRow['seq'] === $topic) {
                        $topicName = $topicRow['name_ko'];
                    }
                }
                $topic = $topicName;
            }
            if ($authorError !== '') {
                $error = $authorError;
            } elseif ($title === '' || $topic === '') {
                $error = t('제목과 분야를 입력해 주세요.', 'Enter the title and choose a topic.');
            } elseif (!isset($presentationChoices[$presentation])) {
                $error = t('발표 희망 형태를 선택해 주세요.', 'Choose a presentation type.');
            } elseif ($presentation === 'mentor' && $mentorTotal !== 1) {
                $error = t('Mentor-Mentee Video는 Mentor를 한 명 지정해 주세요.', 'Mentor-Mentee Video needs one mentor.');
            } elseif ($presentation !== 'mentor' && $mentorOnly) {
                $error = t('Mentor는 Mentor-Mentee Video에서만 지정할 수 있습니다. 해당 저자의 역할을 다시 선택해 주세요.', 'Mentor can be assigned only for Mentor-Mentee Video. Choose another role for that author.');
            } elseif ($length($title) > 150) {
                $error = t('제목은 150자까지 입력할 수 있습니다.', 'The title can be up to 150 characters.');
            } elseif (empty($_POST['coauthor_agree']) || empty($_POST['privacy_agree']) || empty($_POST['publication_agree'])) {
                $error = t('공동저자 동의, 개인정보 수집·이용 동의, 초록 게재 동의를 모두 체크해 주세요.', 'Please check Co-author Consent, Privacy Consent, and Publication Consent.');
            } elseif (!$existing && !$hasFile) {
                $error = t('초록 파일을 첨부해 주세요.', 'Attach the abstract file.');
            } else {
                $storedName = $existing ? $existing['stored_name'] : '';
                $originalName = $existing ? $existing['file_name'] : '';
                if ($hasFile) {
                    if ($file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 10 * 1024 * 1024) {
                        $error = t('파일은 10MB 이하의 PDF, DOC, DOCX만 올릴 수 있습니다.', 'Upload a PDF, DOC, or DOCX file up to 10MB.');
                    } else {
                        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                            $error = t('파일은 PDF, DOC, DOCX만 올릴 수 있습니다.', 'Only PDF, DOC, and DOCX files are allowed.');
                        } else {
                            if (!is_dir($storeDir)) {
                                mkdir($storeDir, 0700, true);
                            }
                            $newStored = bin2hex(random_bytes(16)) . '.' . $ext;
                            $dest = $storeDir . '/' . $newStored;
                            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                                $error = t('파일을 저장하지 못했습니다.', 'The file could not be saved.');
                            } else {
                                if ($storedName !== '') {
                                    $oldPath = $storeDir . '/' . basename((string)$storedName);
                                    if (is_file($oldPath)) {
                                        unlink($oldPath);
                                    }
                                }
                                $storedName = $newStored;
                                $originalName = basename(str_replace(['\\', '/'], '', (string)$file['name']));
                            }
                        }
                    }
                }

                if ($error === '' && $storedName !== '') {
                    if ($presentation !== 'mentor') {
                        foreach ($authorRows as $authorIndex => $authorRow) {
                            $authorRows[$authorIndex]['mentor'] = 0;
                        }
                    }
                    if ($existing) {
                        $update = $pdo->prepare(
                            "UPDATE abstract_info
                             SET title = ?, presentation_type = ?, topic = ?, authors = ?, author_country = ?, affiliation = ?, file_name = ?, stored_name = ?,
                                 agree_coauthor = 1, agree_privacy = 1, agree_publication = 1, update_date = NOW()
                             WHERE seq = ? AND member_seq = ? AND year = ? AND status = 'received'"
                        );
                        $update->execute([
                            $title, $presentation, $topic, $authors, $authorCountry, $affiliation, $originalName, $storedName,
                            (int)$existing['seq'], $memberSeq, $year,
                        ]);
                    } else {
                        $insert = $pdo->prepare(
                            "INSERT INTO abstract_info
                             (member_seq, year, title, presentation_type, topic, authors, author_country, affiliation, file_name, stored_name, status, agree_coauthor, agree_privacy, agree_publication, reg_date, update_date)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'received', 1, 1, 1, NOW(), NOW())"
                        );
                        $insert->execute([
                            $memberSeq, $year, $title, $presentation, $topic, $authors, $authorCountry, $affiliation, $originalName, $storedName,
                        ]);
                    }
                    $abstractSeq = $existing ? (int)$existing['seq'] : (int)$pdo->lastInsertId();
                    $pdo->prepare("DELETE FROM abstract_author WHERE abstract_seq = ?")->execute([$abstractSeq]);
                    $insertAuthor = $pdo->prepare(
                        "INSERT INTO abstract_author
                            (abstract_seq, sort_no, first_name, last_name, affiliation, department, country, email, presenting, corresponding, mentor)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    foreach ($authorRows as $index => $authorRow) {
                        $insertAuthor->execute([
                            $abstractSeq, $index + 1, $authorRow['first'], $authorRow['last'], $authorRow['affiliation'],
                            $authorRow['department'], $authorRow['country'], $authorRow['email'], $authorRow['presenting'], $authorRow['corresponding'], $authorRow['mentor'],
                        ]);
                    }
                    ksers_sync_user_class($pdo, $memberSeq);
                    if ($existing) {
                        ksers_abstract_updated_notify($pdo, $abstractSeq, $hasFile, false);
                    } else {
                        ksers_abstract_received_notify($pdo, $abstractSeq);
                    }
                    header('Location: abstract.php?saved=1');
                    exit;
                }
            }
        }
    }
}

$listStmt = $pdo->prepare("SELECT * FROM abstract_info WHERE member_seq = ? AND year = ? ORDER BY seq DESC");
$listStmt->execute([$memberSeq, $year]);
$abstracts = $listStmt->fetchAll(PDO::FETCH_ASSOC);
$videoByAbstract = [];
$videoStmt = $pdo->prepare(
    "SELECT abstract_seq, file_size FROM member_submission_file
     WHERE member_seq = ? AND year = ? AND kind IN ('abstract_video_1', 'abstract_video_2') AND abstract_seq IS NOT NULL
     ORDER BY seq DESC"
);
$videoStmt->execute([$memberSeq, $year]);
foreach ($videoStmt->fetchAll(PDO::FETCH_ASSOC) as $videoRow) {
    $videoSeq = (int)$videoRow['abstract_seq'];
    if ($videoSeq > 0 && !isset($videoByAbstract[$videoSeq])) {
        $videoByAbstract[$videoSeq] = (int)$videoRow['file_size'];
    }
}
$topicLabel = function ($stored) use ($topics) {
    foreach ($topics as $topicRow) {
        if ((string)$topicRow['seq'] === (string)$stored || $topicRow['name_ko'] === $stored || $topicRow['name_en'] === $stored) {
            return ksers_text($topicRow, 'name_ko', 'name_en');
        }
    }
    return (string)$stored;
};
$presentationBadge = function ($code) {
    if ($code === 'video' || $code === 'abstract_video_1') {
        return 'is-video';
    }
    if ($code === 'mentor' || $code === 'mentee' || $code === 'abstract_video_2') {
        return 'is-mentor';
    }
    if ($code === 'eposter' || $code === 'poster') {
        return 'is-eposter';
    }
    return 'is-oral';
};
$fileSizeLabel = function ($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return number_format(max(1, (int)round($bytes / 1048576))) . ' MB';
    }
    if ($bytes > 0) {
        return number_format(max(1, (int)round($bytes / 1024))) . ' KB';
    }
    return '';
};

$editSeq = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = $editSeq > 0 ? $loadOwn($editSeq) : null;
if ($editing && $editing['status'] !== 'received') {
    $error = t('심사가 시작된 초록은 수정할 수 없습니다.', 'This abstract can no longer be edited.');
    $editing = null;
} elseif ($editing && !$abstractDeadlineOpen) {
    $error = t('2027년 1월 14일(목)까지 초록을 수정할 수 있습니다. 그 이후 수정은 사무국(ksers2027@ksers.kr)으로 요청해 주세요.', 'You may edit your abstract until January 14 (Thu), 2027. After that date, ask the secretariat (ksers2027@ksers.kr).');
    $editing = null;
}
$showForm = isset($_GET['add']) || $editing || ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '');
$submitterStmt = $pdo->prepare("SELECT name_ko, first_name_eng, last_name_eng, country FROM member_info WHERE seq = ?");
$submitterStmt->execute([$memberSeq]);
$submitter = $submitterStmt->fetch(PDO::FETCH_ASSOC);
$submitterKorea = ksers_member_is_korea($pdo, $memberSeq);
$formAuthors = $postedAuthorRows;
if (!$formAuthors && $editing) {
    $savedAuthors = $pdo->prepare(
        "SELECT first_name, last_name, affiliation, department, country, email, presenting, corresponding, mentor
         FROM abstract_author WHERE abstract_seq = ? ORDER BY sort_no, seq"
    );
    $savedAuthors->execute([(int)$editing['seq']]);
    foreach ($savedAuthors->fetchAll(PDO::FETCH_ASSOC) as $savedAuthor) {
        $formAuthors[] = [
            'first' => $savedAuthor['first_name'],
            'last' => $savedAuthor['last_name'],
            'affiliation' => $savedAuthor['affiliation'],
            'department' => $savedAuthor['department'],
            'country' => (int)$savedAuthor['country'],
            'email' => $savedAuthor['email'],
            'presenting' => (int)$savedAuthor['presenting'],
            'corresponding' => (int)$savedAuthor['corresponding'],
            'mentor' => (int)$savedAuthor['mentor'],
        ];
    }
}
$countryOptions = [];
foreach ($countries as $countryRow) {
    $countryOptions[] = ['id' => (int)$countryRow['minr'], 'name' => code_name($countryRow)];
}

$page_title = t('초록 제출 - KSERS ' . $year, 'Abstract Submission - KSERS ' . $year);
$page_heading = t('초록 제출', 'Abstract Submission');
include 'lib/header.php';
include 'lib/menu.php';
?>

<div class="mb-5">
        <?php if (isset($_GET['saved'])) { ?>
        <div class="alert alert-success border-0 shadow-sm"><?= h(t('초록을 저장했습니다. 2027년 1월 14일(목)까지 수정할 수 있습니다.', 'The abstract was saved. You may edit it until January 14 (Thu), 2027.')) ?></div>
        <?php } ?>
        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'closed') { ?>
        <div class="alert alert-warning border-0 shadow-sm"><?= h(t('접수 마감일 이후에는 여기서 삭제할 수 없습니다. 사무국(ksers2027@ksers.kr)으로 요청해 주세요.', 'Abstracts can no longer be deleted here after the deadline. Please ask the secretariat (ksers2027@ksers.kr).')) ?></div>
        <?php } ?>
        <?php if ($error !== '') { ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= h($error) ?></div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var list = document.getElementById('abstractNoticeList');
            var modal = document.getElementById('abstractNoticeModal');
            if (!list || !modal || !window.bootstrap) {
                return;
            }
            var item = document.createElement('li');
            item.textContent = <?= json_encode($error, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            list.appendChild(item);
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        });
        </script>
        <?php } ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <p class="text-muted small mb-0">
                    <?= h($topics
                        ? t('로그인한 계정으로 초록을 제출합니다. 참가 등록이나 결제와 관계없이 제출할 수 있습니다. 분야는 목록에서 고릅니다.', 'Submit an abstract with this account. Registration and payment are not required. Choose a topic from the list.')
                        : t('로그인한 계정으로 초록을 제출합니다. 참가 등록이나 결제와 관계없이 제출할 수 있습니다. 분야 목록이 없으면 직접 입력합니다.', 'Submit an abstract with this account. Registration and payment are not required. If no topic list is set, type the topic.')) ?>
                </p>
            </div>
        </div>

        <?php if ($showForm) {
            $form = $editing ? $editing : [
                'seq' => isset($_POST['seq']) ? (int)$_POST['seq'] : 0,
                'title' => isset($_POST['title']) ? $_POST['title'] : '',
                'presentation_type' => isset($_POST['presentation_type']) ? $_POST['presentation_type'] : '',
                'topic' => isset($_POST['topic']) ? $_POST['topic'] : '',
                'authors' => isset($_POST['authors']) ? $_POST['authors'] : '',
                'author_country' => isset($_POST['author_country']) ? (int)$_POST['author_country'] : 0,
                'affiliation' => isset($_POST['affiliation']) ? $_POST['affiliation'] : '',
                'file_name' => '',
            ];
            $linkedVideos = [];
            if ((int)$form['seq'] > 0) {
                $linkedVideoStmt = $pdo->prepare(
                    "SELECT seq, kind, original_name, reg_date, update_date
                     FROM member_submission_file
                     WHERE abstract_seq = ? AND member_seq = ? AND year = ? AND kind IN ('abstract_video_1', 'abstract_video_2')
                     ORDER BY seq"
                );
                $linkedVideoStmt->execute([(int)$form['seq'], $memberSeq, $year]);
                $linkedVideos = $linkedVideoStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="m-0 fw-bold"><?= h($editing ? t('초록 수정', 'Edit abstract') : t('초록 추가', 'Add abstract')) ?></h5>
            </div>
            <div class="card-body p-4">
                <form method="post" action="abstract.php" id="abstractForm" enctype="multipart/form-data" data-leave="abstract" data-has-file="<?= ($editing && !empty($form['file_name'])) ? '1' : '0' ?>" novalidate>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="seq" value="<?= (int)$form['seq'] ?>">
                    <h6 class="fw-bold text-success mb-3"><i class="bi bi-easel me-2"></i><?= h(t('발표 정보', 'Presentation information')) ?></h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label small fw-bold" for="presentation"><?= h(t('발표 희망 형태', 'Presentation Type')) ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="presentation" name="presentation_type" required>
                                <option value=""><?= h(t('선택', 'Choose')) ?></option>
                                <?php
                                $selectedPresentation = $form['presentation_type'] === 'poster' ? 'eposter' : $form['presentation_type'];
                                foreach ($presentationChoices as $code => $label) { ?>
                                <option value="<?= h($code) ?>" <?= $selectedPresentation === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php } ?>
                            </select>
                            <div class="form-text"><?= h(t('발표 희망 형태는 저자가 선택할 수 있습니다. 최종 결정은 심사 후 학술위원회가 합니다.', 'Authors may choose their preferred presentation type. The final decision will be made by the Scientific Committee after review.')) ?></div>
                            <div class="form-text">* <?= h(t('Video·Mentor-Mentee Video는 초록을 먼저 제출한 뒤, 마이페이지 > 영상 제출에서 영상을 등록해 주세요.', 'For Video and Mentor-Mentee Video, submit the abstract first, then register the video in My Page > Video Submission.')) ?></div>
                            <div id="presentationHint" class="form-text small is-invalid d-none"><?= h(t('발표 희망 형태를 선택해 주세요.', 'Choose a presentation type.')) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold" for="topic"><?= h(t('분야', 'Topic')) ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="topic" name="topic" required>
                                <option value=""><?= h(t('선택', 'Choose')) ?></option>
                                <?php foreach ($topics as $topicRow) { ?>
                                <option value="<?= (int)$topicRow['seq'] ?>" <?= ((string)$form['topic'] === (string)$topicRow['seq'] || $form['topic'] === $topicRow['name_ko']) ? 'selected' : '' ?>><?= h(ksers_text($topicRow, 'name_ko', 'name_en')) ?></option>
                                <?php } ?>
                            </select>
                            <div id="topicHint" class="form-text small is-invalid d-none"><?= h(t('분야를 선택해 주세요.', 'Choose a topic.')) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold" for="title"><?= h(t('제목', 'Title')) ?> <span class="text-danger">*</span></label>
                            <input class="form-control" id="title" name="title" maxlength="150" required value="<?= h($form['title']) ?>">
                            <div class="form-text"><?= h(t('제목은 150자까지 입력할 수 있습니다.', 'Enter up to 150 characters.')) ?></div>
                            <div id="titleHint" class="form-text small is-invalid d-none"><?= h(t('제목을 입력해 주세요.', 'Enter the title.')) ?></div>
                        </div>
                    </div>
                    <h6 class="fw-bold text-success mb-3"><i class="bi bi-people me-2"></i><?= h(t('저자 정보', 'Author information')) ?></h6>
                    <div class="mb-4">
                        <label class="form-label small fw-bold"><?= h(t('제출자', 'Submitter')) ?></label>
                        <?php if ($submitterKorea) { ?>
                        <label class="form-label small fw-bold"><?= h(t('성명(국문)', 'Name (Korean)')) ?></label>
                        <input class="form-control" value="<?= h($submitter ? $submitter['name_ko'] : '') ?>" disabled>
                            <?php } else { ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><?= h(t('이름(영문)', 'First Name')) ?></label>
                                <input class="form-control" value="<?= h($submitter ? $submitter['first_name_eng'] : '') ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><?= h(t('성(영문)', 'Family (Last) Name')) ?></label>
                                <input class="form-control" value="<?= h($submitter ? $submitter['last_name_eng'] : '') ?>" disabled>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="mb-4">
                        <div class="form-label small fw-bold"><?= h(t('저자 추가', 'Authors (Add)')) ?> <span class="text-danger">*</span></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold" for="authorFirst"><?= h(t('이름(영문)', 'First Name')) ?> <span class="text-danger">*</span></label>
                                        <input class="form-control" id="authorFirst" maxlength="80">
                        </div>
                        <div class="col-md-6">
                                        <label class="form-label small fw-bold" for="authorLast"><?= h(t('성(영문)', 'Last Name')) ?> <span class="text-danger">*</span></label>
                                        <input class="form-control" id="authorLast" maxlength="80">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold" for="authorAffiliation"><?= h(t('소속(영문)', 'Affiliation')) ?> <span class="text-danger">*</span></label>
                                        <input class="form-control" id="authorAffiliation" maxlength="200">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold" for="authorDepartment"><?= h(t('부서(영문)', 'Department')) ?></label>
                                        <input class="form-control" id="authorDepartment" maxlength="120">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold" for="authorCountry"><?= h(t('국가', 'Country')) ?> <span class="text-danger d-none" id="authorCountryStar">*</span></label>
                                        <select class="form-select" id="authorCountry">
                                            <option value=""><?= h(t('국가를 선택해 주세요', 'Choose a country')) ?></option>
                                            <?php foreach ($countries as $countryRow) {
                                                $countryKorea = !empty(ksers_country_profile($countryRow)['korea']);
                                            ?>
                                            <option value="<?= (int)$countryRow['minr'] ?>" data-korea="<?= $countryKorea ? '1' : '0' ?>"><?= h(code_name($countryRow)) ?></option>
                                            <?php } ?>
                                        </select>
                                        <div class="form-text" id="authorCountryHelp"><?= h(t('발표자를 선택하면 국가가 필수입니다. 공저자는 선택입니다.', 'Required when Presenting is selected. Optional for co-authors.')) ?></div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold" for="authorEmail"><?= h(t('이메일', 'E-mail')) ?> <span class="text-danger d-none" id="authorEmailStar">*</span></label>
                                        <input class="form-control" id="authorEmail" type="email" maxlength="120">
                                        <div class="form-text" id="authorEmailHelp"><?= h(t('발표자와 교신저자는 이메일이 필수입니다. 해외 국가를 고르면 공저자도 이메일이 필수입니다.', 'Required for the presenting author and corresponding author. An author outside Korea also needs an email address.')) ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-label small fw-bold"><?= h(t('역할', 'Role')) ?> <span class="text-danger">*</span></div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="authorPresenting" value="1">
                                            <label class="form-check-label" for="authorPresenting">Presenting</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="authorCorresponding" value="1">
                                            <label class="form-check-label" for="authorCorresponding">Corresponding</label>
                                        </div>
                                        <div class="form-check d-none" id="authorMentorRow">
                                            <input class="form-check-input" type="checkbox" id="authorMentor" value="1">
                                            <label class="form-check-label" for="authorMentor">Mentor</label>
                                        </div>
                                        <div class="form-text"><?= h(t('발표자는 1명, 교신저자는 1명 이상입니다. Mentor-Mentee Video는 Mentor 1명이 필요하며, 발표자와 Mentor는 동시에 지정할 수 없습니다. 교신저자와 Mentor는 겸임할 수 있습니다.', 'Choose one presenting author and at least one corresponding author. Mentor-Mentee Video needs one mentor. The presenting author and the mentor must be different people. A corresponding author may also be the mentor.')) ?></div>
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary-custom" type="button" id="authorAdd"><?= h(t('추가', 'Add')) ?></button>
                                        <div id="authorAddHint" class="form-text small is-invalid d-none"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="author-list-box mt-3">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0 author-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th><?= h(t('성명 (영문)', 'Name')) ?></th>
                                            <th><?= h(t('소속', 'Affiliation')) ?></th>
                                            <th><?= h(t('부서', 'Department')) ?></th>
                                            <th><?= h(t('국가', 'Country')) ?></th>
                                            <th><?= h(t('이메일', 'E-mail')) ?></th>
                                            <th><?= h(t('역할', 'Role')) ?></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="authorTable"></tbody>
                                </table>
                            </div>
                            <p class="author-list-empty" id="authorEmpty"><?= h(t('추가된 저자가 없습니다.', 'No authors yet.')) ?></p>
                        </div>
                        <ul class="form-text small mt-3 mb-0 ps-3">
                            <li><?= h(t('저자는 최대 20명까지 입력할 수 있습니다. 20명을 넘는 경우 사무국(ksers2027@ksers.kr)으로 문의해 주세요.', 'Up to 20 authors can be entered. If there are more than 20 authors, please contact the secretariat (ksers2027@ksers.kr).')) ?></li>
                            <li><?= h(t('저자 정보를 정확하게 입력해 주시고, 초록 관련 모든 정보는 제출자, 발표자, 교신저자의 이메일 주소로 발송될 예정임을 알려드립니다.', 'Please enter author information accurately. All information regarding the abstract will be sent to the e-mail addresses of the submitter, presenting author and corresponding author.')) ?></li>
                            <li><?= h(t('첫 번째 저자의 이름을 먼저 입력해야 합니다. 이름은 여기에 표시된 대로 인쇄되므로 저자 목록의 순서를 확인하십시오.', 'Enter the first author first. Names will be printed exactly as entered here, so please check the order of the author list.')) ?></li>
                            <li><?= h(t('각 저자는 Presenting, Corresponding, Mentor 중 하나 이상을 선택해야 추가됩니다. Mentor-Mentee Video가 아닌 영상을 제출하면 Mentor 지정은 빠집니다.', 'Each author needs Presenting, Corresponding, or Mentor before they can be added. Submitting a video other than Mentor-Mentee Video removes the mentor.')) ?></li>
                        </ul>
                        <div id="authorListHint" class="form-text small is-invalid d-none"><?= h(t('저자를 한 명 이상 추가해 주세요.', 'Add at least one author.')) ?></div>
                        <div id="authorStore"></div>
                    </div>
                    <h6 class="fw-bold text-success mb-3"><i class="bi bi-file-earmark-text me-2"></i><?= h(t('초록 본문', 'Abstract body')) ?></h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold" for="abstractFile"><?= h(t('초록 파일', 'Abstract File')) ?> <span class="text-danger">*</span></label>
                            <div class="file-picker">
                                <span class="file-picker-btn">
                                    <span class="btn btn-outline-secondary"><?= h(t('파일 선택', 'Choose file')) ?></span>
                                    <input id="abstractFile" name="abstract_file" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                </span>
                                <span class="file-picker-name" id="abstractFileName"><?= h(t('선택된 파일 없음', 'No file selected')) ?></span>
                            </div>
                            <div class="form-text"><?= h(t('문서 파일 1개 (Word: DOC·DOCX, PDF), 최대 10MB. 마감 전에는 파일을 교체할 수 있습니다.', 'One document file (Word: DOC·DOCX, PDF), up to 10MB. You can replace the file before the deadline.')) ?></div>
                            <?php if ($editing && $form['file_name'] !== '') { ?>
                            <div class="form-text"><?= h(t('현재 파일: ', 'Current file: ')) ?><?= h($form['file_name']) ?></div>
                            <?php } ?>
                            <p class="form-text mb-0"><?= h(t('초록은 문서 파일(Word 또는 PDF)로 업로드해 주세요. 초록 본문은 300단어 이하로 작성해 주세요.', 'Please upload your abstract as a document file (Word or PDF). The abstract body should be 300 words or fewer.')) ?></p>
                            <div id="abstractFileHint" class="form-text small is-invalid d-none"><?= h(t('초록 파일을 첨부해 주세요.', 'Attach the abstract file.')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="form-label small fw-bold"><?= h(t('초록 양식 제공', 'Abstract Template')) ?></div>
                            <a class="btn btn-sm ksers-outline" href="file/KSERS2027_Abstract_Template.docx" download="KSERS2027_Abstract_Template.docx"><?= h(t('양식 받기', 'Download template')) ?></a>
                        </div>
                    </div>
                    <h6 class="fw-bold text-success mt-4 mb-3"><i class="bi bi-check2-square me-2"></i><?= h(t('동의', 'Consent')) ?></h6>
                    <div class="card bg-light border-0 p-3 mb-2" id="abstractConsent">
                        <div class="join-consent-item mb-3">
                            <input class="form-check-input" type="checkbox" id="coauthorAgree" name="coauthor_agree" value="1"<?= (isset($_POST['coauthor_agree']) && $_POST['coauthor_agree'] === '1') ? ' checked' : '' ?>>
                            <label class="join-consent-badge text-danger" for="coauthorAgree"><?= h(t('[필수]', '[Required]')) ?></label>
                            <div class="join-consent-body">
                                <div class="join-consent-head">
                                    <label class="form-check-label small text-danger" for="coauthorAgree"><?= h(t('공동저자 동의 확인', 'Co-author Consent')) ?></label>
                                </div>
                                <p class="join-consent-note"><?= h(t('모든 공동저자가 초록 제출과 정보 제공에 동의했음을 확인합니다.', 'I confirm that all co-authors have agreed to the submission and to the provision of their information.')) ?></p>
                            </div>
                        </div>
                        <div class="join-consent-item mb-3">
                            <input class="form-check-input" type="checkbox" id="privacyAgree" name="privacy_agree" value="1"<?= (isset($_POST['privacy_agree']) && $_POST['privacy_agree'] === '1') ? ' checked' : '' ?>>
                            <label class="join-consent-badge text-danger" for="privacyAgree"><?= h(t('[필수]', '[Required]')) ?></label>
                            <div class="join-consent-body">
                                <div class="join-consent-head">
                                    <label class="form-check-label small text-danger" for="privacyAgree"><?= h(t('개인정보 수집·이용 동의', 'Privacy Consent')) ?></label>
                                    <button class="join-terms-link ms-2" type="button" data-terms="privacy"><?= h(t('개인정보처리방침 보기', 'View privacy policy')) ?></button>
                                </div>
                                <p class="join-consent-note"><?= h(t('수집 목적', 'Purpose')) ?>: <?= h(t('초록 접수·심사, 발표 일정 안내', 'Abstract submission and review, presentation schedule notices')) ?><br><?= h(t('수집 항목', 'Items')) ?>: <?= h(t('(필수) 저자 성명·소속, 발표자 국가·이메일, 교신저자 이메일, 저자 역할, 초록 정보(발표 형태, 분야, 제목, 초록 파일), 영상 파일(영상 발표에 한함) (선택) 저자 부서, 발표자·교신저자 외 저자의 국가·이메일', '(Required) Authors\' names and affiliations, presenter\'s country and e-mail, corresponding author\'s e-mail, author roles, abstract information (presentation type, topic, title, abstract file), video file (video presentations only) (Optional) Authors\' departments; country and e-mail of authors other than the presenter and corresponding author')) ?><br><?= h(t('보유기간', 'Retention')) ?>: <?= h(t('KSERS 2027 학술대회 종료 후 3개월 이내 파기 (법령에 따라 보존이 필요한 정보는 해당 기간)', 'Destroyed within 3 months after the end of KSERS 2027 (information that must be kept under applicable laws is kept for the required period)')) ?><br><?= h(t('동의 거부 권리', 'Right to refuse')) ?>: <?= h(t('동의를 거부할 수 있으나, 거부하면 초록 제출이 제한됩니다.', 'You may refuse to consent, but you will not be able to submit an abstract.')) ?></p>
                            </div>
                        </div>
                        <div class="join-consent-item">
                            <input class="form-check-input" type="checkbox" id="publicationAgree" name="publication_agree" value="1"<?= (isset($_POST['publication_agree']) && $_POST['publication_agree'] === '1') ? ' checked' : '' ?>>
                            <label class="join-consent-badge text-danger" for="publicationAgree"><?= h(t('[필수]', '[Required]')) ?></label>
                            <div class="join-consent-body">
                                <div class="join-consent-head">
                                    <label class="form-check-label small text-danger" for="publicationAgree"><?= h(t('초록 게재 동의', 'Publication Consent')) ?></label>
                                </div>
                                <p class="join-consent-note"><?= h(t('채택된 초록은 프로그램북, 초록집 및 웹사이트에 게재될 수 있습니다.', 'Accepted abstracts may be published in the program book, abstract book and website.')) ?></p>
                            </div>
                        </div>
                    </div>
                    <div id="abstractConsentHint" class="form-text small is-invalid d-none"><?= h(t('필수사항을 체크해 주세요.', 'Please check the required items.')) ?></div>
                    <?php if ($editing && (int)$form['seq'] > 0) { ?>
                    <h6 class="fw-bold text-success mt-4 mb-3"><i class="bi bi-camera-video me-2"></i><?= h(t('영상 파일 연결', 'Linked video files')) ?></h6>
                    <?php if (!$linkedVideos) { ?>
                    <p class="text-muted mb-0"><?= h(t('연결된 영상이 없습니다.', 'No video is linked.')) ?> <a href="materials.php"><?= h(t('바로가기', 'Go')) ?></a></p>
                    <?php } ?>
                    <?php foreach ($linkedVideos as $video) {
                        $videoWhen = $video['update_date'] ?: $video['reg_date'];
                    ?>
                    <div class="border rounded p-3 mb-2 bg-white d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <div class="fw-bold"><?= h($video['original_name']) ?></div>
                            <div class="small text-muted"><?= h(ksers_submission_label($video['kind'])) ?> · <?= h($videoWhen) ?></div>
                        </div>
                        <a class="btn btn-sm ksers-outline" href="materials.php"><?= h(t('바로가기', 'Go')) ?></a>
                    </div>
                    <?php } ?>
                    <?php } ?>
                    <div class="d-flex justify-content-end gap-2 abstract-form-actions">
                        <a class="btn ksers-outline" href="abstract.php"><?= h(t('취소', 'Cancel')) ?></a>
                        <?php if ((int)$form['seq'] > 0) { ?>
                        <button class="btn btn-outline-danger" type="button" id="abstractEditDelete" data-delete-abstract="<?= (int)$form['seq'] ?>"><?= h(t('삭제', 'Delete')) ?></button>
                        <?php } ?>
                        <button class="btn btn-primary-custom" type="button" id="abstractSaveButton"><?= h(t('저장', 'Save / Submit')) ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>

        <?php if ($abstracts) { ?>
        <div class="abstract-deadline" role="note">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <p>
                <?= h(t('초록 접수 마감: ', 'Abstract deadline: ')) ?>
                <strong><?= h(t('2027년 1월 14일(목)', 'January 14 (Thu), 2027')) ?></strong>
                <?= h(t(' · 마감 전까지 ', '. You may ')) ?>
                <strong><?= h(t('수정·삭제할 수 있습니다.', 'edit or delete your abstract until then.')) ?></strong>
                <?= h(t(' 영상 발표 초록은 영상 제출 메뉴에서 수정합니다.', ' Video abstracts are edited in Video Submission.')) ?>
            </p>
        </div>
        <?php } ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold"><?= h(t('초록 목록', 'My Abstracts')) ?></h5>
                <a class="btn btn-primary-custom" href="abstract.php?add=1"><?= h(t('+ 새 초록 제출', '+ Submit a new abstract')) ?></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive abstract-list-scroll">
                    <table class="table align-middle mb-0 abstract-list-table">
                            <thead class="table-light">
                                <tr>
                                <th><?= h(t('접수번호', 'Receipt No.')) ?></th>
                                <th><?= h(t('발표 희망 형태', 'Preferred type')) ?></th>
                                    <th><?= h(t('제목', 'Title')) ?></th>
                                    <th><?= h(t('분야', 'Topic')) ?></th>
                                <th><?= h(t('영상', 'Video')) ?></th>
                                    <th><?= h(t('심사 상태', 'Review status')) ?></th>
                                <th><?= h(t('관리', 'Manage')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$abstracts) { ?>
                                <tr>
                                <td colspan="7" class="text-center text-muted py-4"><?= h(t('등록된 초록이 없습니다.', 'No abstracts yet.')) ?></td>
                                </tr>
                                <?php } ?>
                            <?php foreach ($abstracts as $row) {
                                $canChange = $row['status'] === 'received' && $abstractDeadlineOpen;
                                $abstractNo = sprintf('A%d-%04d', $year, (int)$row['seq']);
                                $memberStatus = ksers_abstract_member_status($row['status']);
                                $videoSize = isset($videoByAbstract[(int)$row['seq']]) ? $videoByAbstract[(int)$row['seq']] : 0;
                            ?>
                            <tr>
                                <td class="abstract-no"><?= h($abstractNo) ?></td>
                                <td><span class="abstract-type <?= h($presentationBadge($row['presentation_type'])) ?>"><?= h($presentationLabel($row['presentation_type'])) ?></span></td>
                                <td class="abstract-title"><?= h($row['title']) ?></td>
                                <td><?= h($topicLabel($row['topic'])) ?></td>
                                <td>
                                    <?php if ($videoSize > 0) { ?>
                                    <div class="abstract-video-done"><?= h(t('제출 완료', 'Submitted')) ?></div>
                                    <div class="abstract-video-size"><?= h($fileSizeLabel($videoSize)) ?></div>
                                        <?php } else { ?>
                                    <span class="abstract-dash">-</span>
                                    <?php } ?>
                                </td>
                                <td><span class="abstract-status <?= h($memberStatus['class']) ?>"><?= h($memberStatus['label']) ?></span></td>
                                <td>
                                    <div class="abstract-row-actions">
                                        <button class="abstract-action is-preview" type="button" onclick="ksersOpenPreview(<?= (int)$row['seq'] ?>)"><?= h(t('미리보기', 'Preview')) ?></button>
                                        <?php if ($canChange) { ?>
                                        <a class="abstract-action is-edit" href="abstract.php?edit=<?= (int)$row['seq'] ?>"><i class="bi bi-pencil"></i> <?= h(t('수정', 'Edit')) ?></a>
                                        <button class="abstract-action is-delete" type="button" data-delete-abstract="<?= (int)$row['seq'] ?>"><?= h(t('삭제', 'Delete')) ?></button>
                                        <?php } ?>
                                    </div>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
            </div>
                    </div>
        <form method="post" action="abstract.php" id="abstractDeleteForm" class="d-none">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="abs[]" id="abstractDeleteSeq" value="">
                </form>
</div>
<div class="modal fade" id="abstractSubmitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('저장', 'Save / Submit')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0"><?= h(t('2027년 1월 14일(목)까지 초록을 수정할 수 있습니다.', 'You may edit your abstract until January 14 (Thu), 2027.')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn ksers-outline" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary-custom" id="abstractSubmitConfirm"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="abstractDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('삭제', 'Delete')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0"><?= h(t('삭제한 초록은 복구할 수 없습니다. 이 초록을 삭제할까요?', 'Deleted abstracts cannot be recovered. Do you want to delete this abstract?')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn ksers-outline" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-outline-danger" id="abstractDeleteConfirm"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="abstractNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('입력을 확인해 주세요', 'Check the form')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <ul class="mb-0" id="abstractNoticeList"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary-custom" data-bs-dismiss="modal"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="authorLimitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('저자 추가', 'Authors (Add)')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><?= h(t('저자는 최대 20명까지 입력할 수 있습니다. 20명을 넘는 경우 사무국(ksers2027@ksers.kr)으로 문의해 주세요.', 'Up to 20 authors can be entered. If there are more than 20 authors, please contact the secretariat (ksers2027@ksers.kr).')) ?></p>
                <p class="mb-2"><?= h(t('저자 정보를 정확하게 입력해 주시고, 초록 관련 모든 정보는 제출자, 발표자, 교신저자의 이메일 주소로 발송될 예정임을 알려드립니다.', 'Please enter author information accurately. All information regarding the abstract will be sent to the e-mail addresses of the submitter, presenting author and corresponding author.')) ?></p>
                <p class="mb-0"><?= h(t('첫 번째 저자의 이름을 먼저 입력해야 합니다. 이름은 여기에 표시된 대로 인쇄되므로 저자 목록의 순서를 확인하십시오.', 'Enter the first author first. Names will be printed exactly as entered here, so please check the order of the author list.')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary-custom" data-bs-dismiss="modal"><?= h(t('확인', 'OK')) ?></button>
            </div>
            </div>
        </div>
</div>
<script>
const abstractAuthors = <?= json_encode($formAuthors, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const abstractCountries = <?= json_encode($countryOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
(function () {
    const addButton = document.getElementById('authorAdd');
    const table = document.getElementById('authorTable');
    const store = document.getElementById('authorStore');
    if (!addButton || !table || !store) {
        return;
    }
    let authors = Array.isArray(abstractAuthors) ? abstractAuthors.map(function (row) {
        return {
            first: row.first || '',
            last: row.last || '',
            affiliation: row.affiliation || '',
            department: row.department || '',
            country: Number(row.country) || 0,
            email: row.email || '',
            presenting: Number(row.presenting) === 1 ? 1 : 0,
            corresponding: Number(row.corresponding) === 1 ? 1 : 0,
            mentor: Number(row.mentor) === 1 ? 1 : 0
        };
    }) : [];
    const hint = document.getElementById('authorAddHint');
    const empty = document.getElementById('authorEmpty');
    const countrySelect = document.getElementById('authorCountry');
    const emailInput = document.getElementById('authorEmail');
    const countryStar = document.getElementById('authorCountryStar');
    const emailStar = document.getElementById('authorEmailStar');
    const countryHelp = document.getElementById('authorCountryHelp');
    const emailHelp = document.getElementById('authorEmailHelp');
    const messages = {
        name: <?= json_encode(t('영문 성명과 소속을 입력해 주세요.', 'Enter the English name and affiliation.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        role: <?= json_encode(t('역할을 선택해 주세요. Presenting, Corresponding, Mentor 중 하나 이상을 선택해야 추가됩니다.', 'Choose a role. Select Presenting, Corresponding, Mentor, or an allowed combination before adding the author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        mentorConflict: <?= json_encode(t('발표자와 Mentor는 동시에 지정할 수 없습니다.', 'The presenting author and the mentor must be different people.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        needPresenting: <?= json_encode(t('발표자를 한 명 지정해 주세요.', 'Choose one presenting author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        needCorresponding: <?= json_encode(t('교신저자를 한 명 이상 지정해 주세요.', 'Choose at least one corresponding author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        oneMentor: <?= json_encode(t('Mentor는 한 명만 지정할 수 있습니다.', 'Choose one mentor.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        needMentor: <?= json_encode(t('Mentor-Mentee Video는 Mentor를 한 명 지정해 주세요.', 'Mentor-Mentee Video needs one mentor.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        mentorCleared: <?= json_encode(t('Mentor는 Mentor-Mentee Video에서만 지정할 수 있습니다. 역할이 없는 저자를 다시 지정해 주세요.', 'Mentor can be assigned only for Mentor-Mentee Video. Choose another role for an author who no longer has one.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        country: <?= json_encode(t('발표자의 국가는 필수입니다.', 'The presenting author needs a country.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        email: <?= json_encode(t('발표자와 교신저자의 이메일을 입력해 주세요.', 'Enter an email for the presenting author and corresponding author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        overseas: <?= json_encode(t('해외 국가를 선택한 저자는 이메일이 필수입니다.', 'An author outside Korea needs an email address.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        one: <?= json_encode(t('발표자는 한 명만 선택할 수 있습니다.', 'Choose one presenting author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        remove: <?= json_encode(t('삭제', 'Remove'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        helpPresenting: <?= json_encode(t('발표자는 국가와 이메일이 필수입니다.', 'The presenting author needs a country and an email address.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        helpCorresponding: <?= json_encode(t('교신저자는 이메일이 필수입니다. 국가는 선택입니다.', 'The corresponding author needs an email address. Country is optional.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        helpOverseas: <?= json_encode(t('해외 국가를 고르면 이메일이 필수입니다.', 'Choosing a country outside Korea makes the email address required.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        helpOptional: <?= json_encode(t('공저자는 국가와 이메일이 선택입니다. 발표자를 선택하면 둘 다 필수가 됩니다.', 'Country and email are optional for a co-author. Both are required for the presenting author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    };
    function showNotice(lines) {
        const list = document.getElementById('abstractNoticeList');
        const modal = document.getElementById('abstractNoticeModal');
        if (!list || !modal || !window.bootstrap) {
            window.alert(lines.join('\n'));
            return;
        }
        list.innerHTML = '';
        lines.forEach(function (line) {
            const item = document.createElement('li');
            item.textContent = line;
            list.appendChild(item);
        });
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    function selectedKorea() {
        const option = countrySelect.options[countrySelect.selectedIndex];
        return !!(option && option.getAttribute('data-korea') === '1');
    }
    function emailIsRequired(presenting, corresponding) {
        const overseas = countrySelect.value !== '' && !selectedKorea();
        return presenting || corresponding || overseas;
    }
    function syncAuthorRequired() {
        const presenting = document.getElementById('authorPresenting').checked;
        const corresponding = document.getElementById('authorCorresponding').checked;
        const needCountry = presenting;
        const needEmail = emailIsRequired(presenting, corresponding);
        countryStar.classList.toggle('d-none', !needCountry);
        emailStar.classList.toggle('d-none', !needEmail);
        countrySelect.classList.toggle('is-cond-required', needCountry && countrySelect.value === '');
        emailInput.classList.toggle('is-cond-required', needEmail && emailInput.value.trim() === '');
        if (countryHelp) {
            if (presenting) {
                countryHelp.textContent = messages.helpPresenting;
            } else if (corresponding) {
                countryHelp.textContent = messages.helpCorresponding;
            } else if (countrySelect.value !== '' && !selectedKorea()) {
                countryHelp.textContent = messages.helpOverseas;
            } else {
                countryHelp.textContent = messages.helpOptional;
            }
        }
        if (emailHelp) {
            emailHelp.textContent = needEmail ? messages.email : messages.helpOptional;
        }
    }
    function countryName(id) {
        const found = abstractCountries.find(function (row) { return Number(row.id) === Number(id); });
        return found ? found.name : '';
    }
    function showHint(text) {
        if (!hint) {
            return;
        }
        hint.textContent = text;
        hint.classList.remove('d-none');
    }
    function hidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }
    function render() {
        table.innerHTML = '';
        store.innerHTML = '';
        authors.forEach(function (author, index) {
            const roles = [];
            if (author.presenting) {
                roles.push('Presenting');
            }
            if (author.corresponding) {
                roles.push('Corresponding');
            }
            if (author.mentor) {
                roles.push('Mentor');
            }
            const row = document.createElement('tr');
            row.innerHTML = '<td></td><td></td><td></td><td></td><td></td><td></td><td class="text-nowrap"></td>';
            row.children[0].textContent = (author.first + ' ' + author.last).trim();
            row.children[1].textContent = author.affiliation;
            row.children[2].textContent = author.department;
            row.children[3].textContent = countryName(author.country);
            row.children[4].textContent = author.email;
            row.children[5].textContent = roles.join(', ');
            const up = document.createElement('button');
            up.type = 'button';
            up.className = 'btn btn-sm btn-outline-secondary me-1';
            up.textContent = '↑';
            up.disabled = index === 0;
            up.addEventListener('click', function () {
                const previous = authors[index - 1];
                authors[index - 1] = authors[index];
                authors[index] = previous;
                render();
            });
            const down = document.createElement('button');
            down.type = 'button';
            down.className = 'btn btn-sm btn-outline-secondary me-1';
            down.textContent = '↓';
            down.disabled = index === authors.length - 1;
            down.addEventListener('click', function () {
                const next = authors[index + 1];
                authors[index + 1] = authors[index];
                authors[index] = next;
                render();
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.textContent = messages.remove;
            remove.addEventListener('click', function () {
                authors.splice(index, 1);
                render();
            });
            row.children[6].append(up, down, remove);
            table.appendChild(row);
            store.append(
                hidden('author_first[]', author.first),
                hidden('author_last[]', author.last),
                hidden('author_affiliation[]', author.affiliation),
                hidden('author_department[]', author.department),
                hidden('author_country[]', String(author.country || 0)),
                hidden('author_email[]', author.email),
                hidden('author_presenting[]', author.presenting ? '1' : '0'),
                hidden('author_corresponding[]', author.corresponding ? '1' : '0'),
                hidden('author_mentor[]', author.mentor ? '1' : '0')
            );
        });
        if (empty) {
            empty.classList.toggle('d-none', authors.length > 0);
        }
        const authorListHint = document.getElementById('authorListHint');
        if (authorListHint && authors.length > 0) {
            authorListHint.classList.add('d-none');
        }
        const authorTable = table.closest('table');
        if (authorTable) {
            authorTable.classList.toggle('is-filled', authors.length > 0);
        }
    }
    function authorProblems(author) {
        const problems = [];
        const presenting = author.presenting === 1;
        const corresponding = author.corresponding === 1;
        const mentor = author.mentor === 1;
        const overseas = author.country > 0 && !selectedKorea();
        if (presenting && mentor) {
            problems.push(messages.mentorConflict);
        } else if (!presenting && !corresponding && !mentor) {
            problems.push(messages.role);
        }
        if (author.first === '' || author.last === '' || author.affiliation === '') {
            problems.push(messages.name);
        }
        if (presenting && authors.some(function (row) { return row.presenting; })) {
            problems.push(messages.one);
        }
        if (mentor && authors.some(function (row) { return row.mentor; })) {
            problems.push(messages.oneMentor);
        }
        if (presenting && !author.country) {
            problems.push(messages.country);
        }
        const needEmail = presenting || corresponding || overseas;
        if (needEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(author.email)) {
            problems.push(overseas && !presenting && !corresponding ? messages.overseas : messages.email);
        }
        return problems;
    }
    document.getElementById('authorPresenting').addEventListener('change', function () {
        if (this.checked) {
            document.getElementById('authorMentor').checked = false;
        }
        syncAuthorRequired();
    });
    document.getElementById('authorMentor').addEventListener('change', function () {
        if (this.checked) {
            document.getElementById('authorPresenting').checked = false;
        }
        syncAuthorRequired();
    });
    document.getElementById('authorCorresponding').addEventListener('change', syncAuthorRequired);
    countrySelect.addEventListener('change', syncAuthorRequired);
    emailInput.addEventListener('input', syncAuthorRequired);
    addButton.addEventListener('click', function () {
        if (hint) {
            hint.classList.add('d-none');
        }
        if (authors.length >= 20) {
            if (window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('authorLimitModal')).show();
            }
            return;
        }
        const presenting = document.getElementById('authorPresenting').checked;
        const corresponding = document.getElementById('authorCorresponding').checked;
        const mentor = document.getElementById('authorMentor').checked;
        const author = {
            first: document.getElementById('authorFirst').value.trim(),
            last: document.getElementById('authorLast').value.trim(),
            affiliation: document.getElementById('authorAffiliation').value.trim(),
            department: document.getElementById('authorDepartment').value.trim(),
            country: Number(countrySelect.value) || 0,
            email: emailInput.value.trim(),
            presenting: presenting ? 1 : 0,
            corresponding: corresponding ? 1 : 0,
            mentor: mentor ? 1 : 0
        };
        const problems = authorProblems(author);
        if (problems.length) {
            syncAuthorRequired();
            showNotice(problems);
            return;
        }
        authors.push(author);
        ['authorFirst', 'authorLast', 'authorAffiliation', 'authorDepartment', 'authorEmail'].forEach(function (id) {
            document.getElementById(id).value = '';
        });
        countrySelect.value = '';
        document.getElementById('authorPresenting').checked = false;
        document.getElementById('authorCorresponding').checked = false;
        document.getElementById('authorMentor').checked = false;
        syncAuthorRequired();
        render();
    });
    const abstractForm = document.getElementById('abstractForm');
    const saveButton = document.getElementById('abstractSaveButton');
    const titleEmptyMessage = <?= json_encode(t('제목을 입력해 주세요.', 'Enter the title.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const titleLongMessage = <?= json_encode(t('제목은 150자까지 입력할 수 있습니다.', 'The title can be up to 150 characters.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const authorNeedMessage = <?= json_encode(t('저자를 한 명 이상 추가해 주세요.', 'Add at least one author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    function authorRoleProblems() {
        let presenting = 0;
        let corresponding = 0;
        let mentor = 0;
        const problems = [];
        authors.forEach(function (author) {
            if (author.presenting && author.mentor) {
                problems.push(messages.mentorConflict);
            }
            if (author.presenting) {
                presenting += 1;
            }
            if (author.corresponding) {
                corresponding += 1;
            }
            if (author.mentor) {
                mentor += 1;
            }
        });
        if (presenting !== 1) {
            problems.push(presenting > 1 ? messages.one : messages.needPresenting);
        }
        if (corresponding < 1) {
            problems.push(messages.needCorresponding);
        }
        const needsMentor = mentorTypeSelected();
        if (needsMentor && mentor !== 1) {
            problems.push(mentor > 1 ? messages.oneMentor : messages.needMentor);
        } else if (!needsMentor && mentor > 1) {
            problems.push(messages.oneMentor);
        }
        return problems.filter(function (line, index, list) { return list.indexOf(line) === index; });
    }
    function mentorTypeSelected() {
        const presentation = document.getElementById('presentation');
        return !!(presentation && presentation.value === 'mentor');
    }
    function syncMentorRole(clearAuthors) {
        const row = document.getElementById('authorMentorRow');
        const box = document.getElementById('authorMentor');
        const show = mentorTypeSelected();
        if (row) {
            row.classList.toggle('d-none', !show);
        }
        if (!show && box) {
            box.checked = false;
        }
        if (!show && clearAuthors) {
            let lostRole = false;
            authors.forEach(function (author) {
                if (author.mentor && !author.presenting && !author.corresponding) {
                    lostRole = true;
                }
                author.mentor = 0;
            });
            render();
            if (lostRole) {
                showNotice([messages.mentorCleared]);
            }
        }
    }
    const presentationSelect = document.getElementById('presentation');
    if (presentationSelect) {
        presentationSelect.addEventListener('change', function () {
            syncMentorRole(true);
        });
    }
    function markField(field, hint, invalid) {
        if (field) {
            field.classList.toggle('is-invalid', invalid);
        }
        if (hint) {
            hint.classList.toggle('d-none', !invalid);
        }
    }
    function validateAbstractSave() {
        const presentation = document.getElementById('presentation');
        const topic = document.getElementById('topic');
        const title = document.getElementById('title');
        const file = document.getElementById('abstractFile');
        const titleHint = document.getElementById('titleHint');
        const filePicker = file ? file.closest('.file-picker') : null;
        const presentationInvalid = !presentation || presentation.value === '';
        const topicInvalid = !topic || topic.value === '';
        const titleText = title ? title.value.trim() : '';
        const titleInvalid = titleText === '' || Array.from(titleText).length > 150;
        if (titleHint) {
            titleHint.textContent = titleText === '' ? titleEmptyMessage : titleLongMessage;
        }
        const fileInvalid = !!(file && abstractForm.getAttribute('data-has-file') !== '1' && (!file.files || file.files.length < 1));
        const consents = ['coauthorAgree', 'privacyAgree', 'publicationAgree'].map(function (id) {
            return document.getElementById(id);
        }).filter(Boolean);
        const consentInvalid = consents.some(function (box) { return !box.checked; });
        consents.forEach(function (box) {
            box.classList.toggle('is-invalid', !box.checked);
        });
        markField(presentation, document.getElementById('presentationHint'), presentationInvalid);
        markField(topic, document.getElementById('topicHint'), topicInvalid);
        markField(title, titleHint, titleInvalid);
        markField(file, document.getElementById('abstractFileHint'), fileInvalid);
        if (filePicker) {
            filePicker.classList.toggle('is-invalid', fileInvalid);
        }
        const consentHint = document.getElementById('abstractConsentHint');
        if (consentHint) {
            consentHint.classList.toggle('d-none', !consentInvalid);
        }
        const authorsMissing = authors.length < 1;
        const authorListHint = document.getElementById('authorListHint');
        if (authorListHint) {
            authorListHint.classList.toggle('d-none', !authorsMissing);
        }
        return {
            fieldsInvalid: presentationInvalid || topicInvalid || titleInvalid || fileInvalid || consentInvalid,
            authorsMissing: authorsMissing
        };
    }
    function openSubmitModal() {
        const modal = document.getElementById('abstractSubmitModal');
        if (!modal || !window.bootstrap) {
            if (window.ksersAllowLeave) {
                window.ksersAllowLeave();
            }
            abstractForm.submit();
            return;
        }
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    if (abstractForm && saveButton) {
        abstractForm.addEventListener('submit', function (event) {
            event.preventDefault();
            saveButton.click();
        });
        function firstMissingAnchor() {
            const order = [
                ['presentation', 'presentationHint'],
                ['topic', 'topicHint'],
                ['title', 'titleHint'],
                ['authorListHint', 'authorListHint'],
                ['abstractFile', 'abstractFileHint'],
                ['coauthorAgree', 'abstractConsentHint']
            ];
            for (let index = 0; index < order.length; index++) {
                const hint = document.getElementById(order[index][1]);
                if (!hint || hint.classList.contains('d-none')) {
                    continue;
                }
                if (order[index][0] === 'abstractFile') {
                    return document.querySelector('.file-picker') || document.getElementById('abstractFile');
                }
                return document.getElementById(order[index][0]) || hint;
            }
            return null;
        }
        saveButton.addEventListener('click', function () {
            const result = validateAbstractSave();
            const anchor = firstMissingAnchor();
            if (anchor) {
                const target = anchor.closest('.col-12, .mb-4, #abstractConsent') || anchor;
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof anchor.focus === 'function' && anchor.id !== 'authorListHint') {
                    anchor.focus({ preventScroll: true });
                }
            }
            if (result.fieldsInvalid) {
                return;
            }
            if (result.authorsMissing) {
                showNotice([authorNeedMessage]);
                return;
            }
            const roleProblems = authorRoleProblems();
            if (roleProblems.length) {
                showNotice(roleProblems);
                return;
            }
            openSubmitModal();
        });
        ['presentation', 'topic', 'title'].forEach(function (id) {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }
            field.addEventListener('input', function () {
                field.classList.remove('is-invalid');
                const hint = document.getElementById(id + 'Hint');
                if (hint) {
                    hint.classList.add('d-none');
                }
            });
            field.addEventListener('change', function () {
                field.classList.remove('is-invalid');
                const hint = document.getElementById(id + 'Hint');
                if (hint) {
                    hint.classList.add('d-none');
                }
            });
        });
        ['coauthorAgree', 'privacyAgree', 'publicationAgree'].forEach(function (id) {
            const box = document.getElementById(id);
            if (!box) {
                return;
            }
            box.addEventListener('change', function () {
                box.classList.remove('is-invalid');
                const stillMissing = ['coauthorAgree', 'privacyAgree', 'publicationAgree'].some(function (consentId) {
                    const consent = document.getElementById(consentId);
                    return consent && !consent.checked;
                });
                const consentHint = document.getElementById('abstractConsentHint');
                if (consentHint && !stillMissing) {
                    consentHint.classList.add('d-none');
                }
            });
        });
        const submitConfirm = document.getElementById('abstractSubmitConfirm');
        if (submitConfirm) {
            submitConfirm.addEventListener('click', function () {
                if (window.ksersAllowLeave) {
                    window.ksersAllowLeave();
                }
                abstractForm.submit();
            });
        }
    }
    const abstractFile = document.getElementById('abstractFile');
    const abstractFileName = document.getElementById('abstractFileName');
    const noFileLabel = <?= json_encode(t('선택된 파일 없음', 'No file selected'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    if (abstractFile && abstractFileName) {
        abstractFile.addEventListener('change', function () {
            const picked = abstractFile.files && abstractFile.files[0] ? abstractFile.files[0].name : '';
            abstractFileName.textContent = picked !== '' ? picked : noFileLabel;
            if (picked !== '') {
                abstractFile.classList.remove('is-invalid');
                const picker = abstractFile.closest('.file-picker');
                const hint = document.getElementById('abstractFileHint');
                if (picker) {
                    picker.classList.remove('is-invalid');
                }
                if (hint) {
                    hint.classList.add('d-none');
                }
            }
        });
    }
    document.querySelectorAll('#abstractConsent [data-terms]').forEach(function (button) {
        button.addEventListener('click', function () {
            const lang = document.documentElement.lang === 'en' ? 'en' : 'ko';
            const width = 768;
            const height = 860;
            const left = Math.max(0, Math.round((window.screen.width - width) / 2));
            const top = Math.max(0, Math.round((window.screen.height - height) / 2));
            window.open(
                'terms.php?lang=' + lang + '&part=' + encodeURIComponent(button.getAttribute('data-terms')),
                'ksersTerms',
                'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
            );
        });
    });
    syncAuthorRequired();
    syncMentorRole(false);
    render();
})();
(function () {
    const deleteForm = document.getElementById('abstractDeleteForm');
    const deleteSeq = document.getElementById('abstractDeleteSeq');
    const deleteModal = document.getElementById('abstractDeleteModal');
    const deleteConfirm = document.getElementById('abstractDeleteConfirm');
    if (!deleteForm || !deleteSeq || !deleteModal || !deleteConfirm) {
        return;
    }
    document.querySelectorAll('[data-delete-abstract]').forEach(function (button) {
        button.addEventListener('click', function () {
            deleteSeq.value = button.getAttribute('data-delete-abstract') || '';
            if (window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(deleteModal).show();
            }
        });
    });
    deleteConfirm.addEventListener('click', function () {
        if (deleteSeq.value === '') {
            return;
        }
        if (window.ksersAllowLeave) {
            window.ksersAllowLeave();
        }
        deleteForm.submit();
    });
})();
function ksersOpenPreview(seq) {
    const width = 768;
    const height = 900;
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    window.open(
        'abstract_preview.php?seq=' + seq,
        'ksersAbstractPreview',
        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
    );
}
</script>

<?php include 'lib/footer.php'; ?>
