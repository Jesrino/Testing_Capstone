<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../models/appointments.php';
require_once __DIR__ . '/../config/config.php';

// API should return JSON errors instead of redirects
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$config = require __DIR__ . '/../config/config.php';

// Function to call OpenAI API using cURL
function callOpenAI($prompt, $apiKey) {
    if (empty($apiKey)) return null;

    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 300,
        'temperature' => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('OpenAI API cURL Error: ' . $error);
        return null;
    }

    if ($httpCode !== 200) {
        error_log('OpenAI API HTTP Error: ' . $httpCode . ' - ' . $response);
        return null;
    }

    $result = json_decode($response, true);
    if (isset($result['error'])) {
        error_log('OpenAI API Error: ' . json_encode($result['error']));
        return null;
    }

    return $result['choices'][0]['message']['content'] ?? null;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$message = trim($data['message'] ?? '');

function formatAppt($a) {
    $date = date('M j, Y', strtotime($a['date']));
    $time = date('g:i A', strtotime($a['time']));
    $dentist = $a['dentistId'] ? " (Dentist ID: {$a['dentistId']})" : '';
    return "{$date} at {$time}{$dentist}";
}

$appointments = listClientAppointments($userId) ?: [];
$upcoming = array_values(array_filter($appointments, function($appt){
    return strtotime($appt['date']) >= strtotime(date('Y-m-d'));
}));

$lower = strtolower($message);

// Enhanced keyword-based responses with expanded coverage
if ($lower === '') {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit;
}

