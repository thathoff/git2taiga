<?php

// get input
$project = isset($_GET['project']) ? $_GET['project'] : false;
$post_data = file_get_contents("php://input");
$push_info = json_decode($post_data);

// patterns and default texts
$close_pattern = "((?:[Cc]los(?:e[sd]?|ing)|[Ff]ix(?:e[sd]|ing)?) +(?:(?:issues? +)?#\d+(?:(?:, *| +and +)?))+)";
$issue_pattern = "/#([0-9]+)/";
$comment_text = "Closed by commit [%s](%s \"Open commit in GitLab\").";

// include config and taiga class
require "config.php";
require "Taiga.php";

// init new taiga instance
$taiga = new Blanko\Taiga(TAIGA_URL, $project);
$taiga->loginUser(TAIGA_USERNAME, TAIGA_PASSWORD);

header("Content-Type: text/plain");

// handle input
if ($project && $post_data && $push_info->ref) {
    // process only pushes to mater
    if ($push_info->ref == "refs/heads/master") {
        // loop through commits in push
        foreach ($push_info->commits as $commit) {
            // get message and prepare short commit id
            $message = $commit->message;
            $commit_id = substr($commit->id, 0, 8);

            // match agains close pattern
            if (preg_match_all($close_pattern, $message, $matches)) {
                // loop through matches
                foreach ($matches[0] as $match) {
                    // find all issue ids in matches
                    preg_match_all($issue_pattern, $match, $issues);
                    $issues = $issues[1];

                    // loop through issues
                    foreach ($issues as $issue) {
                        // try to close issues in taiga
                        $message = sprintf($comment_text, $commit_id, $commit->url);
                        if ($taiga->closeRef($issue, $message)) {
                            echo $commit_id . ": Item $issue is closed.\n";
                        } else {
                            echo $commit_id . ": Item $issue not found.\n";
                        }
                    }
                }
            } else {
                echo $commit_id . ": No issues found in commit messsage.\n";
            }
        }
    } else {
        echo $commit_id . ": No commit to master.\n";
    }
} else {
    echo "Missing project or post data.\n";
}
