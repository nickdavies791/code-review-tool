<?php

use GuzzleHttp\Client;

function handleAIReview() {
    // Increase execution time limit for AI requests (can take 60-120 seconds)
    set_time_limit(180); // 3 minutes

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
    $client = new Client([
        'timeout' => 120,  // 2 minutes for the HTTP request
        'connect_timeout' => 10  // 10 seconds to establish connection
    ]);

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
                'temperature' => 0.3,
                'maxOutputTokens' => 8192,  // Increased for longer reviews
                'thinkingConfig' => [
                    'thinkingBudget' => 8192  // Allow thinking for accurate diff interpretation
                ]
            ]
        ]
    ]);
    
    $result = json_decode($response->getBody()->getContents(), true);

    $reviewText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No review generated';

    // Check if response was truncated
    $finishReason = $result['candidates'][0]['finishReason'] ?? null;
    $wasTruncated = false;

    if ($finishReason === 'MAX_TOKENS' || $finishReason === 'RECITATION') {
        $wasTruncated = true;
        error_log("Gemini response was truncated. Finish reason: " . $finishReason);
    }

    // Check if we have all required sections
    $hasAllSections =
        strpos($reviewText, '## SECTION: ACTIONABLE_ITEMS') !== false &&
        strpos($reviewText, '## SECTION: CODE_QUALITY') !== false &&
        strpos($reviewText, '## SECTION: POSITIVE_HIGHLIGHTS') !== false &&
        strpos($reviewText, '## SECTION: SUMMARY') !== false;

    if (!$hasAllSections && !$wasTruncated) {
        error_log("Warning: Review is missing some sections");
    }

    return [
        'content' => $reviewText,
        'model' => $model,
        'timestamp' => date('c'),
        'truncated' => $wasTruncated,
        'finishReason' => $finishReason,
        'hasAllSections' => $hasAllSections
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
You're a senior software engineer reviewing this pull request. Take your time to understand the changes and provide helpful, actionable feedback.

# How to Read the Code Changes

Lines starting with `+` are NEW code being added.
Lines starting with `-` are OLD code being removed.
Lines without symbols are unchanged (just for context).

Important: Only comment on the new code (the + lines). Don't confuse what's being added with what's being removed.

---

# What Changed

**Title:** {$title}
**Description:** {$body}
**Changes:** {$additions} lines added, {$deletions} lines removed

{$files_summary}

# The Code

\`\`\`diff
{$diff}
\`\`\`

---

# Your Review

## SECTION: ACTIONABLE_ITEMS

### Must Fix Before Merging 🔴

Look for serious problems that would break things or create security risks:

**Security Problems**
- Passwords, API keys, or secrets exposed in the code
- Missing authentication checks
- Unsafe handling of user input (could lead to hacks)
- Insecure data storage or transmission

**Breaking Bugs**
- Code that will crash the app
- Data could be lost or corrupted
- Changes that break existing functionality
- Multiple threads/processes could conflict

**For each problem:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** What's wrong in plain English
**Impact:** What breaks and why it matters
**Fix:** How to solve it (with code example if helpful)

### Should Improve ⚠️

Things that aren't critical but should be addressed:

- Missing checks for errors or bad input
- Edge cases that aren't handled
- Memory or resources not cleaned up properly
- Important coding standards not followed
- Unclear error messages
- Missing checks for null/empty values

**For each:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** What needs work
**Suggestion:** How to make it better

---

## SECTION: CODE_QUALITY

Share your thoughts on the code quality:

### Design Approach
- Is this a solid way to solve the problem?
- Does it fit well with how the rest of the code works?
- Could it be done more simply?

### Readability
- Is the code easy to follow?
- Are names clear and descriptive?
- Is it well organized?
- Are comments helpful (not too many, not too few)?
- Any confusing parts that could be clearer?

### Coding Standards
- Does it follow the project's conventions?
- Is code duplicated anywhere?
- Any known bad practices or common mistakes?

### Performance
- Any obvious slowdowns?
- Inefficient ways of doing things?
- Too many database or API calls?
- Memory usage concerns?
- Could caching help?

### Tests
- Are there tests for the new code?
- Do tests cover the important scenarios?
- Are tests easy to understand?

**Be specific with file names and line numbers. Focus on things that actually matter.**

---

## SECTION: POSITIVE_HIGHLIGHTS

### What's Done Well ✅

Call out 2-5 things that are genuinely good:
- Clever or clean solutions
- Good error handling
- Smart performance improvements
- Clear, well-organized code
- Good test coverage
- Helpful documentation
- Smart design choices
- Security done right

Be genuine - highlight the actual good work here.

---

## SECTION: SUMMARY

### Overview
In 2-4 sentences, summarize:
- What this pull request does
- Your overall impression of the code quality
- The main concern or what stands out most

### Recommendation
**Pick one:**
- ✅ **APPROVE** - Ready to merge
- 🔄 **REQUEST CHANGES** - Must fix issues first
- 💬 **COMMENT** - Minor suggestions, can merge as-is

### Merge Readiness: X/10
Why this score in one sentence.

---

**Keep in mind:**
- Match your review length to the change size - small changes need short reviews
- Always mention which files and roughly which lines
- Focus on what actually impacts the code working correctly and safely
- Suggest fixes, don't just point out problems
- Be honest and direct - praise good work, clearly identify real issues
PROMPT;
}