// Function to check if message contains any of the keywords
function containsAny($message, $keywords) {
    foreach ($keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

$reply = '';

// Booking and scheduling
if (containsAny($lower, ['book', 'schedule', 'make appointment', 'set up', 'new appointment'])) {
    $reply = "To book an appointment, go to the Appointments page and click 'Book Appointment'. Choose your preferred date, time, and dentist. You'll receive a confirmation email once booked. If you have any special requirements, please mention them during booking.";
}

// Rescheduling and cancellation
elseif (containsAny($lower, ['reschedule', 'change', 'cancel', 'modify', 'postpone', 'move'])) {
    $rescheduleHelp = "To reschedule or cancel an appointment, visit your Appointments page and click on the specific appointment you want to modify. Please provide at least 24 hours' notice for changes to avoid cancellation fees.";
    if (count($upcoming) > 0) {
        $rescheduleHelp .= "\n\nYour upcoming appointments: " . implode("; ", array_map('formatAppt', $upcoming)) . "\n\nIf you tell me which appointment and when you'd like to change it to, I can guide you better!";
    } else {
        $rescheduleHelp .= " You don't have any upcoming appointments to reschedule.";
    }
    $reply = $rescheduleHelp;
}

// Services and treatments
elseif (containsAny($lower, ['service', 'treatment', 'what do you do', 'offer', 'provide', 'specialty', 'procedure'])) {
    $reply = "We offer comprehensive dental services including: Dental Cleaning, Fillings, Root Canal Treatment, Teeth Whitening, Dental Implants, Tooth Extraction, Orthodontic Braces, and Dentures. Each treatment is performed by our experienced dentists using modern equipment. Would you like details about a specific service?";
}

// Clinic hours and availability
elseif (containsAny($lower, ['hour', 'time', 'open', 'close', 'when', 'available', 'operating'])) {
    $reply = "Our clinic hours are: Monday-Friday 9:00 AM to 6:00 PM, Saturday 9:00 AM to 4:00 PM. We're closed on Sundays and major holidays. For urgent matters outside these hours, please contact emergency dental services in your area.";
}

// Payment and costs
elseif (containsAny($lower, ['payment', 'cost', 'price', 'fee', 'charge', 'pay', 'billing', 'insurance'])) {
    $reply = "Payment information is available in your Payments page. We accept cash, credit/debit cards, and online payments. Treatment costs vary by service - please check your Payments page for detailed pricing or contact us for a personalized quote. We also work with most dental insurance plans.";
}

// Contact information
elseif (containsAny($lower, ['contact', 'phone', 'address', 'location', 'call', 'reach', 'find'])) {
    $reply = "You can find our contact information on the Contact page, including phone number, email, and physical address. We're located in a convenient spot with easy access. Feel free to reach out anytime during business hours!";
}

// Dental emergencies
elseif (containsAny($lower, ['emergency', 'urgent', 'pain', 'hurt', 'ache', 'broken tooth', 'swelling'])) {
    $reply = "For dental emergencies, please call our clinic immediately during business hours (Mon-Fri 9AM-6PM, Sat 9AM-4PM). If it's after hours, seek care at the nearest emergency dental clinic or hospital emergency room. Don't ignore severe pain or trauma!";
}

// Insurance and coverage
elseif (containsAny($lower, ['insurance', 'coverage', 'dental plan', 'benefits', 'claim', 'reimbursement'])) {
    $reply = "We accept most major dental insurance plans. Coverage details depend on your specific plan. Please bring your insurance card to your appointment, and our staff will help verify your benefits and handle claims. We also offer payment plans for uninsured patients.";
}

// Preparation for appointments
elseif (containsAny($lower, ['prepare', 'before', 'what to bring', 'documents', 'forms', 'ready'])) {
    $reply = "Before your appointment, please bring your ID, insurance card (if applicable), and any relevant medical history. Arrive 15 minutes early to complete paperwork. If you have X-rays from another dentist, bring those too. Let us know about any medications or health conditions.";
}

// Aftercare and recovery
elseif (containsAny($lower, ['after', 'care', 'recovery', 'post', 'follow up', 'instructions'])) {
    $reply = "Aftercare instructions vary by treatment. Generally, follow your dentist's specific recommendations, maintain good oral hygiene, and contact us if you experience unusual pain or complications. We'll schedule follow-up appointments as needed to ensure proper healing.";
}

// User's appointments
elseif (containsAny($lower, ['my appointments', 'upcoming', 'next', 'when is my', 'appointment list'])) {
    if (count($upcoming) === 0) {
        $reply = "You don't have any upcoming appointments scheduled. Would you like help booking one? You can use the 'Book Appointment' button on the Appointments page.";
    } else {
        $lines = array_map('formatAppt', $upcoming);
        $reply = "Here are your upcoming appointments:\n" . implode("\n", array_map(function($line) { return "• " . $line; }, $lines)) . "\n\nIf you need to make changes, visit the Appointments page.";
    }
}

// Time and date queries
elseif (containsAny($lower, ['what time is it', 'current time', 'time now', 'what\'s the time'])) {
    $currentTime = date('g:i A');
    $reply = "The current time is {$currentTime}. If you're asking about clinic hours, we're open Monday-Friday 9AM-6PM and Saturday 9AM-4PM.";
}

elseif (containsAny($lower, ['what date is it', 'today\'s date', 'current date', 'what day is it', 'what\'s today'])) {
    $currentDate = date('l, F j, Y');
    $dayOfWeek = date('l');
    $reply = "Today is {$currentDate}. It's a {$dayOfWeek}. If you have any appointments scheduled, you can check them in your Appointments page.";
}

elseif (containsAny($lower, ['what month is it', 'current month', 'what year is it'])) {
    $currentMonth = date('F Y');
    $reply = "We're currently in {$currentMonth}. Our clinic is fully operational this month with all services available.";
}

// Weather and general queries (simulated)
elseif (containsAny($lower, ['weather', 'temperature', 'hot', 'cold', 'raining', 'sunny'])) {
    $weatherResponses = [
        "I'm a dental assistant, so I don't have access to current weather information. However, I can help you with dental care tips! For instance, cold weather can be tough on teeth - remember to protect your smile.",
        "While I can't check the weather for you, I can tell you that maintaining good oral health is important regardless of the weather. Regular dental check-ups are key to a healthy smile!",
        "I specialize in dental information rather than weather forecasts. If you're concerned about weather-related dental issues (like cold sensitivity), I'd be happy to provide oral health advice."
    ];
    $reply = $weatherResponses[array_rand($weatherResponses)];
}

// Daily life questions
elseif (containsAny($lower, ['how are you', 'how do you do', 'how\'s it going'])) {
    $responses = [
        "I'm doing well, thank you for asking! I'm here and ready to help with all your dental appointment needs. How can I assist you today?",
        "I'm functioning optimally and excited to help you with your dental care questions! What would you like to know?",
        "I'm great, thanks! As your dental assistant, I'm always ready to provide information about appointments, treatments, and clinic services."
    ];
    $reply = $responses[array_rand($responses)];
}

elseif (containsAny($lower, ['thank you', 'thanks', 'appreciate', 'grateful'])) {
    $responses = [
        "You're very welcome! I'm glad I could help. Don't hesitate to ask if you have any more questions about your dental care.",
        "My pleasure! Remember, your oral health is important to us. Feel free to reach out anytime.",
        "You're welcome! I'm here whenever you need assistance with appointments or dental information."
    ];
    $reply = $responses[array_rand($responses)];
}

elseif (containsAny($lower, ['bye', 'goodbye', 'see you', 'talk later'])) {
    $responses = [
        "Goodbye! Take care of your smile, and I'll see you next time you need dental assistance.",
        "Farewell! Remember to schedule your next dental check-up. Stay healthy!",
        "See you later! Don't forget - regular dental visits are key to maintaining a beautiful smile."
    ];
    $reply = $responses[array_rand($responses)];
}

// General questions and greetings
elseif (containsAny($lower, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
    $hour = date('H');
    $greeting = 'Hello';
    if ($hour < 12) $greeting = 'Good morning';
    elseif ($hour < 17) $greeting = 'Good afternoon';
    else $greeting = 'Good evening';

    $reply = "{$greeting}! I'm your dental appointment assistant. I'm here to help with any questions about scheduling, treatments, payments, or clinic information. What can I assist you with today?";
}

// Fun and engaging responses
elseif (containsAny($lower, ['joke', 'funny', 'laugh', 'smile'])) {
    $jokes = [
        "Why did the dentist take up gardening? Because he wanted to improve his root canal work! 😁 What else can I help you with?",
        "Why did the scarecrow become a successful dentist? Because he was outstanding in his field! 🥕 How can I assist you today?",
        "What do you call a dentist's advice? A root awakening! 🌱 Feel free to ask me about real dental advice too!"
    ];
    $reply = $jokes[array_rand($jokes)];
}

elseif (containsAny($lower, ['motivate', 'inspire', 'encourage', 'positive'])) {
    $motivations = [
        "Remember: A healthy smile is a confident smile! Keep up the great work with your oral hygiene. You've got this! 💪🦷",
        "Every smile tells a story - make yours a story of health and happiness! Regular dental care is the first step. 🌟",
        "Your oral health is worth investing in. A beautiful, healthy smile can boost your confidence and well-being! ✨"
    ];
    $reply = $motivations[array_rand($motivations)];
}

// Health and wellness (general)
elseif (containsAny($lower, ['healthy', 'exercise', 'diet', 'nutrition', 'wellness'])) {
    $reply = "While I specialize in dental health, I can share that good oral hygiene contributes to overall wellness! Remember to brush twice daily, floss regularly, and visit us for check-ups. A healthy mouth supports a healthy body. Would you like dental care tips?";
}

elseif (containsAny($lower, ['tired', 'sleep', 'rest', 'energy'])) {
    $reply = "Getting enough rest is important for overall health, including oral health! Good sleep supports your immune system and healing. If dental issues are keeping you up, please schedule a consultation - we can help address any concerns affecting your sleep.";
}

// Random fun facts
elseif (containsAny($lower, ['fact', 'interesting', 'did you know', 'fun fact'])) {
    $facts = [
        "Did you know? The average person spends about 38.5 days brushing their teeth over a lifetime! That's a lot of toothpaste! 🧼",
        "Fun fact: A smile uses 17 muscles, while a frown uses 43. Smiling is actually easier and healthier! 😊",
        "Interesting: The first toothbrush was invented in China around 1498 and was made from pig bristles! 🐖",
        "Did you know? Humans have 4 types of teeth: incisors, canines, premolars, and molars. Each has a special job! 🦷"
    ];
    $reply = $facts[array_rand($facts)];
}

// Location and parking
elseif (containsAny($lower, ['parking', 'where', 'directions', 'map', 'get here'])) {
    $reply = "We're conveniently located with ample parking available. Check the Contact page for our exact address and directions. We have wheelchair accessibility and are near public transportation routes.";
}

// Age-specific questions
elseif (containsAny($lower, ['children', 'kids', 'pediatric', 'family', 'senior'])) {
    $reply = "We provide dental care for patients of all ages, from children to seniors. Our dentists are experienced in family dentistry and can address age-specific concerns. We create a comfortable environment for everyone!";
}

// Technology and equipment
elseif (containsAny($lower, ['equipment', 'technology', 'modern', 'digital', 'x-ray', 'sterile'])) {
    $reply = "We use state-of-the-art dental technology including digital X-rays, intraoral cameras, and modern sterilization equipment. This ensures accurate diagnoses and comfortable, efficient treatments.";
}

// Fallback responses - use AI if available
else {
    $apiKey = $config['openai_api_key'] ?? '';
    if (!empty($apiKey)) {
        // Build context for AI
        $clinicInfo = "Dents-City Dental Clinic offers: Dental Cleaning, Fillings, Root Canal Treatment, Teeth Whitening, Dental Implants, Tooth Extraction, Orthodontic Braces, and Dentures. Clinic hours: Mon-Fri 9AM-6PM, Sat 9AM-4PM. Located conveniently with parking. Accepts most insurance, cash, cards.";

        $userContext = "User has " . count($upcoming) . " upcoming appointments.";
        if (count($upcoming) > 0) {
            $userContext .= " Upcoming: " . implode("; ", array_map('formatAppt', $upcoming));
        }

        $prompt = "You are a helpful dental clinic assistant. Clinic info: $clinicInfo\nUser context: $userContext\nUser question: $message\nProvide a helpful, concise response. If about appointments, be specific. If rescheduling, guide to Appointments page.";

        try {
            $aiReply = callOpenAI($prompt, $apiKey);
            if ($aiReply) {
                $reply = trim($aiReply);
            } else {
                // Fallback if AI fails
                $reply = "I'm sorry, I'm having trouble processing your request right now. Could you try rephrasing your question or check our FAQ section?";
            }
        } catch (Exception $e) {
            error_log('Chatbot AI error: ' . $e->getMessage());
            $reply = "I'm experiencing technical difficulties with AI responses. Please try again later or check our FAQ for common questions.";
        }
    } else {
        // No AI key set - provide mock AI response for demonstration
        $mockResponses = [
            "Based on your question about '{$message}', I recommend scheduling a consultation with one of our dentists. They can provide personalized advice for your dental needs. Would you like help booking an appointment?",
            "That's an interesting question! While I don't have specific information about that topic, I can tell you that maintaining regular dental check-ups is key to overall oral health. Our clinic offers comprehensive services to address various dental concerns.",
            "I understand you're asking about '{$message}'. For the most accurate and personalized advice, I suggest speaking with our dental professionals. They have extensive experience in all aspects of dental care and can guide you appropriately.",
            "Regarding your question, dental health is interconnected with overall wellness. Regular cleanings, proper oral hygiene, and professional check-ups are fundamental. If you have specific concerns, our team is here to help!",
            "That's a good question! While I can provide general information, individual dental needs vary. I recommend consulting with our dentists who can assess your specific situation and provide tailored recommendations."
        ];
        $reply = $mockResponses[array_rand($mockResponses)];

        // Add note about AI integration
        $reply .= "\n\n*Note: AI responses are currently using demonstration mode. To enable full AI capabilities, add your OpenAI API key to config.php*";
    }
}

echo json_encode(['success' => true, 'reply' => $reply]);

?>