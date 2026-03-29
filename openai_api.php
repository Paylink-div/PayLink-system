<?php
// openai_api.php

// *تم تضمين مفتاحك الخاص هنا*
define('OPENAI_API_KEY', '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'); 
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

/**
 * إرسال سؤال المستخدم والبيانات المالية إلى OpenAI GPT
 * @param string $user_question سؤال المستخدم
 * @param string $context_data البيانات المالية المُستخلصة من المنظومة
 * @return string رد الذكاء الاصطناعي أو رسالة خطأ
 */
function ask_ai_chatgpt($user_question, $context_data = "") {
    
    // تعليمات النظام: لضمان أن الردود تحليلية ومالية ومبنية على السياق.
    $system_instruction = "أنت مساعد مالي خبير ومحلل متقدم متخصص في قطاع الصرافة والتحويلات المالية. مهمتك هي: 1) تحليل الأداء المالي للفروع والتنبؤ بالتدفقات القادمة بناءً على البيانات المُقدمة. 2) الإجابة على استفسارات المستخدمين حول كيفية تحقيق الربح وتجنب الخسارة، والتنبؤ بفرص العملات. 3) استخدم البيانات المُقدمة لتكون إجاباتك دقيقة ومبنية على الواقع. الرد يجب أن يكون باللغة العربية. البيانات المالية المرفقة: {$context_data}";

    $payload = json_encode([
        'model' => 'gpt-3.5-turbo', // نموذج متقدم لتحليل دقيق
        'messages' => [
            ['role' => 'system', 'content' => $system_instruction],
            ['role' => 'user', 'content' => $user_question]
        ],
        'temperature' => 0.3, // للحصول على إجابات منطقية
        'max_tokens' => 1024,
    ]);

    // إعداد طلب CURL
    $ch = curl_init(OPENAI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
         return "❌ فشل الاتصال بخدمة OpenAI. رمز الخطأ: " . $http_code . ". تحقق من المفتاح والميزانية.";
    }

    $data = json_decode($response, true);

    // استخراج الرد
    if (isset($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
    } else {
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'خطأ غير معروف في الرد.';
        return "⚠ لم يتمكن الذكاء الاصطناعي من الرد. التفاصيل: " . $error_message;
    }
}
?>