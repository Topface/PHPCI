<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Model\Build;

use PHPCI\Model\Build;
use PHPCI\Builder;

/**
* Remote Git Build Model
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Core
*/
class RemoteGitBuild extends Build
{
    /**
    * Get the URL to be used to clone this remote repository.
    */
    protected function getCloneUrl()
    {
        return $this->getProject()->getReference();
    }

    /**
    * Create a working copy by cloning, copying, or similar.
    */
    public function createWorkingCopy(Builder $builder, $buildPath)
    {
        $key = trim($this->getProject()->getSshPrivateKey());

        //if (!empty($key)) {
        //    $success = $this->cloneBySsh($builder, $buildPath);
        //} else {
        //    $success = $this->cloneByHttp($builder, $buildPath);
        //}

        $s = $this->checkProjectLocalRepo($builder, $buildPath);
        $buildDir = $this->getProjectLocalRepoDir($builder, $buildPath);


        $success = true;

        if (!$s){
            $builder->log('Start clone project.');
            if (!empty($key)) {
                $success = $this->cloneBySsh($builder, $buildDir);
            } else {
                $success = $this->cloneByHttp($builder, $buildDir);
            }
            if (!$success) {
                $builder->logFailure('Failed to clone remote git repository.');
//                return false;
                $builder->log('Smthg wrong.');
            }

            $builder->log('Succefully cloned.');
        }

        $s &= $this->pullGit($builder, $buildDir);

        $s &= $this->copyLocaleRepoToBuild($builder, $buildDir,$buildPath);

        $s &=$this->postCopyLocaleRepoToBuild($builder,$buildPath);
        $s &= $this->postCloneSetup($builder,$buildPath);

        if (!$s){
            return false;
        }


        return $this->handleConfig($builder, $buildPath);
    }

    public function initLocaleGitRepo(Builder $builder,$buildPath)
    {


        $s = $this->checkProjectLocalRepo($builder, $buildPath);
        $buildDir = $this->getProjectLocalRepoDir($builder, $buildPath);

        if (!file_exists($buildDir)){
            mkdir($buildDir, 0777, true);
        }


        $key = trim($this->getProject()->getSshPrivateKey());



        if (!$s){
            $builder->log('Start clone project.');
            var_dump( $builder->getBuildProjectTitle() . 'Start  clone.');
            if (!empty($key)) {
                $success = $this->cloneBySsh($builder, $buildDir);
            } else {
                $success = $this->cloneByHttp($builder, $buildDir);
            }
            if (!$success) {
                $builder->logFailure('Failed to clone remote git repository.');
//                return false;
                $builder->log('Smthg wrong.');
                var_dump( $builder->getBuildProjectTitle() . 'Smthg wrong.' );
            }

            $builder->log('Succefully cloned.');
            var_dump( $builder->getBuildProjectTitle() . 'Project  cloned.');
        } else {
            $builder->log('Project already cloned.');
            var_dump( $builder->getBuildProjectTitle() . 'Project already cloned.');
        }

        var_dump($buildDir);
        $s = $this->pullGit($builder, $buildDir);

    }

    /**
    * Use an HTTP-based git clone.
    */
    protected function cloneByHttp(Builder $builder, $cloneTo)
    {
        $cmd = 'git clone --recursive ';

        $depth = $builder->getConfig('clone_depth');

        if (!is_null($depth)) {
            $cmd .= ' --depth ' . intval($depth) . ' ';
        }

        $cmd .= ' -b %s %s "%s"';
        $success = $builder->executeCommand($cmd, $this->getBranch(), $this->getCloneUrl(), $cloneTo);

        if ($success) {
            $success = $this->postCloneSetup($builder, $cloneTo);
        }

        return $success;
    }


    protected function postCopyLocaleRepoToBuild(Builder $builder, $to)
    {
        $success = true;
        $commit = $this->getCommitId();

        $keyFile = $this->writeSshKey($to);
        $gitSshWrapper = $this->writeSshWrapper($to, $keyFile);


        $builder->log('Start fetch ');


        $cmd = 'export GIT_SSH="'.$gitSshWrapper.'" && ';
        $cmd .= ' cd "%s" ';

//        $cmd .= ' && git pull --all --quiet';
        $cmd .= ' && git fetch --all ';
        $success = $builder->executeCommand($cmd, $to);
//        $builder->log('Git pull Log is '.(int)$success);
        $builder->log('Git fetch Log is '.(int)$success);

        return $success;
    }

    protected function pullGit(Builder $builder,$from)
    {
        $success = true;

        $keyFile = $this->writeSshKey($from);

            $gitSshWrapper = $this->writeSshWrapper($from, $keyFile);

        $builder->log('pull origin start');

            $cmd = 'cd "%s"';

            $cmd = 'export GIT_SSH="'.$gitSshWrapper.'" && ' . $cmd;

            $cmd .= ' && git fetch --all && git reset --hard origin/master ';

            $success = $builder->executeCommand($cmd, $from);
            $builder->log('pull origin finish');
        return $success;
    }

    protected function copyLocaleRepoToBuild(Builder $builder,$from, $to)
    {
        if (!file_exists($to)) {
            mkdir($to);
        }

        $success = $builder->executeCommand("cp -r %s/. %s", $from, $to);
        if (!$success) {
            $builder->logFailure('Failed to clone remote git repository.');
        }

        $success = $builder->executeCommand("chmod -R 777 %s", $to);

        $builder->log('Copy from '. $from.' to '. $to .'success.');

        return $success;

    }

