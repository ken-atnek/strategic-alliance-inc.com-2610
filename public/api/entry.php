<?php
/* =======================================
 * ストラテジックアライアンス採用 エントリーメール送信API
 * URL: /api/entry.php
 * Referenced in: /src/scripts/entry-form.ts
 * Created: 2026-10-06
 * Last updated: 2026-10-17
 * ======================================= */

declare(strict_types=1);

ini_set('display_errors', '0');

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

    return trim(str_replace(["\r\n", "\r"], "\n", $value));
}

function rawPostValue(string $name): string
{
    $value = $_POST[$name] ?? '';

    return is_string($value) ? $value : '';
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

function nullablePostValue(string $name): ?string
{
    $value = postValue($name);

    return $value === '' ? null : $value;
}

function stringLength(string $value): int
{
    return mb_strlen($value, 'UTF-8');
}

function normalizeBirthDate(string $value): ?string
{
    $value = preg_replace('/\A[ \x{3000}]+|[ \x{3000}]+\z/u', '', $value);
    if (!is_string($value)) {
        return null;
    }

    $value = mb_convert_kana($value, 'n', 'UTF-8');
    $patterns = [
        '/\A([0-9]{4})年([0-9]{1,2})月([0-9]{1,2})日\z/u',
        '/\A([0-9]{4})\/([0-9]{1,2})\/([0-9]{1,2})\z/',
        '/\A([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})\z/',
        '/\A([0-9]{4})([0-9]{2})([0-9]{2})\z/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value, $matches) !== 1) {
            continue;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if ($year < 1000 || !checkdate($month, $day, $year)) {
            return null;
        }

        $normalized = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Tokyo'));

        return $normalized < $today->format('Y-m-d') ? $normalized : null;
    }

    return null;
}

function normalizePhoneNumber(string $value): ?string
{
    $value = mb_convert_kana($value, 'n', 'UTF-8');
    $separators = ' \x{3000}\x{00A0}\t\-\x{FF0D}\x{30FC}\x{FF70}\x{2010}-\x{2015}\x{2212}';
    if (preg_match('/\A[0-9' . $separators . ']+\z/u', $value) !== 1) {
        return null;
    }

    $digits = preg_replace('/[' . $separators . ']/u', '', $value);

    return is_string($digits) && preg_match('/\A0[0-9]{9,10}\z/', $digits) === 1
        ? $digits : null;
}

function detectEntryEnvironment(string $scriptDirectory): ?bool
{
    $path = '/' . trim(strtolower(str_replace('\\', '/', $scriptDirectory)), '/') . '/';
    $isDemo = str_ends_with($path, '/tuna-pic.co.jp/public_html/demo-strategic-alliance/strategic_2610/api/');
    $isProduction = str_ends_with($path, '/strategic-alliance-inc.com/public_html/strategic_2610/api/');

    return $isDemo === $isProduction ? null : $isDemo;
}

function findEntryConfigPath(string $applicationName): ?string
{
    $directory = __DIR__;

    for ($depth = 0; $depth < 10; $depth++) {
        $candidate = $directory . DIRECTORY_SEPARATOR . 'laravel-apps'
            . DIRECTORY_SEPARATOR . $applicationName
            . DIRECTORY_SEPARATOR . 'entry-config.php';
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }

        $parent = dirname($directory);
        if ($parent === $directory) {
            break;
        }
        $directory = $parent;
    }

    return null;
}

