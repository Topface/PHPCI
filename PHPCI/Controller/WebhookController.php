<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Controller;

use b8;
use b8\Store;
use PHPCI\BuildFactory;
use PHPCI\Model\Build;
use PHPCI\Service\BuildService;

/**
 * Webhook Controller - Processes webhook pings from BitBucket, Github, Gitlab, etc.
 * @author       Dan Cryer <dan@block8.co.uk>
 * @author       Sami Tikka <stikka@iki.fi>
 * @author       Alex Russell <alex@clevercherry.com>
 * @package      PHPCI
 * @subpackage   Web
 */
class WebhookController extends \PHPCI\Controller
{
    /**
     * @var \PHPCI\Store\BuildStore
     */
    protected $buildStore;

    /**
     * @var \PHPCI\Store\ProjectStore
     */
    protected $projectStore;

    /**
     * @var \PHPCI\Service\BuildService
     */
    protected $buildService;

    public function init()
    {
        $this->buildStore = Store\Factory::getStore('Build');
        $this->projectStore = Store\Factory::getStore('Project');
        $this->buildService = new BuildService($this->buildStore);
    }

    /**
     * Called by Bitbucket POST service.
     */
    public function bitbucket($project)
    {
        $payload = json_decode($this->getParam('payload'), true);

        foreach ($payload['commits'] as $commit) {
            try {

                $email = $commit['raw_author'];
                $email = substr($email, 0, strpos($email, '>'));
                $email = substr($email, strpos($email, '<') + 1);

                $this->createBuild($project, $commit['raw_node'], $commit['branch'], $email, $commit['message']);
            } catch (\Exception $ex) {
                header('HTTP/1.1 500 Internal Server Error');
                header('Ex: ' . $ex->getMessage());
                die('FAIL');
            }
        }

        die('OK');
    }

    /**
     * Called by POSTing to /git/webhook/<project_id>?branch=<branch>&commit=<commit>
     *
     * @param string $project
     */
    public function git($project)
    {
        $branch = $this->getParam('branch');
        $commit = $this->getParam('commit');

        try {
            if (empty($branch)) {
                $branch = 'master';
            }

            if (empty($commit)) {
                $commit = null;
            }

            $this->createBuild($project, $commit, $branch, null, null);

        } catch (\Exception $ex) {
            header('HTTP/1.1 400 Bad Request');
            header('Ex: ' . $ex->getMessage());
            die('FAIL');
        }

        die('OK');
    }

    /**
     * Called by Github Webhooks:
     */
    public function github($project)
    {
        $payload = json_decode($this->getParam('payload'), true);

        // Handle Pull Request web hooks:
        if (array_key_exists('pull_request', $payload)) {
            return $this->githubPullRequest($project, $payload);
        }

        // Handle Push web hooks:
        if (array_key_exists('commits', $payload)) {
            return $this->githubCommitRequest($project, $payload);
        }

        header('HTTP/1.1 200 OK');
        die('This request type is not supported, this is not an error.');
    }

    protected function githubCommitRequest($project, array $payload)
    {
        // Github sends a payload when you close a pull request with a
        // non-existant commit. We don't want this.
        if (array_key_exists('after', $payload) && $payload['after'] === '0000000000000000000000000000000000000000') {
            die('OK');
        }

        try {

            if (isset($payload['commits']) && is_array($payload['commits'])) {
                // If we have a list of commits, then add them all as builds to be tested:

                foreach ($payload['commits'] as $commit) {
                    if (!$commit['distinct']) {
                        continue;
                    }

                    $branch = str_replace('refs/heads/', '', $payload['ref']);
                    $committer = $commit['committer']['email'];
                    $this->createBuild($project, $commit['id'], $branch, $committer, $commit['message']);
                }
            } elseif (substr($payload['ref'], 0, 10) == 'refs/tags/') {
                // If we don't, but we're dealing with a tag, add that instead:
                $branch = str_replace('refs/tags/', 'Tag: ', $payload['ref']);
                $committer = $payload['pusher']['email'];
                $message = $payload['head_commit']['message'];
                $this->createBuild($project, $payload['after'], $branch, $committer, $message);
            }

        } catch (\Exception $ex) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Ex: ' . $ex->getMessage());
            die('FAIL');
        }