    public function checkProjectLocalRepo(Builder $builder, $cloneTo)
    {
        $buildDir = $this->getProjectLocalRepoDir($builder, $cloneTo);

        return file_exists($buildDir);
    }

    public function getProjectLocalRepoDir(Builder $builder, $cloneTo)
    {
        $buildDir = dirname($cloneTo);

        $buildDir .= '/git/'.$builder->getBuildProjectTitle();
        return $buildDir;
    }

    /**
    * Use an SSH-based git clone.
    */
    protected function cloneBySsh(Builder $builder, $cloneTo)
    {

        $keyFile = $this->writeSshKey($cloneTo);

        if (!IS_WIN) {
            $gitSshWrapper = $this->writeSshWrapper($cloneTo, $keyFile);
        }

        // Do the git clone:
        $cmd = 'git clone --recursive ';

        $depth = $builder->getConfig('clone_depth');

        if (!is_null($depth)) {
            $cmd .= ' --depth ' . intval($depth) . ' ';
        }

        $cmd .= ' -b %s %s "%s"';

        if (!IS_WIN) {
            $cmd = 'export GIT_SSH="'.$gitSshWrapper.'" && ' . $cmd;
        }

        $success = $builder->executeCommand($cmd, $this->getBranch(), $this->getCloneUrl(), $cloneTo);
        var_dump("1");
        var_dump($this->getBranch());
        var_dump($this->getCloneUrl());
//        die();
        if ($success) {
            $success = $this->postCloneSetup($builder, $cloneTo);
        }

        // Remove the key file and git wrapper:
        unlink($keyFile);
        if (!IS_WIN) {
            unlink($gitSshWrapper);
        }

        return $success;
    }

    /**
     * Handle any post-clone tasks, like switching branches.
     * @param Builder $builder
     * @param $cloneTo
     * @return bool
     */
    protected function postCloneSetup(Builder $builder, $cloneTo)
    {

        $keyFile = $this->writeSshKey($cloneTo);

        $success = true;
        $commit = $this->getCommitId();
        $builder->log('Commit is '. $commit);

        $chdir = IS_WIN ? 'cd /d "%s"' : 'cd "%s"';

        $builder->log('post');

        if (!empty($commit) && $commit != 'Manual') {

            $cmd = 'cd "%s"';

            if (IS_WIN) {
                $cmd = 'cd /d "%s"';
            }

            if (!IS_WIN) {
                $gitSshWrapper = $this->writeSshWrapper($cloneTo, $keyFile);
                $cmd = 'export GIT_SSH="' . $gitSshWrapper . '" && ' . $cmd;
            }

            $cmd .= ' && git reset --hard && git checkout %s --quiet --force';

            $success = $builder->executeCommand($cmd, $cloneTo, $this->getCommitId(), $this->getCommitId());
            $builder->log('Post clone setup(1): Log is' . $success . ' ' . $this->getCommitId());
            // Always update the commit hash with the actual HEAD hash
            if ($builder->executeCommand($chdir . ' && git rev-parse HEAD', $cloneTo)) {
                $this->setCommitId(trim($builder->getLastOutput()));
            }
        } else {
            $cmd = 'cd "%s"';

            if (IS_WIN) {
                $cmd = 'cd /d "%s"';
            }

            if (!IS_WIN) {
                $gitSshWrapper = $this->writeSshWrapper($cloneTo, $keyFile);
                $cmd = 'export GIT_SSH="' . $gitSshWrapper . '" && ' . $cmd;
            }

            $cmd .= ' && git reset --hard && git checkout %s --quiet --force';

            $success = $builder->executeCommand($cmd, $cloneTo, 'HEAD');
            $builder->log('Post clone setup(2): Log is' . $success . ' ' . 'HEAD');
            // Always update the commit hash with the actual HEAD hash
            if ($builder->executeCommand($chdir . ' && git rev-parse HEAD', $cloneTo)) {
                $this->setCommitId(trim($builder->getLastOutput()));
            }
        }

        return $success;
    }

    /**
     * Create an SSH key file on disk for this build.
     * @param $cloneTo
     * @return string
     */
    protected function writeSshKey($cloneTo)
    {
        $keyPath = dirname($cloneTo . '/temp');
        $keyFile = $keyPath . '.key';

        // Write the contents of this project's git key to the file:
        file_put_contents($keyFile, $this->getProject()->getSshPrivateKey());
        chmod($keyFile, 0600);

        // Return the filename:
        return $keyFile;
    }

    /**
     * Create an SSH wrapper script for Git to use, to disable host key checking, etc.
     * @param $cloneTo
     * @param $keyFile
     * @return string
     */
    protected function writeSshWrapper($cloneTo, $keyFile)
    {
        $path = dirname($cloneTo . '/temp');
        $wrapperFile = $path . '.sh';

        $sshFlags = '-o CheckHostIP=no -o IdentitiesOnly=yes -o StrictHostKeyChecking=no -o PasswordAuthentication=no';

        // Write out the wrapper script for this build:
        $script = <<<OUT
#!/bin/sh
ssh {$sshFlags} -o IdentityFile={$keyFile} $*

OUT;

        file_put_contents($wrapperFile, $script);
        shell_exec('chmod +x "'.$wrapperFile.'"');

        return $wrapperFile;
    }
}