function loadEntryConfig(bool $isTestEnvironment): array
{
    $applicationName = $isTestEnvironment
        ? 'demo-strategic-alliance-control'
        : 'strategic-alliance-control';
    $configPath = findEntryConfigPath($applicationName);
    if ($configPath === null) {
        throw new RuntimeException('Entry configuration file was not found.');
    }

    $config = require $configPath;
    if (!is_array($config)
        || !isset($config['database']) || !is_array($config['database'])
        || !isset($config['mail']) || !is_array($config['mail'])
        || !array_key_exists('enabled', $config['mail']) || !is_bool($config['mail']['enabled'])) {
        throw new RuntimeException('Entry database configuration is invalid.');
    }

    $database = $config['database'];
    foreach (['host', 'port', 'name', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $database) || (!is_string($database[$key]) && !is_int($database[$key]))) {
            throw new RuntimeException('Entry database configuration is incomplete.');
        }
    }

    if ((string) $database['host'] === '' || (string) $database['name'] === '' || (string) $database['username'] === '') {
        throw new RuntimeException('Entry database configuration is incomplete.');
    }

    $port = filter_var($database['port'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new RuntimeException('Entry database port is invalid.');
    }

    return [
        'database' => [
            'host' => (string) $database['host'],
            'port' => $port,
            'name' => (string) $database['name'],
            'username' => (string) $database['username'],
            'password' => (string) $database['password'],
        ],
        'applicationRoot' => dirname($configPath),
        'mailEnabled' => $config['mail']['enabled'],
    ];
}

function connectDatabase(array $database): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['port'],
        $database['name']
    );
    $pdo = new PDO($dsn, $database['username'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}

function detectResumeFile(): ?array
{
    $resumeFile = $_FILES['resumeFile'] ?? null;
    if (!is_array($resumeFile)) {
        return null;
    }

    $uploadError = $resumeFile['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        sendJson(422, [
            'ok' => false,
            'field' => 'resumeFile',
            'message' => '5MB以下のファイルを選択してください',
        ]);
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        sendJson(500, [
            'ok' => false,
            'field' => 'resumeFile',
            'message' => '職務経歴書の保存に失敗しました。再度選び直してください',
        ]);
    }

    $fileSize = filter_var($resumeFile['size'] ?? null, FILTER_VALIDATE_INT);
    $temporaryPath = $resumeFile['tmp_name'] ?? '';
    $clientName = $resumeFile['name'] ?? '';
    if (!is_string($temporaryPath) || !is_string($clientName)
        || $fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        sendJson(422, [
            'ok' => false,
            'field' => 'resumeFile',
            'message' => '5MB以下のファイルを選択してください',
        ]);
    }
    if (!is_uploaded_file($temporaryPath)) {
        sendJson(500, [
            'ok' => false,
            'field' => 'resumeFile',
            'message' => '職務経歴書の保存に失敗しました。再度選び直してください',
        ]);
    }

    $originalName = basename(str_replace('\\', '/', $clientName));
    $originalName = preg_replace('/[\x00-\x1f\x7f]/u', '_', $originalName) ?: 'resume';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'doc', 'docx'], true)
        || stringLength($originalName) > 255) {
        sendJson(422, [
            'ok' => false,
            'field' => 'resumeFile',
            'message' => 'PDF・Word形式のファイルを選択してください',
        ]);
    }

    $signature = file_get_contents($temporaryPath, false, null, 0, 8);
    $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $isValid = false;
    $mimeType = '';

    if ($extension === 'pdf') {
        $isValid = is_string($signature) && str_starts_with($signature, '%PDF-')
            && $detectedMime === 'application/pdf';
        $mimeType = 'application/pdf';
    } elseif ($extension === 'doc') {
        $documentContents = file_get_contents($temporaryPath);
        $wordDocumentStream = "W\x00o\x00r\x00d\x00D\x00o\x00c\x00u\x00m\x00e\x00n\x00t\x00";
        $isValid = $signature === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"
            && is_string($documentContents)
            && str_contains($documentContents, $wordDocumentStream);
        $mimeType = 'application/msword';
    } else {
        if (!class_exists(ZipArchive::class)) {
            error_log('PHP Zip extension is required to validate DOCX files.');
            sendJson(500, [
                'ok' => false,
                'field' => 'resumeFile',
                'message' => '職務経歴書の保存に失敗しました。再度選び直してください',
            ]);
        }
        $zip = new ZipArchive();
        $opened = $zip->open($temporaryPath) === true;
        $isValid = $opened
            && $zip->locateName('[Content_Types].xml') !== false
            && $zip->locateName('word/document.xml') !== false;
        if ($opened) {
            $zip->close();
        }
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    if (!$isValid) {
        sendJson(422, [
            'ok' => false,
            'field' => 'resumeFile',
            'message' => 'PDF・Word形式のファイルを選択してください',
        ]);
    }

    return [
        'temporaryPath' => $temporaryPath,
        'originalName' => $originalName,
        'extension' => $extension,
        'mimeType' => $mimeType,
        'size' => $fileSize,
    ];
}