        die('OK');
    }

    protected function githubPullRequest($projectId, array $payload)
    {
        // We only want to know about open pull requests:
        if (!in_array($payload['action'], array('opened', 'synchronize', 'reopened'))) {
            die('OK');
        }

        try {
            $headers = array();
            $token = \b8\Config::getInstance()->get('phpci.github.token');

            if (!empty($token)) {
                $headers[] = 'Authorization: token ' . $token;
            }

            $url    = $payload['pull_request']['commits_url'];
            $http   = new \b8\HttpClient();
            $http->setHeaders($headers);
            $response = $http->get($url);

            // Check we got a success response:
            if (!$response['success']) {
                header('HTTP/1.1 500 Internal Server Error');
                header('Ex: Could not get commits, failed API request.');
                die('FAIL');
            }

            foreach ($response['body'] as $commit) {
                $branch = str_replace('refs/heads/', '', $payload['pull_request']['base']['ref']);
                $committer = $commit['commit']['author']['email'];
                $message = $commit['commit']['message'];

                $extra = array(
                    'build_type' => 'pull_request',
                    'pull_request_id' => $payload['pull_request']['id'],
                    'pull_request_number' => $payload['number'],
                    'remote_branch' => $payload['pull_request']['head']['ref'],
                    'remote_url' => $payload['pull_request']['head']['repo']['clone_url'],
                );

                $this->createBuild($projectId, $commit['sha'], $branch, $committer, $message, $extra);
            }
        } catch (\Exception $ex) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Ex: ' . $ex->getMessage());
            die('FAIL');
        }

        die('OK');
    }

    /**
     * Called by Gitlab Webhooks:
     */
    public function gitlab($project)
    {
        $payloadString = file_get_contents("php://input");
        $payload = json_decode($payloadString, true);

        try {

            if (isset($payload['commits']) && is_array($payload['commits'])) {
                // If we have a list of commits, then add them all as builds to be tested:

                foreach ($payload['commits'] as $commit) {
                    $branch = str_replace('refs/heads/', '', $payload['ref']);
                    $committer = $commit['author']['email'];
                    $this->createBuild($project, $commit['id'], $branch, $committer, $commit['message']);
                }
            }

        } catch (\Exception $ex) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Ex: ' . $ex->getMessage());
            die('FAIL');
        }

        die('OK');
    }


    /**
     * Called by Gitlab Webhooks Merge Request:
     */
    public function gitlabpr($project)
    {
        $payloadString = file_get_contents("php://input");
        $payload = json_decode($payloadString, true);

        try {

            if (isset($payload['object_kind']) && $payload['object_kind']=='merge_request') {
                // If we have merge_request;

                $objectAttributes = $payload['object_attributes'];

                $this->createBuild($project, null, $objectAttributes['source_branch'], null, $objectAttributes['title']);


            }

        } catch (\Exception $ex) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Ex: ' . $ex->getMessage());
            die('FAIL');
        }

        die('OK');
    }


    /**
     * Called by Gitlab Webhooks Merge Request:
     */
    public function gitlabprimg($project)
    {


        $payload = [
            'requestId' => $this->getParam('requestId'),
            'target_branch' => $this->getParam('target_branch'),
            'source_branch' => $this->getParam('source_branch'),
            'title' => $this->getParam('title'),
            'created_at' => $this->getParam('created_at'),
            'updated_at' => $this->getParam('updated_at'),
        ];

        $extra = md5(json_encode($payload));

        try {


                $objectAttributes = $payload;

                $f = $this->checkBuild(
                     $project,
                         null,
                         $objectAttributes['source_branch'],
                         null,
                         $objectAttributes['title'],
                         $extra);

            switch ($f) {
                case "0":
                    $status= "pending";
                    break;
                case "1":
                    $status= "failed";
                    break;
                case "2":
                    $status= "passing";
                    break;
                case "3":
                    $status= "failed";
                    break;
            }
            header('Content-Type: image/svg+xml');
            die(file_get_contents(APPLICATION_PATH . 'public/assets/img/build-' . $status . '.svg'));


        } catch (\Exception $ex) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Ex: ' . $ex->getMessage());
            die('FAIL');
        }

        die('OK');
    }

    protected function checkBuild($projectId, $commitId, $branch, $committer, $commitMessage, $extra = null){
        $builds = $this->buildStore->getWhere(['extra' => '"'.$extra.'"']);
//        $builds = $this->buildStore->getWhere(['id' => 174]);
//        var_dump($builds);
        if ($builds['count']) {
            /** @var \PHPCI\Model\Build $cBuild */
            $cBuild = $builds['items'][0];
            return $cBuild->getStatus();
        }

            $this->createBuild($projectId, $commitId, $branch, $committer, $commitMessage, $extra);
            var_dump('build!!');
            return $this->checkBuild($projectId, $commitId, $branch, $committer, $commitMessage, $extra);


    }

    protected function createBuild($projectId, $commitId, $branch, $committer, $commitMessage, $extra = null)
    {
        // Check if a build already exists for this commit ID:
        $builds = $this->buildStore->getByProjectAndCommit($projectId, $commitId);

        if ($builds['count']) {
            return true;
        }

        $project = $this->projectStore->getById($projectId);

        if (empty($project)) {
            throw new \Exception('Project does not exist:' . $projectId);
        }

        // If not, create a new build job for it:
        $build = $this->buildService->createBuild($project, $commitId, $branch, $committer, $commitMessage, $extra);
        $build = BuildFactory::getBuild($build);

        // Send a status postback if the build type provides one:
        $build->sendStatusPostback();

        return true;
    }
}
