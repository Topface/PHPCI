<?php
/**
 * @author Stan Gumeniuk i@vigo.su
 * @author sgumeniuk@topface.com
 * Date: 28/05/15
 * Time: 21:18
 */

namespace PHPCI\Command;

use b8\Store\Factory;
use b8\HttpClient;
use Monolog\Logger;
use PHPCI\Helper\Lang;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use PHPCI\Model\Build;
use PHPCI\Model\Build\GithubBuild;
use PHPCI\Builder;

use PHPCI\Helper\BuildInterpolator;

class InitGitsCommand extends Command
{

    /**
     * @var \Monolog\Logger
     */
    protected $logger;

    public function __construct(Logger $logger, $name = null)
    {
        parent::__construct($name);
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setName('phpci:init-git')
//            ->addOption('project_id', null, InputOption::VALUE_OPTIONAL, 'project_id')
            ->setDescription('prepare git repos');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parser = new Parser();
        $yaml = file_get_contents(APPLICATION_PATH . 'PHPCI/config.yml');
        $this->settings = $parser->parse($yaml);

        $this->logger->addInfo(Lang::get('finding_projects'));
        $projectStore = Factory::getStore('Project');

            $result = $projectStore->getWhere();

        $this->logger->addInfo(Lang::get('found_n_projects', count($result['items'])));

        $interpolator = new BuildInterpolator();

        foreach ($result['items'] as $project) {

//            var_dump($project->type);

            if ($project->type!='github'){
                continue;
            }

//            var_dump($project);

            $build = new GithubBuild();

            $build->setProjectId($project->getId());
            $build->setId(-1);
            $build->setBranch($project->getBranch());




            $builder = new Builder($build, $this->logger);

            $buildPath = $build->getBuildPath() . '/';

            $s = $build->checkProjectLocalRepo($builder, $buildPath);
            $buildDir = $build->getProjectLocalRepoDir($builder, $buildPath);

            $build->initLocaleGitRepo($builder,$buildPath);

        }

        $this->logger->addInfo(Lang::get('finished_processing_builds'));
    }

}