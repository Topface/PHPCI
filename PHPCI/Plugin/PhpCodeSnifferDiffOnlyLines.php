<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI;
use PHPCI\Builder;
use PHPCI\Model\Build;

/**
* PHP Code Sniffer Plugin - Allows PHP Code Sniffer testing.
* @author       Dan Cryer <dan@block8.co.uk>
* @author       Stan Gumeniuk <s.gumeniuk@topface.com>
* @package      PHPCI
* @subpackage   Plugins
*/
class PhpCodeSnifferDiffOnlyLines implements PHPCI\Plugin, PHPCI\ZeroConfigPlugin
{
    /**
     * @var \PHPCI\Builder
     */
    protected $phpci;

    /**
     * @var array
     */
    protected $suffixes;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $standard;

    /**
     * @var string
     */
    protected $tab_width;

    /**
     * @var string
     */
    protected $encoding;

    /**
     * @var int
     */
    protected $allowed_errors;

    /**
     * @var int
     */
    protected $allowed_warnings;

    /**
     * @var string, based on the assumption the root may not hold the code to be
     * tested, exteds the base path
     */
    protected $path;

    /**
     * @var array - paths to ignore
     */
    protected $ignore;

    public static function canExecute($stage, Builder $builder, Build $build)
    {
        if ($stage == 'test') {
            return true;
        }

        return false;
    }

    /**
     * @param \PHPCI\Builder $phpci
     * @param \PHPCI\Model\Build $build
     * @param array $options
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $this->phpci = $phpci;
        $this->build = $build;
        $this->suffixes = array('php');
        $this->directory = $phpci->buildPath;
        $this->standard = 'PSR2';
        $this->tab_width = '';
        $this->encoding = '';
        $this->path = '';
        $this->ignore = $this->phpci->ignore;
        $this->allowed_warnings = 0;
        $this->allowed_errors = 0;

        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowed_warnings = -1;
            $this->allowed_errors = -1;
        }

        if (isset($options['suffixes'])) {
            $this->suffixes = (array)$options['suffixes'];
        }

        if (!empty($options['tab_width'])) {
            $this->tab_width = ' --tab-width='.$options['tab_width'];
        }

        if (!empty($options['encoding'])) {
            $this->encoding = ' --encoding=' . $options['encoding'];
        }

        $this->setOptions($options);
    }

    protected function setOptions($options)
    {
        foreach (array('directory', 'standard', 'path', 'ignore', 'allowed_warnings', 'allowed_errors') as $key) {
            if (array_key_exists($key, $options)) {
                $this->{$key} = $options[$key];
            }
        }
    }

    public function execute()
    {
        list($ignore, $standard, $suffixes) = $this->getFlags();

        $phpcs = $this->phpci->findBinary('phpcs');

        if (!$phpcs) {
            $this->phpci->logFailure('Could not find phpcs.');
            return false;
        }

        $this->phpci->logExecOutput(false);

        $cmd = 'cd '.$this->phpci->buildPath.' && git diff `git merge-base origin/master '.$this->build->getCommitId().'`  --name-only --diff-filter=ACMRT -- "*.php" | xargs -P8 -r  -- '.$phpcs .' --encoding=utf-8 --report=json %s %s %s %s %s';
        $this->phpci->executeCommand(
            $cmd,
            $standard,
            $suffixes,
            $ignore,
            $this->tab_width,
            $this->encoding
        );

        $output = $this->phpci->getLastOutput();

        if ($output) {



            $cmd = 'cd '.$this->phpci->buildPath.' && git diff `git merge-base origin/master '.$this->build->getCommitId().'`   --diff-filter=ACMRT -- "*.php" | ./../../../showlines.awk show_path=1 show_hunk=0 show_header=0';

            $this->phpci->executeCommand($cmd);

            $output2 = $this->phpci->getLastOutput();

//            $this->phpci->log(print_r($output2,true));

            $diffLines = $this->parseLineDiffOutput($output2);
            list($errors, $warnings, $data) = $this->processReport($output,$diffLines);
//            list($errors, $warnings, $data) = $this->processReport($output);

            $this->phpci->logExecOutput(true);

            $success = true;
            $this->build->storeMeta('phpcsdifflines-warnings', $warnings);
            $this->build->storeMeta('phpcsdifflines-errors', $errors);
            $this->build->storeMeta('phpcsdifflines-data', $data);

            if ($this->allowed_warnings != -1 && $warnings > $this->allowed_warnings) {
                $this->phpci->log("Allow warn:".$this->allowed_warnings);
                $success = false;
            }

            if ($this->allowed_errors != -1 && $errors > $this->allowed_errors) {
                $this->phpci->log("Allow allowed_errors:".$this->allowed_errors);
                $this->phpci->log("errors:".$errors);
                $success = false;
            }
        } else {
            $this->phpci->log('Nothing to check');
            $success = true;
        }

        return $success;
    }

    protected function getFlags()
    {
        $ignore = '';
        if (count($this->ignore)) {
            $ignore = ' --ignore=' . implode(',', $this->ignore);
        }

        if (strpos($this->standard, '/') !== false) {
            $standard = ' --standard='.$this->directory.$this->standard;
        } else {
            $standard = ' --standard='.$this->standard;
        }

        $suffixes = '';
        if (count($this->suffixes)) {
            $suffixes = ' --extensions=' . implode(',', $this->suffixes);
        }

        return array($ignore, $standard, $suffixes);
    }


    protected function processReport($output,$outputLines)
    {
        $data = json_decode(trim($output), true);

        if (!is_array($data)) {
            throw new \Exception('Could not process PHPCS report JSON.');
        }

        $errors = 0;
        $warnings = 0;

        $rtn = array();

        foreach ($data['files'] as $fileName => $file) {
            $fileName = str_replace($this->phpci->buildPath, '', $fileName);

            foreach ($file['messages'] as $message) {

                if (isset($outputLines[$fileName][$message['line']])) {
                    $rtn[] = array(
                        'file'    => $fileName,
                        'line'    => $message['line'],
                        'type'    => $message['type'],
                        'message' => $message['message'],
                    );
                    if ($message['type'] == 'WARNING') {
                        $warnings++;
                    } elseif ($message['type'] == 'ERROR') {
                        $errors++;
                    }
                }
            }
        }

        return array($errors, $warnings, $rtn);

    }

    protected function parseLineDiffOutput($outputLines)
    {
        $arr = explode("\n", $outputLines);

        $arr2 = [];
        foreach ($arr as $l)
        {
            $pattern = '/(.+?):(\d+?):(.*?)/i';

            preg_match($pattern, $l, $matches);
            if (count($matches)>0){
                $arr2[$matches[1]][$matches[2]] = 1;
            }
        }

        return $arr2;


    }

}
