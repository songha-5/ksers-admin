<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';



$board  = $_POST['board'];
$mode   = $_POST['mode'];
$seq    = isset($_POST['seq']) ? $_POST['seq'] : '';

if($board == 'member') {

    $member_type        = isset($_POST['member_type']) ? (int)$_POST['member_type'] : 0;
    $email              = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password           = isset($_POST['password']) ? $_POST['password'] : '';
    $name_ko            = isset($_POST['name_ko']) ? trim($_POST['name_ko']) : '';
    $first_name_eng     = isset($_POST['first_name_eng']) ? trim($_POST['first_name_eng']) : '';
    $last_name_eng      = isset($_POST['last_name_eng']) ? trim($_POST['last_name_eng']) : '';
    $affiliation_ko     = isset($_POST['affiliation_ko']) ? trim($_POST['affiliation_ko']) : '';
    $affiliation_eng    = isset($_POST['affiliation_eng']) ? trim($_POST['affiliation_eng']) : '';
    $mobile             = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $country            = isset($_POST['country']) ? strtoupper(trim((string)$_POST['country'])) : '';
    if (!preg_match('/^[A-Z]{2}$/', $country)) {
        $country = '';
    }
    $textLength = function ($value) {
        $count = preg_match_all('/./u', (string)$value, $unused);
        return $count === false ? 0 : $count;
    };

    if($mode == 'input') {
        $confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
        $memberError = t('저장에 실패했습니다.', 'Could not save.');
        $member_type = 0;
        $agreeTerms = isset($_POST['terms_agree']) && trim((string)$_POST['terms_agree']) === '1';
        $agreePrivacy = isset($_POST['privacy_agree']) && trim((string)$_POST['privacy_agree']) === '1';
        $agreeEmail = (isset($_POST['email_agree']) && trim((string)$_POST['email_agree']) === '1') ? 1 : 0;
        $countryOk = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
        $countryOk->execute([$country]);
        $joinYear = ksers_site_year($pdo);
        $duplicate = $pdo->prepare("SELECT seq FROM member_info WHERE email = ? AND year = ?");
        $duplicate->execute([$email, $joinYear]);

        $passwordSpecial = preg_match('/[^0-9A-Za-z가-힣ㄱ-ㅎㅏ-ㅣ\s]/u', $password);
        $countryRow = $countryOk->fetch(PDO::FETCH_ASSOC);
        $countryProfile = ksers_country_profile($countryRow ? $countryRow : []);
        $salutation = isset($_POST['salutation']) ? trim((string)$_POST['salutation']) : '';
        $affiliationType = isset($_POST['affiliation_type']) ? trim((string)$_POST['affiliation_type']) : '';
        $department = isset($_POST['department']) ? trim((string)$_POST['department']) : '';
        $affiliationTypes = ksers_affiliation_types();
        $telephone = ksers_phone_store($countryProfile['dial'], isset($_POST['telephone']) ? $_POST['telephone'] : '');
        $mobile = ksers_phone_store($countryProfile['dial'], $mobile);
        $salutations = ['', 'Prof.', 'Dr.', 'Mr.', 'Ms.'];
        if ($countryRow && $countryProfile['korea']) {
            $first_name_eng = '';
            $last_name_eng = '';
        } elseif ($countryRow) {
            $name_ko = '';
            $affiliationType = '';
            $affiliation_ko = '';
        }
        if (!$countryRow) {
            $memberError = t('국가를 다시 선택해 주세요.', 'Choose the country again.');
        } elseif (!$agreeTerms || !$agreePrivacy) {
            $memberError = t('필수사항을 체크해 주세요.', 'Please check the required items.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $textLength($email) > 45) {
            $memberError = t('이메일 주소를 확인해 주세요.', 'Check the email address.');
        } elseif ($textLength($password) < 8 || !$passwordSpecial || $password !== $confirm) {
            $memberError = t('비밀번호는 8자 이상이며 특수문자를 포함하고, 확인 값과 같아야 합니다.', 'Use at least 8 characters, including a special character, and make the passwords match.');
        } elseif ($countryProfile['korea'] && $name_ko === '') {
            $memberError = t('성명(국문)을 입력해 주세요.', 'Enter your Korean name.');
        } elseif (!$countryProfile['korea'] && ($first_name_eng === '' || $last_name_eng === '')) {
            $memberError = t('영문 이름과 성을 입력해 주세요.', 'Enter your first and last name in English.');
        } elseif ($mobile === null || $telephone === null) {
            $memberError = t('하이픈(-) 2개를 넣어 입력해 주세요.', 'Enter two hyphens (-).');
        } elseif ($mobile === '') {
            $memberError = t('휴대전화를 입력해 주세요.', 'Enter your cell phone.');
        } elseif ($countryProfile['korea'] && ($affiliationType === '' || !isset($affiliationTypes[$affiliationType]) || $affiliation_ko === '')) {
            $memberError = t('소속 선택과 소속(국문)을 입력해 주세요.', 'Choose an affiliation and enter it in Korean.');
        } elseif ($affiliation_eng === '') {
            $memberError = t('소속(영문)을 입력해 주세요.', 'Enter the affiliation in English.');
        } elseif (!in_array($salutation, $salutations, true)) {
            $memberError = t('호칭을 다시 선택해 주세요.', 'Choose the title again.');
        } elseif ($textLength($name_ko) > 45 || $textLength($first_name_eng) > 45 || $textLength($last_name_eng) > 45 || $textLength($affiliation_ko) > 45 || $textLength($affiliation_eng) > 45 || $textLength($department) > 45 || $textLength($mobile) > 45 || $textLength($telephone) > 45) {
            $memberError = t('각 항목은 45자 안으로 입력해 주세요.', 'Each field must be 45 characters or fewer.');
        } elseif ($duplicate->fetch(PDO::FETCH_ASSOC)) {
            $memberError = t('이미 가입된 이메일입니다.', 'This email is already registered.');
        } else {
            $memberInsert = $pdo->prepare(
                "INSERT INTO member_info
                    (member_type, email, password, name_ko, first_name_eng, last_name_eng, affiliation_type, affiliation_ko, affiliation_eng, department, mobile, telephone, salutation, agree_email, country, year, reg_date, is_admin)
                 VALUES (?, ?, HEX(AES_ENCRYPT(?, ?)), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)"
            );
            $memberOk = $memberInsert->execute([
                $member_type, $email, $password, ksers_aes_key(),
                $name_ko, $first_name_eng, $last_name_eng, $affiliationType,
                $affiliation_ko, $affiliation_eng, $department, $mobile, $telephone, $salutation, $agreeEmail,
                $country, $joinYear,
            ]);
            if ($memberOk) {
                $signupName = $countryProfile['korea']
                    ? $name_ko
                    : trim($first_name_eng . ' ' . $last_name_eng);
                $signupAffiliation = $countryProfile['korea'] ? $affiliation_ko : $affiliation_eng;
                ksers_signup_notify($pdo, $joinYear, !empty($countryProfile['korea']), $email, $signupName, $signupAffiliation);
                $_SESSION['join_done_email'] = $email;
            }
        }
    }

    if($mode == 'update') {
        $sessionSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $newPasswordConfirm = isset($_POST['new_password_confirm']) ? $_POST['new_password_confirm'] : '';
        $existingMember = null;
        if ($sessionSeq > 0) {
            $existingStmt = $pdo->prepare("SELECT * FROM member_info WHERE seq = ?");
            $existingStmt->execute([$sessionSeq]);
            $existingMember = $existingStmt->fetch(PDO::FETCH_ASSOC);
        }
        $countryRow = null;
        if ($existingMember) {
            $countryOk = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
            $countryOk->execute([isset($existingMember['country']) ? (string)$existingMember['country'] : '']);
            $countryRow = $countryOk->fetch(PDO::FETCH_ASSOC);
        }
        $countryProfile = ksers_country_profile($countryRow ? $countryRow : []);
        $salutation = isset($_POST['salutation']) ? trim((string)$_POST['salutation']) : '';
        $affiliationType = isset($_POST['affiliation_type']) ? trim((string)$_POST['affiliation_type']) : '';
        $department = isset($_POST['department']) ? trim((string)$_POST['department']) : '';
        $affiliationTypes = ksers_affiliation_types();
        $telephone = ksers_phone_store($countryProfile['dial'], isset($_POST['telephone']) ? $_POST['telephone'] : '');
        $mobile = ksers_phone_store($countryProfile['dial'], $mobile);
        $salutations = ['', 'Prof.', 'Dr.', 'Mr.', 'Ms.'];
        $passwordSpecial = preg_match('/[^0-9A-Za-z가-힣ㄱ-ㅎㅏ-ㅣ\s]/u', $newPassword);
        if ($existingMember && $countryProfile['korea']) {
            $first_name_eng = $existingMember['first_name_eng'];
            $last_name_eng = $existingMember['last_name_eng'];
        } elseif ($existingMember) {
            $name_ko = $existingMember['name_ko'];
            $affiliationType = '';
            $affiliation_ko = '';
        }
        if ($sessionSeq < 1 || !$existingMember) {
            $memberUpdateDenied = true;
            $memberError = t('로그인 후 수정할 수 있습니다.', 'Log in before editing your profile.');
        } elseif ($newPassword !== '' && ($textLength($newPassword) < 8 || !$passwordSpecial || $newPassword !== $newPasswordConfirm)) {
            $memberError = t('새 비밀번호는 8자 이상이며 특수문자를 포함하고, 확인 값과 같아야 합니다.', 'Use at least 8 characters, including a special character, and make the passwords match.');
        } elseif ($mobile === null || $telephone === null) {
            $memberError = t('하이픈(-) 2개를 넣어 입력해 주세요.', 'Enter two hyphens (-).');
        } elseif ($mobile === '') {
            $memberError = t('휴대전화를 입력해 주세요.', 'Enter your cell phone.');
        } elseif ($countryProfile['korea'] && ($affiliationType === '' || !isset($affiliationTypes[$affiliationType]) || $affiliation_ko === '')) {
            $memberError = t('소속 선택과 소속(국문)을 입력해 주세요.', 'Choose an affiliation and enter it in Korean.');
        } elseif ($affiliation_eng === '') {
            $memberError = t('소속(영문)을 입력해 주세요.', 'Enter the affiliation in English.');
        } elseif (!in_array($salutation, $salutations, true)) {
            $memberError = t('호칭을 다시 선택해 주세요.', 'Choose the title again.');
        } elseif ($textLength($affiliation_ko) > 45 || $textLength($affiliation_eng) > 45 || $textLength($department) > 45 || $textLength($mobile) > 45 || $textLength($telephone) > 45) {
            $memberError = t('각 항목은 45자 안으로 입력해 주세요.', 'Each field must be 45 characters or fewer.');
        } else {
            $agreeEmail = (isset($_POST['email_agree']) && trim((string)$_POST['email_agree']) === '1') ? 1 : 0;
            $memberUpdate = $pdo->prepare(
                "UPDATE member_info SET
                    affiliation_type = ?, affiliation_ko = ?, affiliation_eng = ?,
                    department = ?, mobile = ?, telephone = ?, salutation = ?, agree_email = ?
                 WHERE seq = ?"
            );
            $memberUpdateOk = $memberUpdate->execute([
                $affiliationType,
                $affiliation_ko,
                $affiliation_eng,
                $department,
                $mobile,
                $telephone,
                $salutation,
                $agreeEmail,
                $sessionSeq,
            ]);
            if ($memberUpdateOk && (int)$existingMember['agree_email'] !== $agreeEmail) {
                $_SESSION['email_agree_changed_at'] = ksers_password_changed_at_kst();
            }
            if ($memberUpdateOk && $newPassword !== '') {
                $passwordUpdate = $pdo->prepare(
                    "UPDATE member_info SET password = HEX(AES_ENCRYPT(?, ?)) WHERE seq = ?"
                );
                $memberUpdateOk = $passwordUpdate->execute([$newPassword, ksers_aes_key(), $sessionSeq]);
                if ($memberUpdateOk) {
                    ksers_password_changed_notify($pdo, $sessionSeq);
                }
            }
        }
    }
}



if($board == 'participant') {

    $sessionSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
    $participantYear = isset($eventYear) ? (int)$eventYear : 2027;
    $participantOk = false;
    $participantError = t('저장에 실패했습니다.', 'Could not save.');

    $textLength = function ($value) {
        $value = trim((string)$value);
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        $count = preg_match_all('/./u', $value, $unused);
        return $count === false ? 0 : $count;
    };

    if ($sessionSeq < 1) {
        $participantDenied = true;
        $participantError = t('로그인 후 등록할 수 있습니다.', 'Log in before registering.');
    } elseif (ksers_member_is_admin($pdo)) {
        $participantDenied = true;
        $participantError = t('이 페이지에 접근할 권한이 없습니다.', 'You do not have permission to open this page.');
    } elseif (!ksers_participant_cancelled($pdo, $sessionSeq, $participantYear) && ksers_registration_locked($pdo, $sessionSeq, $participantYear)) {
        $participantDenied = true;
        $participantError = t('결제가 진행된 등록은 수정할 수 없습니다.', 'A registration cannot be edited once payment is in progress.');
    } else {
        $memberStmt = $pdo->prepare(
            "SELECT name_ko, first_name_eng, last_name_eng, affiliation_ko, affiliation_eng, country
             FROM member_info WHERE seq = ?"
        );
        $memberStmt->execute([$sessionSeq]);
        $memberRow = $memberStmt->fetch(PDO::FETCH_ASSOC);
        $countryOk = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
        $countryOk->execute([$memberRow ? (string)$memberRow['country'] : '']);
        $countryRow = $countryOk->fetch(PDO::FETCH_ASSOC);
        $memberKorea = ksers_country_profile($countryRow ? $countryRow : [])['korea'];
        $categories = ksers_registration_categories();
        $memberChoices = ksers_registration_member_choices();
        $diets = ksers_registration_diets();
        $ksersMember = isset($_POST['ksers_member']) ? trim((string)$_POST['ksers_member']) : '';
        $seniorLifetime = isset($_POST['senior_lifetime']) ? 1 : 0;
        $category = isset($_POST['category']) ? trim((string)$_POST['category']) : '';
        $licenseNo = isset($_POST['license_no']) ? trim((string)$_POST['license_no']) : '';
        $dietary = isset($_POST['dietary']) ? trim((string)$_POST['dietary']) : '';
        $dietaryDetail = isset($_POST['dietary_detail']) ? trim((string)$_POST['dietary_detail']) : '';
        $needsLicense = $memberKorea && ($category === 'specialist' || $category === 'resident');
        $needsSupport = !$memberKorea && ($category === 'student' || $category === 'nurse');
        $needsDietDetail = $dietary === 'vegetarian' || $dietary === 'others';
        $cmeApply = '';
        if (!$memberKorea) {
            $ksersMember = '';
            $seniorLifetime = 0;
        } elseif ($ksersMember !== 'lifetime') {
            $seniorLifetime = 0;
        }
        if (!$needsLicense) {
            $licenseNo = '';
        } elseif (preg_match('/^[0-9]{1,7}$/', $licenseNo)) {
            $cmeApply = $licenseNo === '0000' ? 'N' : 'Y';
        }
        if (!$needsDietDetail) {
            $dietaryDetail = '';
        }

        $existing = $pdo->prepare(
            "SELECT seq, certificate_file, certificate_stored, support_file, support_stored, support_date,
                    support_status, support_reason, support_handled_at, support_handler, cancelled_at
             FROM participant_info WHERE member_seq = ? AND year = ?"
        );
        $existing->execute([$sessionSeq, $participantYear]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
        $certificateFile = $existingRow ? (string)$existingRow['certificate_file'] : '';
        $certificateStored = $existingRow ? (string)$existingRow['certificate_stored'] : '';
        $supportFile = $existingRow ? (string)$existingRow['support_file'] : '';
        $supportStored = $existingRow ? (string)$existingRow['support_stored'] : '';
        $supportDate = ($existingRow && !empty($existingRow['support_date'])) ? $existingRow['support_date'] : null;
        $supportStatus = $existingRow && isset($existingRow['support_status']) ? (string)$existingRow['support_status'] : '';
        $supportReason = $existingRow && isset($existingRow['support_reason']) ? (string)$existingRow['support_reason'] : '';
        $supportHandledAt = ($existingRow && !empty($existingRow['support_handled_at'])) ? $existingRow['support_handled_at'] : null;
        $supportHandler = $existingRow && isset($existingRow['support_handler']) ? (string)$existingRow['support_handler'] : '';
        $wasCancelled = $existingRow && !empty($existingRow['cancelled_at']);
        $oldStored = $certificateStored;
        $oldSupportStored = $supportStored;
        if ($wasCancelled) {
            $certificateFile = '';
            $certificateStored = '';
            $supportFile = '';
            $supportStored = '';
            $supportDate = null;
            $supportStatus = '';
            $supportReason = '';
            $supportHandledAt = null;
            $supportHandler = '';
        }
        $supportLocked = $needsSupport && $supportStatus === 'approved';
        $uploadedPath = '';
        $supportPath = '';
        $upload = isset($_FILES['certificate']) ? $_FILES['certificate'] : null;
        $fileError = '';
        if ($upload && isset($upload['error']) && (int)$upload['error'] !== UPLOAD_ERR_NO_FILE) {
            list($fileError, $meta) = ksers_submission_check_file('qualification_certificate', $upload);
            if ($fileError === '' && $meta) {
                list($fileError, $storedName, $storedPath) = ksers_submission_place('qualification_certificate', $meta, $participantYear, $sessionSeq);
                if ($fileError === '') {
                    $certificateFile = $meta['original'];
                    $certificateStored = $storedName;
                    $uploadedPath = $storedPath;
                }
            }
        }
        $supportUpload = isset($_FILES['support_file']) ? $_FILES['support_file'] : null;
        if ($fileError === '' && $needsSupport && !$supportLocked && $supportUpload && isset($supportUpload['error']) && (int)$supportUpload['error'] !== UPLOAD_ERR_NO_FILE) {
            list($fileError, $meta) = ksers_submission_check_file('supporting_document', $supportUpload);
            if ($fileError === '' && $meta) {
                list($fileError, $storedName, $storedPath) = ksers_submission_place('supporting_document', $meta, $participantYear, $sessionSeq);
                if ($fileError === '') {
                    $supportFile = $meta['original'];
                    $supportStored = $storedName;
                    $supportDate = date('Y-m-d H:i:s');
                    $supportPath = $storedPath;
                }
            }
        }
        if (!$needsSupport) {
            $supportFile = '';
            $supportStored = '';
            $supportDate = null;
            $supportStatus = '';
            $supportReason = '';
            $supportHandledAt = null;
            $supportHandler = '';
        } elseif (!$supportLocked && ($supportPath !== '' || $supportStatus !== 'rejected')) {
            $supportStatus = 'review';
            if ($supportPath !== '') {
                $supportReason = '';
                $supportHandledAt = null;
                $supportHandler = '';
            }
        }

        if (!$memberRow) {
            $participantError = t('회원 정보를 찾을 수 없습니다.', 'The member profile could not be found.');
        } elseif ($memberKorea && !isset($memberChoices[$ksersMember])) {
            $participantError = t('KSERS 회원여부를 선택해 주세요.', 'Choose your KSERS membership.');
        } elseif (!isset($categories[$category])) {
            $participantError = t('직종을 선택해 주세요.', 'Choose a category.');
        } elseif ($needsLicense && !preg_match('/^[0-9]{1,7}$/', $licenseNo)) {
            $participantError = t('의사면허번호는 숫자 1~7자리로 입력해 주세요. 평점을 신청하지 않으면 0000입니다.', 'Enter 1 to 7 digits. Use 0000 if you are not applying for CME credit.');
        } elseif ($dietary !== '' && !isset($diets[$dietary])) {
            $participantError = t('식이 제한을 다시 선택해 주세요.', 'Choose the dietary option again.');
        } elseif ($needsDietDetail && $dietaryDetail === '') {
            $participantError = t('Vegetarian 또는 Others를 선택하신 경우 상세 내용을 입력해 주세요.', 'If you select Vegetarian or Others, please provide details.');
        } elseif ($textLength($licenseNo) > 45 || $textLength($dietaryDetail) > 200) {
            $participantError = t('입력 길이를 확인해 주세요.', 'Check the length of the fields.');
        } elseif ($fileError !== '') {
            $participantError = $fileError;
        } elseif ($needsSupport && $supportFile === '') {
            $participantError = t('학생 또는 간호사임을 확인할 수 있는 서류를 업로드해 주세요.', 'Please upload a document verifying that you are a student or nurse.');
        } else {
            $saved = false;
            $participantValues = [
                $ksersMember, $seniorLifetime, $category, $licenseNo, $cmeApply,
                $certificateFile, $certificateStored, $supportFile, $supportStored, $supportDate,
                $supportStatus, $supportReason, $supportHandledAt, $supportHandler,
                $dietary, $dietaryDetail, $sessionSeq, $participantYear,
            ];
            if ($existingRow) {
                $participantUpdate = $pdo->prepare(
                    "UPDATE participant_info SET
                        ksers_member = ?, senior_lifetime = ?,
                        category = ?, license_no = ?, cme_apply = ?, certificate_file = ?, certificate_stored = ?,
                        support_file = ?, support_stored = ?, support_date = ?,
                        support_status = ?, support_reason = ?, support_handled_at = ?, support_handler = ?,
                        dietary = ?, dietary_detail = ?
                     WHERE member_seq = ? AND year = ?"
                );
                $saved = $participantUpdate->execute($participantValues);
            } else {
                $participantInsert = $pdo->prepare(
                    "INSERT INTO participant_info
                        (member_seq, ksers_member, senior_lifetime, category,
                         license_no, cme_apply, certificate_file, certificate_stored,
                         support_file, support_stored, support_date,
                         support_status, support_reason, support_handled_at, support_handler,
                         dietary, dietary_detail, year, reg_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                try {
                    $saved = $participantInsert->execute([
                        $sessionSeq, $ksersMember, $seniorLifetime, $category,
                        $licenseNo, $cmeApply, $certificateFile, $certificateStored,
                        $supportFile, $supportStored, $supportDate,
                        $supportStatus, $supportReason, $supportHandledAt, $supportHandler,
                        $dietary, $dietaryDetail, $participantYear,
                    ]);
                } catch (PDOException $e) {
                    $duplicate = isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062;
                    if ($duplicate) {
                        $participantUpdate = $pdo->prepare(
                            "UPDATE participant_info SET
                                ksers_member = ?, senior_lifetime = ?,
                                category = ?, license_no = ?, cme_apply = ?, certificate_file = ?, certificate_stored = ?,
                                support_file = ?, support_stored = ?, support_date = ?,
                                support_status = ?, support_reason = ?, support_handled_at = ?, support_handler = ?,
                                dietary = ?, dietary_detail = ?
                             WHERE member_seq = ? AND year = ?"
                        );
                        $saved = $participantUpdate->execute($participantValues);
                    }
                }
            }
            if ($saved && $wasCancelled) {
                $reopen = $pdo->prepare(
                    "UPDATE participant_info SET cancelled_at = NULL, reg_date = NOW(), pay_confirm = 'N' WHERE member_seq = ? AND year = ?"
                );
                $saved = $reopen->execute([$sessionSeq, $participantYear]);
            }
            if ($saved) {
                $participantOk = true;
                if ((!$needsSupport || $supportStatus === 'approved') && ksers_record_free_registration($pdo, $sessionSeq, $participantYear) && function_exists('ksers_registration_free_notify')) {
                    ksers_registration_free_notify($pdo, $sessionSeq, $participantYear);
                }
                if ($needsSupport && $supportPath !== '' && function_exists('ksers_registration_support_notify')) {
                    ksers_registration_support_notify($pdo, $sessionSeq, $participantYear);
                }
                if ($uploadedPath !== '' && $oldStored !== '' && $oldStored !== $certificateStored) {
                    $oldPath = ksers_submission_find('qualification_certificate', $oldStored);
                    if ($oldPath !== '' && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
                if ($oldSupportStored !== '' && $oldSupportStored !== $supportStored) {
                    $oldSupportPath = ksers_submission_find('supporting_document', $oldSupportStored);
                    if ($oldSupportPath !== '' && is_file($oldSupportPath)) {
                        unlink($oldSupportPath);
                    }
                }
            } else {
                if ($uploadedPath !== '' && is_file($uploadedPath)) {
                    unlink($uploadedPath);
                }
                if ($supportPath !== '' && is_file($supportPath)) {
                    unlink($supportPath);
                }
            }
        }
        if (empty($participantOk)) {
            if ($uploadedPath !== '' && is_file($uploadedPath)) {
                unlink($uploadedPath);
            }
            if ($supportPath !== '' && is_file($supportPath)) {
                unlink($supportPath);
            }
        }
    }
}

// echo $sql;

if ($board == 'member' && $mode == 'input') {
    $result = !empty($memberOk) ? true : null;
} elseif ($board == 'member' && $mode == 'update') {
    $result = (empty($memberUpdateDenied) && !empty($memberUpdateOk)) ? true : null;
} elseif ($board == 'participant') {
    $result = (empty($participantDenied) && !empty($participantOk)) ? true : null;
} else {
    $result = $pdo->query($sql, $param);
}
$homeNext = ksers_homepage_return(isset($_POST['next']) ? $_POST['next'] : '');
$joinDoneUrl = 'join.php?done=1' . ($homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '');
if(isset($result)) { ?>
    <script>
        <?php if($board == 'member' && $mode == "input") { ?>
        location.replace(<?= json_encode($joinDoneUrl) ?>);
        <?php } else { ?>
        alert(<?= json_encode(t('저장에 성공했습니다.', 'Saved.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)
        <?php } ?>
        <?php if($board == 'member' && $mode == "update") { ?>
        location.replace('mypage.php');
        <?php } ?>
        <?php if($board == 'participant') { ?>
        location.replace('registration.php?saved=1');
        <?php } ?>
    </script>
<?php } else { ?>
    <script>
        alert(<?= json_encode(isset($memberError) ? $memberError : (isset($participantError) ? $participantError : t('저장에 실패했습니다.', 'Could not save.')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
        history.back();
    </script>
<?php } ?>