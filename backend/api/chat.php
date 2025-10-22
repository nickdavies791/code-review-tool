<?php

use GuzzleHttp\Client;

function handleChat() {
    set_time_limit(60); // 1 minute for chat

    $input = json_decode(file_get_contents('php://input'), true);

    $pr_data = $input['pr'] ?? null;
    $review_content = $input['review'] ?? null;
    $messages = $input['messages'] ?? [];
    $question = $input['question'] ?? null;

    if (!$pr_data || !$review_content || !$question) {
        http_response_code(400);
        echo json_encode(['error' => 'PR data, review content, and question are required']);
        return;
    }

    $api_key = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');

    if (!$api_key) {
        http_response_code(500);
        echo json_encode(['error' => 'GEMINI_API_KEY not configured']);
        return;
    }

    try {
        $response = generateChatResponse($pr_data, $review_content, $messages, $question, $api_key);
        echo json_encode(['response' => $response]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Chat failed: ' . $e->getMessage()]);
    }
}

function generateChatResponse($pr_data, $review_content, $messages, $question, $api_key) {
    $client = new Client([
        'timeout' => 45,
        'connect_timeout' => 10
    ]);

    // Build context for the chat
    $context = buildChatContext($pr_data, $review_content);

    // Build conversation history
    $conversation = [];

    // Add system context as first message
    $conversation[] = [
        'role' => 'user',
        'parts' => [['text' => $context]]
    ];

    $conversation[] = [
        'role' => 'model',
        'parts' => [['text' => 'I understand. I have the PR details and the code review. I\'m ready to answer your questions about this review.']]
    ];

    // Add conversation history (skip the question we're about to add)
    foreach ($messages as $msg) {
        if ($msg['role'] === 'user') {
            $conversation[] = [
                'role' => 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        } elseif ($msg['role'] === 'assistant') {
            $conversation[] = [
                'role' => 'model',
                'parts' => [['text' => $msg['content']]]
            ];
        }
    }

    // Using Gemini 2.5 Flash for chat
    $model = 'gemini-2.5-flash';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

    $response = $client->post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $api_key,
        ],
        'json' => [
            'contents' => $conversation,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048,
            ]
        ]
    ]);

    $result = json_decode($response->getBody()->getContents(), true);
    $responseText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No response generated';

    return $responseText;
}

function buildChatContext($pr_data, $review_content) {
    $title = $pr_data['title'] ?? 'No title';
    $body = $pr_data['body'] ?? 'No description';
    $files = $pr_data['files'] ?? [];

    $files_list = '';
    if (!empty($files)) {
        $files_list = "\n### Files Changed:\n";
        foreach ($files as $file) {
            $path = $file['path'] ?? 'unknown';
            $adds = $file['additions'] ?? 0;
            $dels = $file['deletions'] ?? 0;
            $files_list .= "- `{$path}` (+{$adds}/-{$dels})\n";
        }
    }

    // Load prompt template from file
    $prompt_template = file_get_contents(__DIR__ . '/../prompts/chat-prompt.txt');

    // Replace placeholders
    $context = str_replace('{{PR_TITLE}}', $title, $prompt_template);
    $context = str_replace('{{PR_DESCRIPTION}}', $body, $context);
    $context = str_replace('{{FILES_LIST}}', $files_list, $context);
    $context = str_replace('{{REVIEW_CONTENT}}', $review_content, $context);

    return $context;
}