function saveResumeFile(array $resumeFile, string $applicationRoot): array
{
    $resumeDirectory = $applicationRoot . DIRECTORY_SEPARATOR . 'storage'
        . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private'
        . DIRECTORY_SEPARATOR . 'resumes';

    if ((!is_dir($resumeDirectory) && !mkdir($resumeDirectory, 0770, true))
        || !is_writable($resumeDirectory)) {
        throw new RuntimeException('Resume directory is not writable.');
    }

    $relativePath = bin2hex(random_bytes(24)) . '.' . $resumeFile['extension'];
    $absolutePath = $resumeDirectory . DIRECTORY_SEPARATOR . $relativePath;
    if (!move_uploaded_file($resumeFile['temporaryPath'], $absolutePath)) {
        throw new RuntimeException('Uploaded resume could not be moved.');
    }
    @chmod($absolutePath, 0640);

    return [
        ...$resumeFile,
        'relativePath' => $relativePath,
        'absolutePath' => $absolutePath,
    ];
}

function removeSavedResume(?array $savedResume): void
{
    if ($savedResume !== null && is_file($savedResume['absolutePath'])
        && !@unlink($savedResume['absolutePath'])) {
        error_log('Entry form could not remove an orphaned resume file.');
    }
}

function findExistingApplication(PDO $pdo, string $submissionKey): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, mail_status FROM job_applications WHERE submission_key = :submission_key LIMIT 1'
    );
    $statement->execute(['submission_key' => $submissionKey]);
    $application = $statement->fetch();

    return is_array($application) ? $application : null;
}

function sendAcceptedResponse(string $mailStatus): void
{
    $payload = ['ok' => true];
    if ($mailStatus === 'failed') {
        $payload['mailWarning'] = '※応募は受け付けましたが、採用担当者への通知メールを送信できませんでした。'
            . '担当者からのご連絡がない場合は、お手数ですがお電話でお問い合わせください。';
    } elseif ($mailStatus !== 'sent') {
        $payload['mailWarning'] = '※応募は受け付けましたが、採用担当者への通知メールの送信結果を確認できませんでした。'
            . '担当者からのご連絡がない場合は、お手数ですがお電話でお問い合わせください。';
    }

    sendJson(200, $payload);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    sendJson(405, ['ok' => false, 'message' => 'POSTで送信してください。']);
}

$isTestEnvironment = detectEntryEnvironment(__DIR__);
if ($isTestEnvironment === null) {
    error_log('Entry form environment could not be determined from its directory.');
    sendJson(500, ['ok' => false, 'message' => '送信設定を確認できませんでした。時間をおいて再度お試しください。']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestHost = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$originHost = $origin !== '' ? strtolower((string) parse_url($origin, PHP_URL_HOST)) : '';
$expectedHost = $isTestEnvironment
    ? 'demo-strategic-alliance.tuna-pic.co.jp'
    : 'strategic-alliance-inc.com';

if ($requestHost !== $expectedHost) {
    error_log('Entry form host does not match its deployment directory.');
    sendJson(403, ['ok' => false, 'message' => '送信元を確認できませんでした。']);
}

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
        $payload = [
            'ok' => false,
            'message' => $label . 'をご入力ください。',
        ];
        if ($name === 'birthDate' || $name === 'phoneNumber') {
            $payload['field'] = $name;
        }

        sendJson(422, $payload);
    }
}

$employmentStatuses = [
    'employed' => '在職中',
    'unemployed' => '離職中',
];
$employmentStatus = postValue('employmentStatus');
if (!array_key_exists($employmentStatus, $employmentStatuses)) {
    sendJson(422, [
        'ok' => false,
        'message' => '現在の就業状況を選択してください。',
    ]);
}

$applicantEmail = postValue('email');
if (filter_var($applicantEmail, FILTER_VALIDATE_EMAIL) === false) {
    sendJson(422, [
        'ok' => false,
        'message' => 'メールアドレスの形式をご確認ください。',
    ]);
}

