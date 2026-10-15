<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db   = 'xvanikigma';
$user = 'xvanikigma'; // Если ваш пользователь БД отличается, замените его здесь (например, 'root')
$pass = 'E4qkL5sHpeW7hL7';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Получение списка всех голосов (для отображения результатов и админ-панели)
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT full_name as fullName, school_class as schoolClass, candidate, UNIX_TIMESTAMP(created_at) * 1000 as timestamp FROM votes");
    $votes = $stmt->fetchAll();
    echo json_encode($votes);
    exit;
}

// Отправка нового голоса
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['fullName']) || empty($data['schoolClass']) || empty($data['candidate'])) {
        echo json_encode(['success' => false, 'message' => 'Неверные данные']);
        exit;
    }

    $fullName = trim($data['fullName']);
    
    // Проверка на серверной стороне: каждое слово должно начинаться с заглавной буквы
    $words = preg_split('/\s+/', $fullName);
    foreach ($words as $word) {
        if ($word !== '' && mb_substr($word, 0, 1, 'UTF-8') !== mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')) {
            echo json_encode(['success' => false, 'message' => 'Каждое слово в ФИО должно начинаться с заглавной буквы.']);
            exit;
        }
    }

    $schoolClass = trim($data['schoolClass']);
    $candidate = trim($data['candidate']);

    // Проверка лимита класса (максимум 45 голосов)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE school_class = ?");
    $stmt->execute([$schoolClass]);
    $count = $stmt->fetchColumn();

    if ($count >= 45) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Для класса $schoolClass исчерпан лимит голосов (максимум 45)!"]);
        exit;
    }

    // Сохранение в базу данных
    $stmt = $pdo->prepare("INSERT INTO votes (full_name, school_class, candidate) VALUES (?, ?, ?)");
    $stmt->execute([$fullName, $schoolClass, $candidate]);

    echo json_encode(['success' => true]);
    exit;
}