<?php

// get input
$project = isset($_GET['project']) ? $_GET['project'] : false;
$action = isset($_GET['redirect']) ? "redirect" : "push";
$post_data = file_get_contents("php://input");
$push_info = json_decode($post_data);

// patterns and default texts
$close_pattern = "((?:[Cc]los(?:e[sd]?|ing)|[Ff]ix(?:e[sd]|ing)?) +(?:(?:issues? +)?#\d+(?:(?:, *| +and +)?))+)";
$issue_pattern = "/#([0-9]+)/";
$comment_text = "Closed by commit [%s](%s \"Open commit in GitLab\").";

// include config and taiga class
require "config.php";
require "inc/Taiga.php";

// init new taiga instance
$taiga = new Blanko\Taiga(TAIGA_URL, $project);
$taiga->loginUser(TAIGA_USERNAME, TAIGA_PASSWORD);
$output = "";

if ($project) {
    if ($action == "redirect") {
        $issue = isset($_GET['issue']) ? $_GET['issue'] : false;

        // prepare base project url
        $project_url = TAIGA_URL . "project/" . $project . "/";

        // user has provided an issue
        if ($issue == "new") {
            $url = $project_url . "issues";

            header("HTTP/1.1 301 Moved Permanently");
            header("Location: " . $url);
        } elseif ($issue) {
            // try to find item by reference (and convert issue to int)
            $item = $taiga->getItemByRef((int)$issue);

            if ($item) {
                // when item is found redirect to item
                $url = $project_url . $item['type'] . "/" . $item['ref'];

                header("HTTP/1.1 301 Moved Permanently");
                header("Location: " . $url);
            } else {
                // display error message
                $output .= sprintf(
                    "Item #%s not found in project %s.<br>",
                    htmlspecialchars($issue),
                    htmlspecialchars($project)
                );

                if (!empty($_SERVER['HTTP_REFERER'])) {
                    $output .= sprintf("<br><a href=\"%s\">« Back</a>", htmlspecialchars($_SERVER['HTTP_REFERER']));
                }
            }

        } else {
            // just redirect to project when no issue is provided
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: " . $project_url);
        }
    } else {
        // handle input
        if ($post_data && isset($push_info->ref) && $push_info->ref) {
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
                                    $output .= $commit_id . ": Item $issue is closed.<br>";
                                } else {
                                    $output .= $commit_id . ": Item $issue not found.<br>";
                                }
                            }
                        }
                    } else {
                        $output .= $commit_id . ": No issues found in commit messsage.<br>";
                    }
                }
            } else {
                $output = $commit_id . ": No commit to master.<br>";
            }
        } else {
            $output = "Wrong post data.<br>";
        }
    }
} else {
    $output = "Missing project parameter.<br>";
}

// display message when output is set
if ($output) {
    include "inc/header.html";
    echo $output;
    include "inc/footer.html";
}
