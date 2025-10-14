<?php

use GuzzleHttp\Client;

function handleAIReview() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $pr_data = $input['pr'] ?? null;
    
    if (!$pr_data) {
        http_response_code(400);
        echo json_encode(['error' => 'PR data required']);
        return;
    }
    
    $api_key = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
    
    if (!$api_key) {
        http_response_code(500);
        echo json_encode(['error' => 'GEMINI_API_KEY not configured']);
        return;
    }
    
    try {
        $review = generateGeminiReview($pr_data, $api_key);
        echo json_encode(['review' => $review]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'AI Review failed: ' . $e->getMessage()]);
    }
}

function generateGeminiReview($pr_data, $api_key) {
    $client = new Client();
    
    // Build the prompt for Gemini
    $prompt = buildReviewPrompt($pr_data);
    
    // Using Gemini 2.5 Flash
    $model = 'gemini-2.5-flash';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    
    $response = $client->post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $api_key,
        ],
        'json' => [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 4096,
                'thinkingConfig' => [
                    'thinkingBudget' => 0  // Disable thinking for faster responses
                ]
            ]
        ]
    ]);
    
    $result = json_decode($response->getBody()->getContents(), true);
    
    $reviewText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No review generated';
    
    return [
        'content' => $reviewText,
        'model' => $model,
        'timestamp' => date('c')
    ];
}

function buildReviewPrompt($pr_data) {
    $title = $pr_data['title'] ?? 'No title';
    $body = $pr_data['body'] ?? 'No description';
    $diff = $pr_data['diff'] ?? 'No diff available';
    $files = $pr_data['files'] ?? [];
    $additions = $pr_data['additions'] ?? 0;
    $deletions = $pr_data['deletions'] ?? 0;
    
    $files_summary = '';
    if (!empty($files)) {
        $files_summary = "\n### Files Changed (" . count($files) . " files)\n";
        foreach ($files as $file) {
            $path = $file['path'] ?? 'unknown';
            $adds = $file['additions'] ?? 0;
            $dels = $file['deletions'] ?? 0;
            $files_summary .= "- `{$path}` (+{$adds}/-{$dels})\n";
        }
    }
    
    return <<<PROMPT
You are a senior software engineer performing a thorough code review. Analyze this Pull Request with attention to detail and provide actionable, constructive feedback.

# Pull Request Overview

**Title:** {$title}

**Description:** 
{$body}

**Changes Summary:** +{$additions} additions, -{$deletions} deletions

{$files_summary}

---

# Code Diff

\`\`\`diff
{$diff}
\`\`\`

---

# Review Instructions

Please provide a comprehensive code review with the following structure:

## 1. Summary
Brief 2-3 sentence overview of what this PR does and your overall assessment.

## 2. Critical Issues 🔴
List any critical bugs, security vulnerabilities, or breaking changes that MUST be addressed before merging.

## 3. Code Quality Review

### Architecture & Design
- Evaluate the approach and design patterns used
- Suggest improvements to structure or organization

### Best Practices
- Identify violations of language/framework best practices
- Point out code smells or anti-patterns

### Performance Considerations
- Flag potential performance bottlenecks
- Suggest optimizations where relevant

### Error Handling
- Check for proper error handling and edge cases
- Identify missing validations or boundary checks

## 4. Security Review 🔒
- Check for security vulnerabilities (SQL injection, XSS, etc.)
- Review authentication/authorization logic
- Flag any exposed sensitive data

## 5. Suggestions & Improvements 💡
Provide specific, actionable suggestions with code examples where helpful. Format like:

**File:** `path/to/file.php`
**Line:** ~42
**Issue:** Brief description
**Suggestion:** 
\`\`\`php
// Better approach
\`\`\`

## 6. Positive Highlights ✅
Call out things done well (clean code, good tests, smart solutions, etc.)

## 7. Overall Recommendation
- ✅ **Approve** - Ready to merge
- 🔄 **Request Changes** - Needs fixes before merging  
- 💬 **Comment** - Optional improvements, but not blocking

---

**Important Guidelines:**
- Be specific with line numbers and file references
- Provide code examples for suggested improvements
- Focus on meaningful issues, not nitpicks
- Be constructive and encouraging in tone
- If the PR is small/trivial, keep the review proportional
PROMPT;
}