$submissionKey = postValue('submissionKey');
if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $submissionKey) !== 1) {
    sendJson(422, [
        'ok' => false,
        'message' => '送信情報を確認できませんでした。ページを再読み込みしてお試しください。',
    ]);
}

$birthDateInput = rawPostValue('birthDate');
$phoneNumberInput = rawPostValue('phoneNumber');

$lengthLimits = [
    'fullName' => ['label' => '氏名', 'max' => 100],
    'fullNameKana' => ['label' => 'フリガナ', 'max' => 100],
    'phoneNumber' => ['label' => '電話番号', 'max' => 30],
    'email' => ['label' => 'メールアドレス', 'max' => 254],
    'address' => ['label' => '住所', 'max' => 500],
    'otherQualifications' => ['label' => 'その他保有資格', 'max' => 500],
    'latestEmployer' => ['label' => '直近の勤務先', 'max' => 255],
    'managementTeamSize' => ['label' => 'マネジメント人数', 'max' => 50],
    'workExperience' => ['label' => '経験業務', 'max' => 5000],
    'achievements' => ['label' => '主な実績', 'max' => 5000],
    'motivation' => ['label' => '志望動機', 'max' => 5000],
    'selfPromotion' => ['label' => '自己PR', 'max' => 5000],
    'preferredConditions' => ['label' => '希望条件', 'max' => 5000],
    'questions' => ['label' => '質問・連絡事項', 'max' => 5000],
];
foreach ($lengthLimits as $name => $rule) {
    $value = $name === 'phoneNumber' ? $phoneNumberInput : postValue($name);
    if (stringLength($value) > $rule['max']) {
        $payload = [
            'ok' => false,
            'message' => $rule['label'] . 'は' . number_format($rule['max']) . '文字以内でご入力ください。',
        ];
        if ($name === 'phoneNumber') {
            $payload['field'] = 'phoneNumber';
        }

        sendJson(422, $payload);
    }
}

$birthDate = normalizeBirthDate($birthDateInput);
if ($birthDate === null) {
    sendJson(422, [
        'ok' => false,
        'field' => 'birthDate',
        'message' => '生年月日は「1990年1月1日」「1990/1/1」「1990-01-01」「19900101」のいずれかで、過去の実在する日付をご入力ください。',
    ]);
}

$phoneNumberDigits = normalizePhoneNumber($phoneNumberInput);
if ($phoneNumberDigits === null) {
    sendJson(422, [
        'ok' => false,
        'field' => 'phoneNumber',
        'message' => '電話番号は数字・ハイフン・空白を使用し、0から始まる10桁または11桁でご入力ください。',
    ]);
}
$phoneNumberDisplay = str_replace([' ', '　', "\u{00A0}", "\t"], '', $phoneNumberInput);

