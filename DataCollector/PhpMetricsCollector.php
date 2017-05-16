<?php

namespace MVPDoers\PhpMetricsCollectorBundle\DataCollector;

use Hal\Application\Analyze;
use Hal\Application\Config\Config;
use Hal\Application\Config\Validator;
use Hal\Component\Issue\Issuer;
use Hal\Component\Output\CliOutput;
use Hal\Metric\Consolidated;
use Hal\Report;
use Hal\Violation\ViolationParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class PhpMetricsCollector extends DataCollector
{
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $files = $this->getFilesToAnalyze();
        $metrics = $this->analyze($files);
        $consolidated = new Consolidated($metrics);

        $this->data = [
            'metrics' => $metrics,
            'consolidated' => $consolidated,
            'average'      => $consolidated->getAvg(),
            'sum'          => $consolidated->getSum(),
            'files'        => $files
        ];
    }

    private function analyze(array $files)
    {
        // formatter
        $output = new CliOutput();
        $output->setQuietMode(true);

        $issuer = (new Issuer($output));

        $config = new Config();
        $config->set('files', $files);

        (new Validator())->validate($config);

        // analyze
        $metrics = (new Analyze($config, $output, $issuer))->run($files);

        // violations
        (new ViolationParser($config, $output))->apply($metrics);

        return $metrics;
    }

    private function getFilesToAnalyze()
    {
        $files = get_included_files();

        return array_filter($files, function ($v) {
            $excludes = [
                'vendor',
                'pear',
                '\\.phar',
                'bootstrap\.php',
                'Test',
                '/app',
                'AppKernel.php',
                'autoload.php',
                'cache/',
                'app.php',
                'app_dev.php',
                'Form',
                'PhpMetrics',
                'classes.php'
            ];

            return !preg_match('!' . implode('|', $excludes) . '!', $v);
        });
    }

    public function getMetrics()
    {
        return $this->data['metrics'];
    }

    public function getSummary()
    {
        return $this->data['consolidated'];
    }

    public function getMaintainabilityIndex()
    {
        return $this->data['average']->mi;
    }

    public function getComplexity()
    {
        return $this->data['average']->ccn;
    }

    public function getCommentWeight()
    {
        return $this->data['average']->commentWeight;
    }

    public function getLoc()
    {
        return $this->data['sum']->loc;
    }

    public function getLogicalLoc()
    {
        return $this->data['sum']->lloc;
    }

    public function getCommentLoc()
    {
        return $this->data['sum']->cloc;
    }

    public function getBugs()
    {
        return $this->data['average']->bugs;
    }

    public function getDifficulty()
    {
        return $this->data['average']->difficulty;
    }

    public function getIntelligentContent()
    {
        return $this->data['average']->intelligentContent;
    }

    public function getVocabulary()
    {
        if (0 === $this->getNumberOfFiles()) {
            return 0;
        }

        return array_sum(array_map(function ($item) {
            return $item->get('vocabulary');
        }, $this->data['metrics']->all()))/ $this->getNumberOfFiles();
    }

    public function getNumberOfFiles()
    {
        return count($this->data['files']);
    }

    public function getFiles()
    {
        return $this->data['files'];
    }

    public function getName()
    {
        return 'mvpdoers.phpmetrics_collector';
    }
}
