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
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use b8\Store\Factory;
use PHPCI\Builder;
use PHPCI\BuildFactory;
use PHPCI\Model\Build;
use PHPCI\Plugin\SlackNotify;

/**
* Run console command - Runs any pending builds.
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Console
*/
class SlackCommand extends Command
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
    protected $maxBuilds = null;

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
            ->setName('phpci:slack')
            ->setDescription('slack test');
    }

    /**
     * Pulls all pending builds from the database and runs them.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;





            $build = BuildFactory::getBuildById(4858);
            $builder = new Builder($build, $this->logger);



            $plugin = new SlackNotify($builder,$build, array(
                "webhook_url" => "https://hooks.slack.com/services/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",
            ));

        $plugin->execute();

//            // Skip build (for now) if there's already a build running in that project:
//            if (!$this->isFromDaemon && in_array($build->getProjectId(), $running)) {
//                $this->logger->addInfo(Lang::get('skipping_build', $build->getId()));
//                $result['items'][] = $build;
//
//                // Re-run build validator:
//                $running = $this->validateRunningBuilds();
//                continue;
//            }
//
//            $builds++;
//
//            try {
//                // Logging relevant to this build should be stored
//                // against the build itself.
//                $buildDbLog = new BuildDBLogHandler($build, Logger::INFO);
//                $this->logger->pushHandler($buildDbLog);
//
//                $builder = new Builder($build, $this->logger);
//                $builder->execute();
//
//                // After execution we no longer want to record the information
//                // back to this specific build so the handler should be removed.
//                $this->logger->popHandler($buildDbLog);
//            } catch (\Exception $ex) {
//                $build->setStatus(Build::STATUS_FAILED);
//                $build->setFinished(new \DateTime());
//                $build->setLog($build->getLog() . PHP_EOL . PHP_EOL . $ex->getMessage());
//                $store->save($build);
//            }
//
//
//
//        $this->logger->addInfo(Lang::get('finished_processing_builds'));
//
//        return $builds;
    }

    public function setMaxBuilds($numBuilds)
    {
        $this->maxBuilds = (int)$numBuilds;
    }

    public function setIsDaemon($fromDaemon)
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
                $this->removeBuildDirectory($build);
                continue;
            }

            $rtn[$build->getProjectId()] = true;
        }

        return $rtn;
    }

    protected function removeBuildDirectory($build)
    {
        $buildPath = PHPCI_DIR . 'PHPCI/build/' . $build->getId() . '/';

        if (is_dir($buildPath)) {
            $cmd = 'rm -Rf "%s"';

            if (IS_WIN) {
                $cmd = 'rmdir /S /Q "%s"';
            }

            shell_exec($cmd);
        }
    }
}
