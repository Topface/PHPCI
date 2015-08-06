<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Command;

use b8\Config;
use Monolog\Logger;
use PHPCI\Helper\Lang;
use PHPCI\Logging\BuildDBLogHandler;
use PHPCI\Logging\LoggedBuildContextTidier;
use PHPCI\Logging\OutputLogHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use b8\Store\Factory;
use PHPCI\Builder;
use PHPCI\BuildFactory;
use PHPCI\Model\Build;

/**
* Run console command - Check runned builds
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Console
*/
class CheckCommand extends Command
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var int
     */
    protected $maxBuilds = 100;

    /**
     * @var bool
     */
    protected $isFromDaemon = false;

    /**
     * @param \Monolog\Logger $logger
     * @param string $name
     */
    public function __construct(Logger $logger, $name = null)
    {
        parent::__construct($name);
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setName('phpci:check-builds')
            ->setDescription('Check builds');
    }

    /**
     * Pulls all pending builds from the database and runs them.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        // For verbose mode we want to output all informational and above
        // messages to the symphony output interface.
        if ($input->hasOption('verbose') && $input->getOption('verbose')) {
            $this->logger->pushHandler(
                new OutputLogHandler($this->output, Logger::INFO)
            );
        }

        $running = $this->validateRunningBuilds();


        $builds = 0;


        return $builds;
    }

    public function setMaxBuilds($numBuilds)
    {
        $this->maxBuilds = (int)$numBuilds;
    }

    public function setDaemon($fromDaemon)
    {
        $this->isFromDaemon = (bool)$fromDaemon;
    }

    protected function validateRunningBuilds()
    {
        /** @var \PHPCI\Store\BuildStore $store */
        $store = Factory::getStore('Build');
        $running = $store->getByStatus(1);
        $rtn = array();

        $timeout = Config::getInstance()->get('phpci.build.failed_after', 1800);

//        var_dump($running);
        foreach ($running['items'] as $build) {
            /** @var \PHPCI\Model\Build $build */
            $build = BuildFactory::getBuild($build);

            $now = time();
            $start = $build->getStarted()->getTimestamp();

            if (($now - $start) > $timeout) {
                $this->logger->addInfo(Lang::get('marked_as_failed', $build->getId()));
                $build->setStatus(Build::STATUS_FAILED);
                $build->setFinished(new \DateTime());
                $store->save($build);
                $build->removeBuildDirectory();
                continue;
            } else {
                $this->logger->addInfo('Build is OK: '. $build->getId() . " project id: ".$build->getProjectId());
                var_dump('Build is OK: '. $build->getId() . " project id: ".$build->getProjectId());
            }

            $rtn[$build->getProjectId()] = true;
        }

//        var_dump($rtn);
        return $rtn;
    }
}
