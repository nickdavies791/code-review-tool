<?php

function sanitizeInput($input) {
    // Remove any characters that could be used for command injection
    // Only allow alphanumeric, hyphen, underscore, forward slash, and dot
    return preg_replace('/[^a-zA-Z0-9\-_\/\.]/', '', $input);
}

function executeGhCommand($command) {
    $output = [];
    $return_var = 0;
    exec($command . ' 2>&1', $output, $return_var);

    if ($return_var !== 0) {
        return ['error' => 'GitHub CLI error: ' . implode("\n", $output)];
    }

    return json_decode(implode("\n", $output), true);
}

function handleGetRepos() {
    $allRepos = [];

    // Get user's personal repos
    $command = 'gh repo list --json nameWithOwner --limit 1000';
    $userRepos = executeGhCommand($command);

    if (!isset($userRepos['error']) && is_array($userRepos)) {
        foreach ($userRepos as $repo) {
            $allRepos[] = $repo['nameWithOwner'];
        }
    }

    // Get list of organizations
    $orgsCommand = 'gh api user/orgs --jq ".[].login"';
    $orgsOutput = [];
    exec($orgsCommand . ' 2>&1', $orgsOutput, $return_var);

    if ($return_var === 0) {
        // Fetch repos from each organization
        foreach ($orgsOutput as $org) {
            $org = sanitizeInput(trim($org));
            if (!empty($org)) {
                $orgReposCommand = "gh repo list $org --json nameWithOwner --limit 1000";
                $orgRepos = executeGhCommand($orgReposCommand);

                if (!isset($orgRepos['error']) && is_array($orgRepos)) {
                    foreach ($orgRepos as $repo) {
                        $allRepos[] = $repo['nameWithOwner'];
                    }
                }
            }
        }
    }

    // Sort repos alphabetically
    sort($allRepos);

    echo json_encode(['repos' => $allRepos]);
}

function handleGetPRs() {
    $repo = $_GET['repo'] ?? null;

    if (!$repo) {
        http_response_code(400);
        echo json_encode(['error' => 'Repo parameter required']);
        return;
    }

    // Sanitize repo parameter to prevent command injection
    $repo = sanitizeInput($repo);

    if (empty($repo)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid repo parameter']);
        return;
    }

    // Fetch PRs for the repo
    $command = "gh pr list --repo $repo --json number,title,author,state,updatedAt,url --limit 30";
    $result = executeGhCommand($command);
    
    if (isset($result['error'])) {
        http_response_code(500);
        echo json_encode($result);
        return;
    }
    
    echo json_encode(['prs' => $result]);
}

function handleGetPRDetails() {
    $repo = $_GET['repo'] ?? null;
    $pr_number = $_GET['number'] ?? null;

    if (!$repo || !$pr_number) {
        http_response_code(400);
        echo json_encode(['error' => 'Repo and PR number required']);
        return;
    }

    // Sanitize inputs to prevent command injection
    $repo = sanitizeInput($repo);
    $pr_number = sanitizeInput($pr_number);

    if (empty($repo) || empty($pr_number) || !is_numeric($pr_number)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid repo or PR number parameter']);
        return;
    }

    // Get PR details with more fields
    $command = "gh pr view $pr_number --repo $repo --json title,body,author,files,additions,deletions,commits,state,createdAt,updatedAt,mergeable,labels,reviewDecision,url,headRefName,baseRefName,isDraft";
    $pr_data = executeGhCommand($command);

    if (isset($pr_data['error'])) {
        http_response_code(500);
        echo json_encode($pr_data);
        return;
    }

    // Get PR diff
    $diff_command = "gh pr diff $pr_number --repo $repo";
    $diff_output = [];
    exec($diff_command . ' 2>&1', $diff_output, $return_var);
    $pr_data['diff'] = implode("\n", $diff_output);

    // Get PR comments/conversation
    $comments_command = "gh api repos/$repo/issues/$pr_number/comments --jq '.[] | {author: .user.login, body: .body, createdAt: .created_at, id: .id}'";
    $comments_output = [];
    exec($comments_command . ' 2>&1', $comments_output, $return_var);

    $comments = [];
    if ($return_var === 0 && !empty($comments_output)) {
        $jsonString = implode("\n", $comments_output);
        // Parse multiple JSON objects (one per line)
        $lines = explode("\n", $jsonString);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $comment = json_decode($line, true);
                if ($comment) {
                    $comments[] = $comment;
                }
            }
        }
    }
    $pr_data['comments'] = $comments;

    // Get PR review comments (code review comments)
    $review_comments_command = "gh api repos/$repo/pulls/$pr_number/comments --jq '.[] | {author: .user.login, body: .body, path: .path, line: .line, createdAt: .created_at, id: .id}'";
    $review_comments_output = [];
    exec($review_comments_command . ' 2>&1', $review_comments_output, $return_var);

    $reviewComments = [];
    if ($return_var === 0 && !empty($review_comments_output)) {
        $jsonString = implode("\n", $review_comments_output);
        $lines = explode("\n", $jsonString);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $comment = json_decode($line, true);
                if ($comment) {
                    $reviewComments[] = $comment;
                }
            }
        }
    }
    $pr_data['reviewComments'] = $reviewComments;

    echo json_encode(['pr' => $pr_data]);
}