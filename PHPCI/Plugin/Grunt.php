<?php
/**
* PHPCI - Continuous Integration for PHP
*
* @copyright    Copyright 2013, Block 8 Limited.
* @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
* @link         http://www.phptesting.org/
*/

namespace PHPCI\Plugin;

use PHPCI\Builder;
use PHPCI\Model\Build;

/**
* Grunt Plugin - Provides access to grunt functionality.
* @author       Tobias Tom <t.tom@succont.de>
* @package      PHPCI
* @subpackage   Plugins
*/
class Grunt implements \PHPCI\Plugin
{
    protected $directory;
    protected $task;
    protected $preferDist;
    protected $phpci;
    protected $grunt;
    protected $gruntfile;

    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $path = $phpci->buildPath;
        $this->phpci = $phpci;
        $this->directory = $path;
        $this->task = null;
        $this->grunt = $this->phpci->findBinary('grunt');
        $this->gruntfile = 'Gruntfile.js';

        // Handle options:
        if (isset($options['directory'])) {
            $this->directory = $path . '/' . $options['directory'];
        }

        if (isset($options['task'])) {
            $this->task = $options['task'];
        }

        if (isset($options['grunt'])) {
            $this->grunt = $options['grunt'];
        }

        if (isset($options['gruntfile'])) {
            $this->gruntfile = $options['gruntfile'];
        }
    }

    /**
    * Executes grunt and runs a specified command (e.g. install / update)
    */
    public function execute()
    {
        // if npm does not work, we cannot use grunt, so we return false
        if (!$this->phpci->executeCommand('cd %s && npm install', $this->directory)) {
            return false;
        }

        // build the grunt command
        $cmd = 'cd %s && ' . $this->grunt;
        $cmd .= ' --no-color';
        $cmd .= ' --gruntfile %s';
        $cmd .= ' %s'; // the task that will be executed

        // and execute it
        return $this->phpci->executeCommand($cmd, $this->directory, $this->gruntfile, $this->task);
    }
}
