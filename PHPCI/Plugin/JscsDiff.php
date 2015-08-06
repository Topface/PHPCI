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
use Exception;

/**
* PHP Code Sniffer Plugin - Allows PHP Code Sniffer testing.
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Plugins
*/
class JscsDiff implements PHPCI\Plugin, PHPCI\ZeroConfigPlugin
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

//        $dir = "phpcigit-dev2";
        $dir = "phpcigit3";
        $this->phpci->logExecOutput(false);

        //$cmd = "export IS_IN_DOCKER=1 &&  export IS_CI=1 && export SHELL_MODE_SCRIPT=phpunit && cd " .$this->directory . " && ./bin/script Topface.Admin.Acl.Script.AclCheckServicePermissionsScript --json" ;
//        $cmd = "export IS_IN_DOCKER=1 &&  export IS_CI=1 && export SHELL_MODE_SCRIPT=phpunit  &&  find -type f -name \"*SectionWidget.js\" | xargs sudo docker run --rm -v /home/docker-client/phpcigit-dev2/".$this->build->getId().":/var/www/topface/master docker.core.tf/topface-jscs ./static/default/js/Chatterbox.js" ;

//        $cmd = 'cd '.$this->phpci->buildPath.' && git diff `git merge-base origin/master '.$this->build->getCommitId().'`  --name-only --diff-filter=ACMRT -- "*.php" | xargs -P8 -r  -- '.$phpcs .' --encoding=utf-8 --report=json %s %s %s %s %s';

//        $cmd = "export IS_IN_DOCKER=1 &&  export IS_CI=1 && export SHELL_MODE_SCRIPT=phpunit  &&  find ./static/default/js/TF/ -type f -name \"*.js\"  -not -name \"*jquery*\" -not -name \"*highcharts.js\" -not -name \"*Jquery*\" -not -name \"*JQuery*\" -not -name \"*.min.js\" | xargs sudo docker run --rm -v /home/docker-client/".$dir."/".$this->build->getId().":/var/www/topface/master docker.core.tf/topface-jscs " ;
        $cmd = "cd ".$this->phpci->buildPath." && export IS_IN_DOCKER=1 &&  export IS_CI=1 && export SHELL_MODE_SCRIPT=phpunit  &&  git diff `git merge-base origin/master ".$this->build->getCommitId()."`  --name-only --diff-filter=ACMRT -- \"*.js\" | grep -v Jquery | grep -v JQuery | grep -v min.js | grep -v jquery | grep -v highcharts | grep -v \"vendor/\" | xargs  sudo docker run --rm -v /home/docker-client/".$dir."/".$this->build->getId().":/var/www/topface/master docker.core.tf/topface-jscs " ;
        $this->phpci->log($cmd);
        $this->phpci->executeCommand(
            $cmd
        );

        $output = $this->phpci->getLastOutput();
        list($result,  $rtn) = $this->processReport($output);

        $this->phpci->logExecOutput(true);

        $success = $result;
        $this->build->storeMeta('jscsdiff-data', $rtn);



//        if ($this->allowed_errors != -1 && count($rtn) > $this->allowed_errors) {
//            $success = false;
//        }

        return $success;
    }



    protected function processReport($output)
    {
        ini_set('memory_limit', '512M');
        $xml = simplexml_load_string($output);

        $rtn = array();
        $status = true;

        if ($xml) {
            $this->phpci->log('Jscsdiff Log:');
            $this->phpci->log($output);


            $json = json_encode($xml);
            $array = json_decode($json,TRUE);
//            print_r($array);




            if (isset($array['file'][0])){
                foreach ($array['file'] as $file){
                    $fileName = $file['@attributes']['name'];
                    //echo $fileName. PHP_EOL;
                    if (isset($file['error'])){
                        $errors = $file['error'];
                        //var_dump($file['@attributes']['error']);
                        if (count($errors)){
                            if (isset($errors[0])){
                                foreach ($errors as $er){
                                    $rtn[] = [
                                        'name' =>$fileName,
                                        'line' => $er['@attributes']['line'],
                                        'msg' => $er['@attributes']['message'],
//                                        'msg' => 'line :' . $er['@attributes']['line'] .
//                                        'column :'. $er['@attributes']['column'] .
//                                        'severity :'. $er['@attributes']['severity'] .
//                                        'message :'. $er['@attributes']['message']
                                    ];
                                }
                            } else {
                                $rtn[] = [
                                    'name' =>$fileName,
                                    'line' =>$errors['@attributes']['line'],
                                    'msg' =>  $errors['@attributes']['message']
                                    ];
                            }


                        }

                    }


                }

            } elseif (isset($array['file']['@attributes'])){

                $fileName = $array['file']['@attributes']['name'];
                if (isset($array['file']['error'])){
                    $errors = $array['file']['error'];

                    if (count($errors))foreach ($errors as $er){
                        $rtn[] = [
                            'name' =>$fileName,
                            'line' =>isset($er['@attributes']['line'])?$er['@attributes']['line']:0,
                            'msg' =>  isset($er['@attributes']['message'])?$er['@attributes']['message']:'unknown'
                            ];
                    }
                }


            }




//            $fileName = $array['file']['@attributes']['name'];
//            $errors = $array['file']['error'];
//
//            foreach ($errors as $er){
//                $rtn[] = $fileName.  'line :' . $er['@attributes']['line'] .
//                    'column :'. $er['@attributes']['column'] .
//                    'severity :'. $er['@attributes']['severity'] .
//                    'message :'. $er['@attributes']['message'] .
//                    PHP_EOL;
//            }
        }

        return array($status, $rtn);

    }

    /**
     * Process the PHPCS output report.
     * @param $output
     * @return array
     * @throws \Exception
     */
    protected function processReport2($output)
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
