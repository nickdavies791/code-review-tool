<?php

use GuzzleHttp\Client;

function handleAIReview() {
    // Increase execution time limit for AI requests (can take 60-120 seconds)
    set_time_limit(180); // 3 minutes

    $input = json_decode(file_get_contents('php://input'), true);

    $pr_data = $input['pr'] ?? null;
    $custom_guidelines = $input['customGuidelines'] ?? null;

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
        $review = generateGeminiReview($pr_data, $api_key, $custom_guidelines);
        echo json_encode(['review' => $review]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'AI Review failed: ' . $e->getMessage()]);
    }
}

function generateGeminiReview($pr_data, $api_key, $custom_guidelines = null) {
    $client = new Client([
        'timeout' => 120,  // 2 minutes for the HTTP request
        'connect_timeout' => 10  // 10 seconds to establish connection
    ]);

    // Build the prompt for Gemini
    $prompt = buildReviewPrompt($pr_data, $custom_guidelines);

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

function buildReviewPrompt($pr_data, $custom_guidelines = null) {
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

    // Build custom guidelines section if provided
    $custom_guidelines_section = '';
    if (!empty($custom_guidelines)) {
        $custom_guidelines_section = <<<GUIDELINES

---

# IMPORTANT: Custom Code Review Guidelines

The organization has provided specific code review guidelines that MUST be followed in addition to the standard review criteria below:

{$custom_guidelines}

---

Please ensure your review addresses these custom guidelines alongside the standard review sections. Reference these guidelines when evaluating code quality and making recommendations.

GUIDELINES;
    }

    return <<<PROMPT
You're a senior software engineer reviewing this pull request. Provide thorough, actionable feedback focused on security, code quality, and maintainability.
{$custom_guidelines_section}

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

### Critical Issues - Must Fix Before Merging

Look for serious problems that create security vulnerabilities or break functionality:

**Security Vulnerabilities**
- Use of dangerous functions: eval(), innerHTML, dangerouslySetInnerHTML, document.write(), new Function()
- SQL injection vulnerabilities from string concatenation in queries
- XSS vulnerabilities from unsanitized user input
- Hardcoded passwords, API keys, secrets, or tokens in the code
- Missing authentication or authorization checks
- Insecure data transmission (missing HTTPS, encryption)
- Command injection from unsanitized exec() or system() calls
- Path traversal vulnerabilities
- Insecure random number generation for security-critical operations
- Missing CSRF protection on state-changing operations

**Critical Bugs**
- Code that will throw uncaught exceptions or crash
- Null pointer/undefined reference errors
- Data corruption risks (race conditions, improper locking)
- Memory leaks or resource leaks
- Breaking changes to public APIs without migration path
- Logic errors that produce incorrect results
- Infinite loops or recursion without base case

**For each critical issue:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** Exact description of the security vulnerability or bug
**Impact:** What breaks, data at risk, or security implications
**Fix:** Specific code solution or approach to resolve it

### Important Improvements - Should Address

Issues that don't block merge but reduce code quality:

**Code Quality**
- Missing error handling for external calls (API, database, file I/O)
- Unhandled edge cases (empty arrays, null values, zero division)
- Complex nested logic that's hard to follow (cognitive complexity)
- High cyclomatic complexity (too many decision paths)
- Missing input validation
- Poor error messages that don't help debugging
- Resource cleanup missing (unclosed files, connections, timers)
- Magic numbers or hardcoded values without explanation

**Maintainability**
- Unclear variable or function names
- Duplicated code that should be extracted
- Functions doing too many things (violating single responsibility)
- Missing documentation for complex logic
- Commented-out code that should be removed
- TODO/FIXME comments for incomplete implementations

**For each:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** What could be better
**Suggestion:** How to improve it

---

## SECTION: CODE_QUALITY

Evaluate the quality of the implementation:

### Architecture & Design
- Does the solution follow good design principles (SOLID, separation of concerns)?
- Is the abstraction level appropriate?
- Does it integrate well with existing patterns in the codebase?
- Are there simpler approaches that would work as well?
- Any over-engineering or premature optimization?

### Code Clarity
- Is the code self-documenting with clear names?
- Is the logic flow easy to trace?
- Are functions and classes focused on a single responsibility?
- Is the code organized logically?
- Are comments used appropriately (why, not what)?

### Coding Standards
- Consistent with project style and conventions?
- Following language-specific best practices?
- Avoiding anti-patterns?
- DRY principle followed (no unnecessary duplication)?
- Proper use of type systems (TypeScript types, PHP types, etc.)?

### Performance & Efficiency
- Any N+1 query problems or unnecessary database calls?
- Inefficient algorithms (could use better data structures)?
- Unnecessary re-rendering or re-computation?
- Memory usage appropriate for the task?
- Opportunities for caching or memoization?

### Testing
- Are there tests for the new functionality?
- Do tests cover edge cases and error paths?
- Are tests maintainable and clear?
- Is test coverage adequate for critical paths?

**Be specific with file paths and line numbers. Focus on issues that meaningfully impact code quality, security, or maintainability.**

---

## SECTION: POSITIVE_HIGHLIGHTS

### What's Done Well

Highlight 2-5 things that demonstrate good engineering:
- Well-structured, clean solutions
- Proper error handling and validation
- Good security practices (input sanitization, authentication)
- Performance optimizations
- Clear, maintainable code
- Comprehensive test coverage
- Good documentation or helpful comments
- Smart use of design patterns
- Defensive programming techniques

Be specific about what's good and why it matters.

---

## SECTION: SUMMARY

### Overview
In 2-4 sentences:
- What this pull request accomplishes
- Overall assessment of code quality and implementation
- Most significant concern or notable achievement

### Recommendation
**Choose one:**
- **APPROVE** - Code is production-ready, no blocking issues
- **REQUEST CHANGES** - Critical issues must be fixed before merge
- **COMMENT** - Suggestions provided but not blocking merge

### Merge Readiness: X/10
Justify the score based on code quality, security, test coverage, and impact.

---

**Review Guidelines:**
- Match depth to PR size: small changes need focused reviews, large changes need thorough analysis
- Always specify file paths and approximate line numbers for issues
- Prioritize security vulnerabilities and breaking bugs over style preferences
- Provide actionable solutions, not just problem identification
- Consider how changes interact with unchanged code
- Be direct and honest: recognize good work and clearly flag real problems
PROMPT;
}