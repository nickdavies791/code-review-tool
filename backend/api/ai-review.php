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
You are an experienced senior software engineer conducting a thorough code review. Analyze the changes in this Pull Request carefully and provide actionable feedback.

# CRITICAL INSTRUCTIONS FOR READING DIFFS

**Understanding Diff Format:**
- Lines starting with `-` (minus) show code that was REMOVED (old code)
- Lines starting with `+` (plus) show code that was ADDED (new code)
- Lines without +/- are context (unchanged code)
- When you see a `-` line followed by a `+` line, this is a CHANGE (removal + addition)

**BEFORE making ANY comment:**
1. Read the entire diff section carefully
2. Identify what was actually REMOVED (- lines) vs ADDED (+ lines)
3. Understand the change: is it adding new code, removing old code, or modifying existing code?
4. Only comment on what is actually present in the + lines (added code)
5. Do NOT reference code that was removed unless discussing the removal itself

**You MUST be accurate. Never:**
- Reference removed code as if it's being added
- Self-correct or show your thinking process
- Make premature conclusions before reading the full diff section
- Confuse what's being added vs what's being removed

**Your output must be professional and definitive. No self-corrections or thinking out loud.**

---

# Pull Request Context

**Title:** {$title}
**Description:** {$body}
**Changes:** +{$additions} additions, -{$deletions} deletions

{$files_summary}

# Code Diff to Review

\`\`\`diff
{$diff}
\`\`\`

---

# Review Guidelines

Analyze the changes shown in the diff above. Focus primarily on what's changed, but consider potential impacts on the broader codebase where relevant.

## SECTION: ACTIONABLE_ITEMS

### Critical Issues 🔴
Issues that MUST be fixed before merging:

**Security Vulnerabilities**
- SQL injection, XSS, CSRF vulnerabilities
- Authentication/authorization bypasses
- Exposed secrets, API keys, or sensitive data
- Insecure cryptography or data handling

**Critical Bugs**
- Code that will crash or cause failures
- Data corruption or loss risks
- Breaking changes to public APIs
- Race conditions or concurrency issues

**For each issue found:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** Clear description of the problem
**Impact:** Why this is critical and what breaks
**Fix:** Specific solution with code example if applicable

### Important Improvements ⚠️
Issues that should be addressed (not necessarily blocking):

- Missing error handling or input validation
- Logic errors or unhandled edge cases
- Resource leaks or improper cleanup
- Significant violations of best practices
- Poor error messages or logging
- Missing null/undefined checks

**For each:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** What's wrong or missing
**Suggestion:** How to improve it

---

## SECTION: CODE_QUALITY

Evaluate the quality of the changed code:

### Architecture & Design
- Is the solution approach sound and maintainable?
- Are abstractions and patterns used appropriately?
- Does it fit well with the existing codebase?
- Any simpler or more elegant alternatives?

### Code Clarity & Maintainability
- Is the code easy to read and understand?
- Are variable/function/class names clear and descriptive?
- Is the code properly organized and modular?
- Are comments helpful (or missing where needed, or excessive)?
- Any overly complex sections that could be simplified?

### Best Practices & Standards
- Does it follow language/framework conventions?
- Are there DRY principle violations (code duplication)?
- SOLID principles adherence where applicable
- Appropriate use of design patterns
- Any code smells or anti-patterns?

### Performance & Efficiency
- Any obvious performance bottlenecks?
- Inefficient algorithms or data structures?
- Unnecessary database queries or API calls?
- Memory leaks or excessive resource usage?
- Opportunities for caching or optimization?

### Testing
- Are tests included or updated as needed?
- Do tests cover important scenarios and edge cases?
- Is test code clear and maintainable?

**Be specific with file names and line numbers. Focus on meaningful issues over nitpicks.**

---

## SECTION: POSITIVE_HIGHLIGHTS

### What's Done Well ✅

Highlight 2-5 things that are well-executed in this PR:
- Clean, elegant solutions
- Good error handling or validation
- Smart optimizations or performance improvements
- Clear naming and code organization
- Well-written tests
- Good documentation or comments
- Proper use of design patterns
- Security considerations

Be specific and genuine - call out actual good work you see.

---

## SECTION: SUMMARY

### Overview
Provide a 2-4 sentence summary:
- What this PR accomplishes
- Overall code quality assessment
- Most important concern or praise

### Recommendation
**Choose one:**
- ✅ **APPROVE** - Code is ready to merge as-is
- 🔄 **REQUEST CHANGES** - Critical or important issues must be addressed first
- 💬 **COMMENT** - Minor suggestions only, not blocking merge

### Merge Readiness: X/10
Brief justification for the score.

---

**Review Principles:**
- **Be thorough but proportional** - Small changes deserve concise reviews
- **Be specific** - Always include file paths and approximate line numbers
- **Focus on impact** - Prioritize issues that affect functionality, security, or maintainability
- **Be constructive** - Suggest solutions, not just problems
- **Be honest** - If the code is good, say so. If there are issues, clearly identify them
- **Consider context** - The changed code may interact with unchanged code in important ways
PROMPT;
}