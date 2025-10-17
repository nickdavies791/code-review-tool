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
        strpos($reviewText, '## SECTION: TEST_SCENARIOS') !== false;

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
You're a senior software engineer conducting a thorough code review. Your job is to find REAL issues in the actual code being changed.

**Critical Instructions:**
- Read through ALL the code changes line by line
- Look for actual problems that exist in this specific PR
- Focus on security vulnerabilities, bugs, and performance issues (especially N+1 queries, missing indexes, inefficient loops)
- Be thorough but ONLY report issues you actually find in the code
- Do not provide generic advice - only specific issues with file paths and line numbers
- If the code is good, say so. Don't invent problems.

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

Carefully analyze ALL the code changes in the diff. Look line by line for actual issues present in this specific PR.

### Critical Issues - Must Fix Before Merging

Identify serious problems that create vulnerabilities, break functionality, or cause severe performance degradation:

**Security Vulnerabilities**
- SQL injection (string concatenation in queries, unsanitized input)
- XSS vulnerabilities (unsanitized output, dangerous functions)
- Authentication/authorization bypass or missing checks
- Hardcoded secrets, API keys, passwords, or tokens
- Command injection vulnerabilities
- Path traversal risks
- Insecure cryptography or random number generation
- Missing CSRF protection
- Insecure deserialization
- Exposed sensitive data in logs or responses

**Critical Bugs & Logic Errors**
- Code that will crash or throw uncaught exceptions
- Null/undefined reference errors
- Race conditions or data corruption risks
- Memory leaks or resource leaks (unclosed connections, files)
- Infinite loops or missing recursion base cases
- Off-by-one errors in loops or array access
- Type mismatches that cause runtime errors
- Breaking changes to APIs without migration path
- Incorrect business logic that produces wrong results

**Performance Issues - Must Fix**
- N+1 query problems (queries inside loops)
- Missing database indexes on filtered/joined columns
- Full table scans on large datasets
- Inefficient algorithms (O(n²) where O(n) possible)
- Loading unnecessary data (SELECT * when few columns needed)
- Memory-inefficient operations (loading entire files into memory)
- Missing pagination on large result sets
- Redundant database queries (same query called multiple times)
- Blocking operations on main thread/event loop

**For each critical issue found in the actual code:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** [Precise description of the actual problem in the code]
**Impact:** [What breaks, data at risk, or performance degradation]
**Fix:** [Specific code change or implementation approach]

### Important Improvements - Should Address

Real code quality issues found in this PR that reduce maintainability or correctness:

**Error Handling & Resilience**
- Missing error handling for external calls (API, DB, file I/O)
- Unhandled edge cases (empty arrays, null values, boundary conditions)
- Poor error messages that don't help debugging
- Swallowed exceptions without logging
- Missing input validation on user-provided data
- No graceful degradation for failures

**Code Quality & Design**
- Duplicated code that should be extracted
- Functions doing too many things (violating single responsibility)
- Complex nested logic that's hard to follow
- High cyclomatic complexity (too many branches)
- Magic numbers or strings without explanation
- Unclear variable or function names
- Missing documentation for complex logic
- Commented-out code left in
- TODO/FIXME indicating incomplete work

**Database & Data Issues**
- Missing transactions for multi-step data changes
- Incorrect SQL query logic
- Missing foreign key constraints
- Data validation missing before persistence
- Timezone handling issues with dates

**For each improvement found in the actual code:**
**File:** `path/to/file.ext` (Line ~X)
**Issue:** [What could be better]
**Suggestion:** [How to improve it]

**IMPORTANT:** Only report issues that actually exist in the code changes. Do not include generic advice. If no issues are found in a category, skip that category entirely.

---

## SECTION: TEST_SCENARIOS

Generate comprehensive test scenarios for this PR using Given-When-Then format. Cover:

### Happy Path Scenarios
Test the primary functionality with expected inputs and conditions:

**Format for each scenario:**
**Scenario X: [Brief description of what's being tested]**
- **Given:** [Initial state/preconditions]
- **When:** [Action being performed]
- **Then:** [Expected outcome]

**Example:**
**Scenario 1: User successfully logs in with valid credentials**
- **Given:** A registered user with username "john@example.com" and password "SecurePass123"
- **When:** User submits the login form with correct credentials
- **Then:** User should be redirected to the dashboard and see a welcome message

### Edge Cases & Error Scenarios
Test boundary conditions and failure modes:

**Format:**
**Scenario X: [Edge case or error condition]**
- **Given:** [Setup for edge case]
- **When:** [Triggering action]
- **Then:** [Expected handling/error message]

Cover:
- Empty/null inputs
- Maximum/minimum values
- Invalid data types
- Missing required fields
- Duplicate entries
- Concurrent operations
- Network failures
- Permission denied scenarios
- Resource not found

### Security Test Scenarios
If the PR touches authentication, authorization, data handling, or API endpoints:

**Format:**
**Scenario X: [Security test description]**
- **Given:** [Security context]
- **When:** [Attack or security check]
- **Then:** [Expected security behavior]

Cover:
- Unauthorized access attempts
- Input injection attempts (SQL, XSS, etc.)
- CSRF protection
- Data validation and sanitization
- Authentication bypass attempts
- Privilege escalation attempts

### Integration Test Scenarios
Test how new code interacts with existing systems:

**Format:**
**Scenario X: [Integration test description]**
- **Given:** [State of integrated systems]
- **When:** [Action triggering integration]
- **Then:** [Expected interaction result]

### Performance Test Scenarios (if applicable)
For features that may impact performance:

**Format:**
**Scenario X: [Performance test description]**
- **Given:** [Load conditions - e.g., "1000 concurrent users"]
- **When:** [Performance-critical operation]
- **Then:** [Expected performance metrics - e.g., "Response time < 200ms"]

**Guidelines:**
- Generate 8-15 test scenarios total
- Prioritize based on PR changes (more scenarios for complex/risky code)
- Be specific with actual values and conditions
- Reference specific files/functions being tested
- Include both automated test suggestions and manual testing steps where appropriate

---

**Review Guidelines:**
- Analyze EVERY line of code in the diff thoroughly
- Small PRs still need careful review - simple changes can have critical bugs
- N+1 queries and performance issues are CRITICAL - look for database calls in loops
- Always specify exact file paths and line numbers for every issue found
- Prioritize: 1) Security vulnerabilities, 2) Performance issues (N+1s, missing indexes), 3) Critical bugs, 4) Code quality
- Provide specific, actionable solutions with code examples
- Consider how new code interacts with existing patterns
- If you don't find issues, that's fine - don't invent them
- Be direct: clearly flag real problems, acknowledge good code
PROMPT;
}