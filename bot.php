<?php
// توكن البوت
$botToken = "7409055167:AAEZsZE4vbk05OHgWh7elXixKteuD36pbrs";
$apiUrl = "https://api.telegram.org/bot$botToken/";

// دالة لإرسال الرسائل
function sendMessage($chatId, $text, $keyboard = null) {
    global $apiUrl;

    $postData = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];

    if ($keyboard) {
        $postData['reply_markup'] = json_encode($keyboard);
    }

    file_get_contents($apiUrl . "sendMessage?" . http_build_query($postData));
}

// دالة للحصول على مالك المجموعة
function getGroupOwner($chatId) {
    global $apiUrl;

    $response = file_get_contents($apiUrl . "getChatAdministrators?chat_id=$chatId");
    $data = json_decode($response, true);

    if (isset($data['result'])) {
        foreach ($data['result'] as $admin) {
            if ($admin['status'] == 'creator') {
                return $admin['user']['id']; // إعادة معرف المالك
            }
        }
    }

    return null; // لم يتم العثور على مالك
}

// قراءة التحديثات
$update = json_decode(file_get_contents("php://input"), true);

if (!$update) {
    exit;
}

$chatId = $update['message']['chat']['id'];
$userId = $update['message']['from']['id'];
$message = $update['message']['text'];
$replyTo = $update['message']['reply_to_message'] ?? null;

// تخزين الرتب (تحديث معرف المالك تلقائيًا)
$ownerId = getGroupOwner($chatId);
$ranks = [
    "owner" => $ownerId ? [$ownerId] : [], // إذا لم يتم العثور على مالك، تكون فارغة
    "manager" => [],
    "admin" => [],
    "member" => []
];

// التحقق من رتبة المستخدم
function getUserRank($userId, $ranks) {
    foreach ($ranks as $rank => $users) {
        if (in_array($userId, $users)) {
            return $rank;
        }
    }
    return "member"; // الرتبة الافتراضية
}

// تخصيص الرتبة
$userRank = getUserRank($userId, $ranks);

// التعامل مع الأوامر
if ($message == "/start") {
    sendMessage($chatId, "مرحبًا بك في البوت! رتبتك الحالية هي: *$userRank*.");
} elseif ($message == "/features") {
    // عرض الميزات بناءً على الرتبة
    switch ($userRank) {
        case "owner":
            sendMessage($chatId, "ميزاتك: التحكم الكامل بالبوت (رفع، تنزيل، حظر).");
            break;
        case "manager":
            sendMessage($chatId, "ميزاتك: إدارة المستخدمين (رفع أدمن، تنزيل أعضاء).");
            break;
        case "admin":
            sendMessage($chatId, "ميزاتك: مساعدة المدير (إدارة أساسية).");
            break;
        default:
            sendMessage($chatId, "ميزاتك: استخدام الأوامر الأساسية فقط.");
    }
} elseif (strpos($message, "/ban") === 0 && $userRank == "owner" && $replyTo) {
    // حظر المستخدم عبر الرد
    $targetId = $replyTo['from']['id'];
    file_get_contents($apiUrl . "kickChatMember?chat_id=$chatId&user_id=$targetId");
    sendMessage($chatId, "تم حظر المستخدم بنجاح!");
} elseif (strpos($message, "/promote") === 0 && $userRank == "owner" && $replyTo) {
    // رفع رتبة مستخدم
    $targetId = $replyTo['from']['id'];
    $newRank = trim(str_replace("/promote", "", $message));
    if (in_array($newRank, ["manager", "admin"])) {
        $ranks[$newRank][] = $targetId;
        sendMessage($chatId, "تمت ترقية المستخدم إلى $newRank.");
    } else {
        sendMessage($chatId, "رتبة غير صحيحة! استخدم: manager أو admin.");
    }
} elseif (strpos($message, "/demote") === 0 && $userRank == "owner" && $replyTo) {
    // تنزيل رتبة مستخدم
    $targetId = $replyTo['from']['id'];
    foreach ($ranks as $rank => $users) {
        if (($key = array_search($targetId, $users)) !== false) {
            unset($ranks[$rank][$key]);
        }
    }
    sendMessage($chatId, "تم تنزيل رتبة المستخدم إلى عضو.");
} else {
    sendMessage($chatId, "عذرًا، لا يمكنك استخدام هذا الأمر.");
}
?>
