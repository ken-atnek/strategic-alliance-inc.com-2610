<?php
/* =======================================
 * ストラテジックアライアンス採用 エントリーメール送信API
 * URL: /api/entry.php
 * Referenced in: /src/scripts/entry-form.ts
 * Created: 2026-10-06
 * Last updated: 2026-10-06
 * ======================================= */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function sendJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function postValue(string $name): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function formatExperience(string $value): string
{
    if ($value === '0') {
        return '未経験';
    }

    if ($value === 'less-than-1') {
        return '1年未満';
    }

    if ($value === '10-plus') {
        return '10年以上';
    }

    return ctype_digit($value) ? $value . '年' : $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    sendJson(405, ['ok' => false, 'message' => 'POSTで送信してください。']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestHost = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$originHost = $origin !== '' ? strtolower((string) parse_url($origin, PHP_URL_HOST)) : '';

if ($originHost !== '' && $requestHost !== '' && $originHost !== $requestHost) {
    sendJson(403, ['ok' => false, 'message' => '送信元を確認できませんでした。']);
}

if (postValue('website') !== '') {
    sendJson(200, ['ok' => true]);
}

$requiredFields = [
    'fullName' => '氏名',
    'fullNameKana' => 'フリガナ',
    'birthDate' => '生年月日',
    'phoneNumber' => '電話番号',
    'email' => 'メールアドレス',
    'employmentStatus' => '現在の就業状況',
    'address' => '住所',
    'insuranceExperienceYears' => '保険業界での経験年数',
    'financeExperienceYears' => '金融業界での経験年数',
    'salesExperienceYears' => '営業経験年数',
    'managementExperience' => 'マネジメント経験の有無',
    'trainingExperience' => '採用・育成・教育経験の有無',
    'laborManagementExperience' => '労務管理経験の有無',
    'complianceExperience' => '保険募集管理・コンプライアンス関連経験',
    'lifeInsuranceQualification' => '生命保険募集人資格',
    'nonLifeInsuranceQualification' => '損害保険募集人資格',
    'latestEmployer' => '直近の勤務先',
    'workExperience' => '経験業務',
    'achievements' => '主な実績',
];

foreach ($requiredFields as $name => $label) {
    if (postValue($name) === '') {
        sendJson(422, [
            'ok' => false,
            'message' => $label . 'をご入力ください。',
        ]);
    }
}

$applicantEmail = postValue('email');
if (filter_var($applicantEmail, FILTER_VALIDATE_EMAIL) === false) {
    sendJson(422, [
        'ok' => false,
        'message' => 'メールアドレスの形式をご確認ください。',
    ]);
}

$isTestEnvironment = $requestHost === 'demo-strategic-alliance.tuna-pic.co.jp';
$mailTo = $isTestEnvironment
    ? (getenv('ENTRY_TEST_MAIL_TO') ?: 'debug01@a-fact.co.jp')
    : (getenv('ENTRY_MAIL_TO') ?: 'ken.atnek@gmail.com');
$mailBcc = $isTestEnvironment
    ? ''
    : (getenv('ENTRY_MAIL_BCC') ?: 'debug01@a-fact.co.jp');
$mailFrom = getenv('ENTRY_MAIL_FROM') ?: 'recruit@strategic-alliance-inc.com';

$mailAddresses = [$mailTo, $mailFrom];
if ($mailBcc !== '') {
    $mailAddresses[] = $mailBcc;
}

foreach ($mailAddresses as $mailAddress) {
    if (filter_var($mailAddress, FILTER_VALIDATE_EMAIL) === false) {
        error_log('Entry form mail address configuration is invalid.');
        sendJson(500, [
            'ok' => false,
            'message' => '送信設定に問題があります。管理者へお問い合わせください。',
        ]);
    }
}

$fieldLabels = [
    'fullName' => '氏名',
    'fullNameKana' => 'フリガナ',
    'birthDate' => '生年月日',
    'phoneNumber' => '電話番号',
    'email' => 'メールアドレス',
    'address' => '住所',
    'employmentStatus' => '現在の就業状況',
    'insuranceExperienceYears' => '保険業界での経験年数',
    'financeExperienceYears' => '金融業界での経験年数',
    'salesExperienceYears' => '営業経験年数',
    'managementExperience' => 'マネジメント経験の有無',
    'trainingExperience' => '採用・育成・教育経験の有無',
    'laborManagementExperience' => '労務管理経験の有無',
    'complianceExperience' => '保険募集管理・コンプライアンス関連経験',
    'lifeInsuranceQualification' => '生命保険募集人資格',
    'nonLifeInsuranceQualification' => '損害保険募集人資格',
    'fpQualification' => 'FP資格',
    'otherQualifications' => 'その他保有資格',
    'latestEmployer' => '直近の勤務先',
    'managementTeamSize' => 'マネジメント人数',
    'workExperience' => '経験業務',
    'achievements' => '主な実績',
    'motivation' => '志望動機',
    'selfPromotion' => '自己PR',
    'preferredConditions' => '希望条件',
    'questions' => '質問・連絡事項',
];

$experienceFields = [
    'insuranceExperienceYears',
    'financeExperienceYears',
    'salesExperienceYears',
];
$mailLines = [
    $isTestEnvironment
        ? '【テスト送信】採用サイトからエントリーがありました。'
        : '採用サイトからエントリーがありました。',
    '',
];

foreach ($fieldLabels as $name => $label) {
    $value = postValue($name);
    if (in_array($name, $experienceFields, true)) {
        $value = formatExperience($value);
    }

    $mailLines[] = '【' . $label . '】';
    $mailLines[] = $value !== '' ? $value : '未入力';
    $mailLines[] = '';
}

$boundary = 'entry_' . bin2hex(random_bytes(16));
$mailBody = implode("\r\n", $mailLines);
$message = '--' . $boundary . "\r\n";
$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n\r\n";
$message .= chunk_split(base64_encode($mailBody));

$resumeFile = $_FILES['resumeFile'] ?? null;
if (is_array($resumeFile) && ($resumeFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (($resumeFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        sendJson(422, [
            'ok' => false,
            'message' => '職務経歴書をアップロードできませんでした。',
        ]);
    }

    $maxFileBytes = (int) (getenv('ENTRY_RESUME_MAX_BYTES') ?: 5242880);
    $fileSize = (int) ($resumeFile['size'] ?? 0);
    $temporaryPath = (string) ($resumeFile['tmp_name'] ?? '');
    $originalName = basename((string) ($resumeFile['name'] ?? 'resume'));
    $safeName = preg_replace('/[\r\n"]+/', '', $originalName) ?: 'resume';
    $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'doc', 'docx'];

    if ($fileSize < 1 || $fileSize > $maxFileBytes) {
        sendJson(422, [
            'ok' => false,
            'message' => '職務経歴書は5MB以下のファイルを選択してください。',
        ]);
    }

    if (!in_array($extension, $allowedExtensions, true) || !is_uploaded_file($temporaryPath)) {
        sendJson(422, [
            'ok' => false,
            'message' => '職務経歴書はPDFまたはWord形式で送信してください。',
        ]);
    }

    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $fileContents = file_get_contents($temporaryPath);

    if ($fileContents === false) {
        sendJson(500, [
            'ok' => false,
            'message' => '職務経歴書を読み込めませんでした。',
        ]);
    }

    $encodedName = rawurlencode($safeName);
    $message .= '--' . $boundary . "\r\n";
    $message .= 'Content-Type: ' . $mimeTypes[$extension] . "; name*=UTF-8''" . $encodedName . "\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename*=UTF-8''" . $encodedName . "\r\n\r\n";
    $message .= chunk_split(base64_encode($fileContents));
}

$message .= '--' . $boundary . "--\r\n";
$subject = ($isTestEnvironment ? '【テスト】' : '') . '【採用サイト】エントリーがありました';
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    'From: Strategic Alliance Recruit <' . $mailFrom . '>',
    'Reply-To: ' . $applicantEmail,
    'X-Mailer: PHP/' . PHP_VERSION,
];

if ($mailBcc !== '') {
    $headers[] = 'Bcc: ' . $mailBcc;
}

$sent = mail($mailTo, $encodedSubject, $message, implode("\r\n", $headers));

if (!$sent) {
    error_log('Entry form mail delivery failed.');
    sendJson(500, [
        'ok' => false,
        'message' => '送信できませんでした。時間をおいて再度お試しください。',
    ]);
}

sendJson(200, ['ok' => true]);
