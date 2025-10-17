<?php

function getGitHubToken() {
    $token = getenv('GITHUB_API_TOKEN');
    if (!$token) {
        return null;
    }
    return $token;
}

function githubApiRequest($endpoint, $method = 'GET') {
    $token = getGitHubToken();
    if (!$token) {
        return ['error' => 'GITHUB_API_TOKEN not configured'];
    }

    $ch = curl_init();
    $url = 'https://api.github.com/' . ltrim($endpoint, '/');

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/vnd.github+json',
        'User-Agent: Code-Review-Tool',
        'X-GitHub-Api-Version: 2022-11-28'
    ]);

    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['error' => 'GitHub API error: HTTP ' . $httpCode];
    }

    return json_decode($response, true);
}

function handleGetRepos() {
    $allRepos = [];

    // Get user's personal repos
    $page = 1;
    while ($page <= 10) { // Limit to 10 pages (1000 repos max)
        $userRepos = githubApiRequest("user/repos?per_page=100&page=$page&sort=updated");

        if (isset($userRepos['error'])) {
            http_response_code(500);
            echo json_encode($userRepos);
            return;
        }

        if (empty($userRepos)) {
            break;
        }

        foreach ($userRepos as $repo) {
            $allRepos[] = $repo['full_name'];
        }

        $page++;
    }

    // Get list of organizations
    $orgs = githubApiRequest('user/orgs');

    if (!isset($orgs['error']) && is_array($orgs)) {
        // Fetch repos from each organization
        foreach ($orgs as $org) {
            $orgLogin = $org['login'];
            $page = 1;

            while ($page <= 10) { // Limit to 10 pages per org
                $orgRepos = githubApiRequest("orgs/$orgLogin/repos?per_page=100&page=$page&sort=updated");

                if (isset($orgRepos['error']) || empty($orgRepos)) {
                    break;
                }

                foreach ($orgRepos as $repo) {
                    $allRepos[] = $repo['full_name'];
                }

                $page++;
            }
        }
    }

    // Remove duplicates and sort alphabetically
    $allRepos = array_unique($allRepos);
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

    // Validate repo format (owner/name)
    if (!preg_match('/^[a-zA-Z0-9\-_\.]+\/[a-zA-Z0-9\-_\.]+$/', $repo)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid repo format']);
        return;
    }

    // Fetch PRs for the repo (open and closed, sorted by updated)
    $prs = githubApiRequest("repos/$repo/pulls?state=all&per_page=30&sort=updated&direction=desc");

    if (isset($prs['error'])) {
        http_response_code(500);
        echo json_encode($prs);
        return;
    }

    // Format the response to match the expected structure
    $formattedPRs = array_map(function($pr) {
        return [
            'number' => $pr['number'],
            'title' => $pr['title'],
            'author' => ['login' => $pr['user']['login']],
            'state' => $pr['state'],
            'updatedAt' => $pr['updated_at'],
            'url' => $pr['html_url']
        ];
    }, $prs);

    echo json_encode(['prs' => $formattedPRs]);
}

function handleGetPRDetails() {
    $repo = $_GET['repo'] ?? null;
    $pr_number = $_GET['number'] ?? null;

    if (!$repo || !$pr_number) {
        http_response_code(400);
        echo json_encode(['error' => 'Repo and PR number required']);
        return;
    }

    // Validate inputs
    if (!preg_match('/^[a-zA-Z0-9\-_\.]+\/[a-zA-Z0-9\-_\.]+$/', $repo) || !is_numeric($pr_number)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid repo or PR number parameter']);
        return;
    }

    // Get PR details
    $pr_data = githubApiRequest("repos/$repo/pulls/$pr_number");

    if (isset($pr_data['error'])) {
        http_response_code(500);
        echo json_encode($pr_data);
        return;
    }

    // Get PR files
    $files = githubApiRequest("repos/$repo/pulls/$pr_number/files?per_page=100");
    $pr_data['files'] = isset($files['error']) ? [] : $files;

    // Get PR commits
    $commits = githubApiRequest("repos/$repo/pulls/$pr_number/commits?per_page=100");
    $pr_data['commits'] = isset($commits['error']) ? [] : $commits;

    // Get PR diff (construct from files)
    $diff = '';
    if (!isset($files['error']) && is_array($files)) {
        foreach ($files as $file) {
            if (isset($file['patch'])) {
                $diff .= "diff --git a/{$file['filename']} b/{$file['filename']}\n";
                $diff .= $file['patch'] . "\n";
            }
        }
    }
    $pr_data['diff'] = $diff;

    // Get PR comments (issue comments)
    $comments = githubApiRequest("repos/$repo/issues/$pr_number/comments?per_page=100");
    $formattedComments = [];
    if (!isset($comments['error']) && is_array($comments)) {
        foreach ($comments as $comment) {
            $formattedComments[] = [
                'author' => $comment['user']['login'],
                'body' => $comment['body'],
                'createdAt' => $comment['created_at'],
                'id' => $comment['id']
            ];
        }
    }
    $pr_data['comments'] = $formattedComments;

    // Get PR review comments (code review comments)
    $reviewComments = githubApiRequest("repos/$repo/pulls/$pr_number/comments?per_page=100");
    $formattedReviewComments = [];
    if (!isset($reviewComments['error']) && is_array($reviewComments)) {
        foreach ($reviewComments as $comment) {
            $formattedReviewComments[] = [
                'author' => $comment['user']['login'],
                'body' => $comment['body'],
                'path' => $comment['path'] ?? null,
                'line' => $comment['line'] ?? null,
                'createdAt' => $comment['created_at'],
                'id' => $comment['id']
            ];
        }
    }
    $pr_data['reviewComments'] = $formattedReviewComments;

    // Format field names to match expected structure
    $pr_data['author'] = ['login' => $pr_data['user']['login']];
    $pr_data['createdAt'] = $pr_data['created_at'];
    $pr_data['updatedAt'] = $pr_data['updated_at'];
    $pr_data['headRefName'] = $pr_data['head']['ref'];
    $pr_data['baseRefName'] = $pr_data['base']['ref'];
    $pr_data['isDraft'] = $pr_data['draft'];
    $pr_data['reviewDecision'] = null; // This requires a GraphQL API call, keeping as null for now
    $pr_data['url'] = $pr_data['html_url'];

    echo json_encode(['pr' => $pr_data]);
}