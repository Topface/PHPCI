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
* PHP check for AclCheckServicePermissionsScript.
* @author       Stan Gumeniuk <i@vigo.su>
* @package      PHPCI
* @subpackage   Plugins
*/
class Acsp implements PHPCI\Plugin, PHPCI\ZeroConfigPlugin
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

    /**
     * Check if this plugin can be executed.
     * @param $stage
     * @param Builder $builder
     * @param Build $build
     * @return bool
     */
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
        $this->directory = $phpci->buildPath;

        $this->allowed_warnings = 0;
        $this->allowed_errors = 0;

        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowed_warnings = -1;
            $this->allowed_errors = -1;
        }


        $this->setOptions($options);
    }

    /**
     * Handle this plugin's options.
     * @param $options
     */
    protected function setOptions($options)
    {
        foreach (array('directory', 'allowed_warnings', 'allowed_errors') as $key) {
            if (array_key_exists($key, $options)) {
                $this->{$key} = $options[$key];
            }
        }
    }

    /**
    * Runs PHP Code Sniffer in a specified directory, to a specified standard.
    */
    public function execute()
    {
        $this->phpci->logExecOutput(false);

        $cmd = "export IS_IN_DOCKER=1 &&  export IS_CI=1 && export SHELL_MODE_SCRIPT=phpunit && cd " .$this->directory . " && ./bin/script Topface.Admin.Acl.Script.AclCheckServicePermissionsScript --json" ;
        $this->phpci->executeCommand(
            $cmd
        );

        $output = $this->phpci->getLastOutput();
        list($result, $permissions, $rtn) = $this->processReport($output);

        $this->phpci->logExecOutput(true);

        $success = $result;
        $this->build->storeMeta('acsp-data', $rtn);



        if ($this->allowed_errors != -1 && count($rtn) > $this->allowed_errors) {
            $success = false;
        }

        return $success;
    }



    /**
     * Process the PHPCS output report.
     * @param $output
     * @return array
     * @throws \Exception
     */
    protected function processReport($output)
    {
        $data = json_decode(trim($output), true);

        $rtn = array();
        $permissions = array();
        if (!is_array($data)) {
            $this->phpci->log($output);
            //throw new \Exception(PHPCI\Helper\Lang::get('could_not_process_report'));
            $result = true;
        }else {
            $result = $data['result'];
            $permissions = $data['permissions'];





            foreach($permissions as $p){
                $rtn[] = ['name' => $p];
            }
        }



        return array($result, $permissions, $rtn);
    }
}
