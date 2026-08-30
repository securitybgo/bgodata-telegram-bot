<?php
// telegram_bot.php
// Telegram Bot <-> MySQL records table

error_reporting(0);
ini_set('display_errors', 0);

// IMPORTANT: Do NOT put your real Telegram token in this file if it has
// already been exposed. Generate a new token in @BotFather first.
$BOT_TOKEN = getenv('BOT_TOKEN');

if (!$BOT_TOKEN) {
    http_response_code(500);
    exit('BOT_TOKEN is not configured');
}

function telegramRequest($method, $data = [])
{
    global $BOT_TOKEN;

    $url = "https://api.telegram.org/bot" . $BOT_TOKEN . "/" . $method;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function sendMessage($chatId, $text)
{
    telegramRequest('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
    ]);
}

$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message'])) {
    exit('OK');
}

$message = $update['message'];
$chatId = $message['chat']['id'] ?? 0;
$text = trim($message['text'] ?? '');

if (!$chatId) {
    exit('OK');
}

// Commands
if ($text === '/start') {
    sendMessage(
        $chatId,
        "မင်္ဂလာပါ 👋\n\n" .
        "BAGO DATA Bot မှ ကြိုဆိုပါတယ်။\n\n" .
        "🔍 မီတာနံပါတ်၊ အမည် သို့မဟုတ် စာရင်းအမှတ်ကို ရိုက်ပို့ပြီး ရှာနိုင်ပါတယ်။\n\n" .
        "ဥပမာ — E18.18533"
    );
    exit('OK');
}

if ($text === '/help') {
    sendMessage(
        $chatId,
        "အသုံးပြုနည်း\n\n" .
        "🔢 မီတာနံပါတ်ကို ရိုက်ပို့ပါ\n" .
        "👤 အမည်ကို ရိုက်ပို့ပါ\n" .
        "📋 စာရင်းအမှတ်ကို ရိုက်ပို့ပါ"
    );
    exit('OK');
}

if ($text === '') {
    exit('OK');
}

// Search records by meter number, name, or account.
$search = '%' . $text . '%';

$sql = "SELECT number, name, account, result,
               previous_reading, current_reading,
               usage_units, amount_due
        FROM records
        WHERE number LIKE ?
           OR name LIKE ?
           OR account LIKE ?
        ORDER BY id ASC
        LIMIT 10";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    sendMessage($chatId, "⚠️ Database query ပြဿနာရှိနေပါတယ်။");
    exit('OK');
}

$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendMessage(
        $chatId,
        "❌ ရှာမတွေ့ပါ။\n\n" .
        "မီတာနံပါတ်၊ အမည် သို့မဟုတ် စာရင်းအမှတ်ကို ပြန်စစ်ပြီး ပို့ပေးပါ။"
    );
    $stmt->close();
    exit('OK');
}

$output = "🔎 BAGO DATA ရှာဖွေမှု\n";
$output .= "━━━━━━━━━━━━━━\n\n";

$count = 0;

while ($row = $result->fetch_assoc()) {
    $count++;

    $output .= "📌 ရလဒ် " . $count . "\n";
    $output .= "👤 အမည် — " . ($row['name'] ?? '') . "\n";
    $output .= "🔢 မီတာနံပါတ် — " . ($row['number'] ?? '') . "\n";
    $output .= "📋 စာရင်းအမှတ် — " . ($row['account'] ?? '') . "\n";
    $output .= "📍 လိပ်စာ/အချက်အလက် — " . ($row['result'] ?? '') . "\n";
    $output .= "📖 ယခင်လဖတ်ချက် — " . formatNumber($row['previous_reading']) . "\n";
    $output .= "📖 ယခုလဖတ်ချက် — " . formatNumber($row['current_reading']) . "\n";
    $output .= "⚡ သုံးစွဲယူနစ် — " . formatNumber($row['usage_units']) . "\n";
    $output .= "💰 ကျသင့်ငွေ — " . formatNumber($row['amount_due']) . "\n";
    $output .= "\n━━━━━━━━━━━━━━\n\n";
}

if ($count >= 10) {
    $output .= "ℹ️ ပထမ 10 ခုသာ ပြထားပါတယ်။ ပိုတိကျတဲ့ မီတာနံပါတ်နဲ့ ပြန်ရှာပါ။";
}

sendMessage($chatId, $output);

$stmt->close();

function formatNumber($value)
{
    if ($value === null || $value === '') {
        return '-';
    }

    $number = (float)$value;

    if (floor($number) == $number) {
        return number_format($number, 0);
    }

    return number_format($number, 2);
}

exit('OK');
?>