$experienceValues = ['0', 'less-than-1', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10-plus'];
foreach (['insuranceExperienceYears', 'financeExperienceYears', 'salesExperienceYears'] as $name) {
    if (!in_array(postValue($name), $experienceValues, true)) {
        sendJson(422, [
            'ok' => false,
            'message' => '経験年数の選択内容をご確認ください。',
        ]);
    }
}

foreach (['managementExperience', 'trainingExperience', 'laborManagementExperience', 'complianceExperience'] as $name) {
    if (!in_array(postValue($name), ['あり', 'なし'], true)) {
        sendJson(422, [
            'ok' => false,
            'message' => '経験の有無を選択してください。',
        ]);
    }
}

foreach (['lifeInsuranceQualification', 'nonLifeInsuranceQualification'] as $name) {
    if (!in_array(postValue($name), ['保有', '未保有'], true)) {
        sendJson(422, [
            'ok' => false,
            'message' => '資格の保有状況を選択してください。',
        ]);
    }
}

$fpQualification = nullablePostValue('fpQualification');
if ($fpQualification !== null && !in_array($fpQualification, ['保有', '未保有'], true)) {
    sendJson(422, [
        'ok' => false,
        'message' => 'FP資格の保有状況を選択してください。',
    ]);
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
    if ($name === 'birthDate') {
        $value = (new DateTimeImmutable($birthDate))->format('Y年n月j日');
    }
    if ($name === 'phoneNumber') {
        $value = $phoneNumberDisplay;
    }
    if ($name === 'employmentStatus') {
        $value = $employmentStatuses[$value];
    }
    if (in_array($name, $experienceFields, true)) {
        $value = formatExperience($value);
    }

    $mailLines[] = '【' . $label . '】';
    $mailLines[] = $value !== '' ? $value : '未入力';
    $mailLines[] = '';
}

try {
    $entryConfig = loadEntryConfig($isTestEnvironment);
    $pdo = connectDatabase($entryConfig['database']);
    $existingApplication = findExistingApplication($pdo, $submissionKey);
} catch (Throwable $exception) {
    error_log('Entry form database initialization failed: ' . $exception->getMessage());
    sendJson(500, [
        'ok' => false,
        'message' => '応募内容を登録できませんでした。時間をおいて再度お試しください。',
    ]);
}

if ($existingApplication !== null) {
    sendAcceptedResponse((string) $existingApplication['mail_status']);
}

$resumeFile = detectResumeFile();
$savedResume = null;
if ($resumeFile !== null) {
    try {
        $savedResume = saveResumeFile($resumeFile, $entryConfig['applicationRoot']);
    } catch (Throwable $exception) {
        error_log('Entry form resume save failed: ' . $exception->getMessage());
        sendJson(500, [
            'ok' => false,
            'field' => 'resumeFile',
            'message' => '職務経歴書の保存に失敗しました。再度選び直してください',
        ]);
    }
}

$createdAt = gmdate('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();
    $applicationStatement = $pdo->prepare(<<<'SQL'
        INSERT INTO job_applications (
            submission_key, status, mail_status,
            full_name, full_name_kana, birth_date, phone_number, phone_number_digits,
            email, employment_status, address,
            insurance_experience_years, finance_experience_years, sales_experience_years,
            management_experience, training_experience, labor_management_experience,
            compliance_experience, life_insurance_qualification,
            non_life_insurance_qualification, fp_qualification, other_qualifications,
            latest_employer, management_team_size, work_experience, achievements,
            motivation, self_promotion, preferred_conditions, questions,
            created_at, updated_at
        ) VALUES (
            :submission_key, 'new', 'pending',
            :full_name, :full_name_kana, :birth_date, :phone_number, :phone_number_digits,
            :email, :employment_status, :address,
            :insurance_experience_years, :finance_experience_years, :sales_experience_years,
            :management_experience, :training_experience, :labor_management_experience,
            :compliance_experience, :life_insurance_qualification,
            :non_life_insurance_qualification, :fp_qualification, :other_qualifications,
            :latest_employer, :management_team_size, :work_experience, :achievements,
            :motivation, :self_promotion, :preferred_conditions, :questions,
            :created_at, :updated_at
        )
        SQL);
    $applicationStatement->execute([
        'submission_key' => $submissionKey,
        'full_name' => postValue('fullName'),
        'full_name_kana' => postValue('fullNameKana'),
        'birth_date' => $birthDate,
        'phone_number' => $phoneNumberDisplay,
        'phone_number_digits' => $phoneNumberDigits,
        'email' => $applicantEmail,
        'employment_status' => $employmentStatus,
        'address' => postValue('address'),
        'insurance_experience_years' => postValue('insuranceExperienceYears'),
        'finance_experience_years' => postValue('financeExperienceYears'),
        'sales_experience_years' => postValue('salesExperienceYears'),
        'management_experience' => postValue('managementExperience'),
        'training_experience' => postValue('trainingExperience'),
        'labor_management_experience' => postValue('laborManagementExperience'),
        'compliance_experience' => postValue('complianceExperience'),
        'life_insurance_qualification' => postValue('lifeInsuranceQualification'),
        'non_life_insurance_qualification' => postValue('nonLifeInsuranceQualification'),
        'fp_qualification' => $fpQualification,
        'other_qualifications' => nullablePostValue('otherQualifications'),
        'latest_employer' => postValue('latestEmployer'),
        'management_team_size' => nullablePostValue('managementTeamSize'),
        'work_experience' => postValue('workExperience'),
        'achievements' => postValue('achievements'),
        'motivation' => nullablePostValue('motivation'),
        'self_promotion' => nullablePostValue('selfPromotion'),
        'preferred_conditions' => nullablePostValue('preferredConditions'),
        'questions' => nullablePostValue('questions'),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $applicationId = (int) $pdo->lastInsertId();

    if ($savedResume !== null) {
        $fileStatement = $pdo->prepare(<<<'SQL'
            INSERT INTO job_application_files (
                job_application_id, original_filename, extension, mime_type,
                file_size_bytes, relative_path, created_at, updated_at
            ) VALUES (
                :job_application_id, :original_filename, :extension, :mime_type,
                :file_size_bytes, :relative_path, :created_at, :updated_at
            )
            SQL);
        $fileStatement->execute([
            'job_application_id' => $applicationId,
            'original_filename' => $savedResume['originalName'],
            'extension' => $savedResume['extension'],
            'mime_type' => $savedResume['mimeType'],
            'file_size_bytes' => $savedResume['size'],
            'relative_path' => $savedResume['relativePath'],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    removeSavedResume($savedResume);

    try {
        $existingApplication = findExistingApplication($pdo, $submissionKey);
    } catch (Throwable) {
        $existingApplication = null;
    }
    if ($existingApplication !== null) {
        sendAcceptedResponse((string) $existingApplication['mail_status']);
    }

    error_log('Entry form database registration failed: ' . $exception->getMessage());
    sendJson(500, [
        'ok' => false,
        'message' => '応募内容を登録できませんでした。時間をおいて再度お試しください。',
    ]);
}

$mailTo = $isTestEnvironment
    ? (getenv('ENTRY_TEST_MAIL_TO') ?: 'debug01@a-fact.co.jp')
    : (getenv('ENTRY_MAIL_TO') ?: 'ken.atnek@gmail.com');
$mailBcc = $isTestEnvironment
    ? ''
    : (getenv('ENTRY_MAIL_BCC') ?: 'debug01@a-fact.co.jp');
$mailFrom = getenv('ENTRY_MAIL_FROM') ?: 'recruit@strategic-alliance-inc.com';
$mailAddresses = array_filter([$mailTo, $mailBcc, $mailFrom]);
$canSendMail = $entryConfig['mailEnabled'];

if (!$canSendMail) {
    error_log('Entry form mail delivery is disabled by configuration.');
}

foreach ($mailAddresses as $mailAddress) {
    if (filter_var($mailAddress, FILTER_VALIDATE_EMAIL) === false) {
        $canSendMail = false;
        error_log('Entry form mail address configuration is invalid.');
        break;
    }
}

$boundary = 'entry_' . bin2hex(random_bytes(16));
$mailBody = implode("\r\n", $mailLines);
$message = '--' . $boundary . "\r\n";
$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n\r\n";
$message .= chunk_split(base64_encode($mailBody));

if ($savedResume !== null) {
    $fileContents = file_get_contents($savedResume['absolutePath']);
    if ($fileContents === false) {
        $canSendMail = false;
        error_log('Saved resume could not be read for mail attachment.');
    } else {
        $encodedName = rawurlencode($savedResume['originalName']);
        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: ' . $savedResume['mimeType'] . "; name*=UTF-8''" . $encodedName . "\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename*=UTF-8''" . $encodedName . "\r\n\r\n";
        $message .= chunk_split(base64_encode($fileContents));
    }
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

$sent = $canSendMail
    && mail($mailTo, $encodedSubject, $message, implode("\r\n", $headers));
$mailStatus = $sent ? 'sent' : 'failed';

if (!$sent) {
    error_log('Entry form mail delivery failed.');
}

try {
    $mailStatusStatement = $pdo->prepare(
        'UPDATE job_applications SET mail_status = :mail_status, updated_at = :updated_at WHERE id = :id'
    );
    $mailStatusStatement->execute([
        'mail_status' => $mailStatus,
        'updated_at' => gmdate('Y-m-d H:i:s'),
        'id' => $applicationId,
    ]);
} catch (Throwable $exception) {
    error_log('Entry form mail status update failed: ' . $exception->getMessage());
}

sendAcceptedResponse($mailStatus);
