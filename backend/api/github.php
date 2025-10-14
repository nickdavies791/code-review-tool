<?php

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
    // Get list of user's repos (or could search)
    $command = 'gh repo list --json name,owner,url --limit 20';
    $result = executeGhCommand($command);
    
    if (isset($result['error'])) {
        http_response_code(500);
        echo json_encode($result);
        return;
    }
    
    echo json_encode(['repos' => $result]);
}

function handleGetPRs() {
    $repo = $_GET['repo'] ?? null;
    
    if (!$repo) {
        http_response_code(400);
        echo json_encode(['error' => 'Repo parameter required']);
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
    
    // Get PR details
    $command = "gh pr view $pr_number --repo $repo --json title,body,author,files,additions,deletions,commits";
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
    
    echo json_encode(['pr' => $pr_data]);
}