<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Player\Tests;

use Blackfire\Player\Console\Application;
use Blackfire\Player\Player;
use Blackfire\Player\Scenario;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

class PlayerTest extends \PHPUnit_Framework_TestCase
{
    private static $port;

    public static function setUpBeforeClass()
    {
        static::$port = getenv('BLACKFIRE_WS_PORT');

        $finder = new PhpExecutableFinder();

        if (false === $binary = $finder->find()) {
            throw new \RuntimeException('Unable to find PHP binary to run server.');
        }

        $server = (new ProcessBuilder(['exec', $binary, '-S', '0:'.static::$port, '-t', __DIR__.'/fixtures']))->getProcess();
        $server->start();

        usleep(250000);

        if ($server->isTerminated() && !$server->isSuccessful()) {
            throw new ProcessFailedException($server);
        }

        register_shutdown_function(function () use ($server) {
            $server->stop();
        });
    }

    public function providePlayerTests()
    {
        $dirs = Finder::create()
            ->in(__DIR__.'/fixtures')
            ->directories();

        foreach ($dirs as $dir) {
            foreach (['index.php', 'output.txt', 'scenario.bkf'] as $file) {
                $file = sprintf('%s/%s', $dir->getPathname(), $file);
                if (!file_exists($file)) {
                    throw new \Exception(sprintf('The fixture file "%s" does not exist.', $file));
                }
            }

            $jsonFile = sprintf('%s/output-json.txt', $dir->getPathname());
            $reportFile = sprintf('%s/output-full-report.txt', $dir->getPathname());

            yield $dir->getBasename() => [
                sprintf('%s/scenario.bkf', $dir->getPathname()),
                file_get_contents(sprintf('%s/output.txt', $dir->getPathname())),
                file_exists($jsonFile) ? file_get_contents($jsonFile) : null,
                file_exists($reportFile) ? file_get_contents($reportFile) : null,
            ];
        }
    }

    /** @dataProvider providePlayerTests */
    public function testPlayer($file, $expectedOutput, $expectedJsonOutput, $expectedReportOutput)
    {
        $application = new Application();
        $tester = new CommandTester($application->get('run'));
        $tester->execute([
            'file' => $file,
            '--endpoint' => 'http://0:'.static::$port,
        ]);

        $output = $tester->getDisplay();
        $output = implode("\n", array_map('rtrim', explode("\n", $output)));
        $expectedOutput = str_replace('{{ PORT }}', static::$port, $expectedOutput);

        $this->assertSame($expectedOutput, $output);

        // For --json and --full-report, the output is composed of STDOUT + STDERR.
        // That's because the CommandTester use a StreamOutput instead of a ConsoleOutput.

        if ($expectedJsonOutput) {
            $tester->execute([
                'file' => $file,
                '--endpoint' => 'http://0:'.static::$port,
                '--json' => true,
            ]);

            $this->assertSame($expectedJsonOutput, $tester->getDisplay());
        }

        if ($expectedReportOutput) {
            $tester->execute([
                'file' => $file,
                '--endpoint' => 'http://0:'.static::$port,
                '--full-report' => true,
            ]);

            $this->assertSame($expectedReportOutput, $tester->getDisplay());
        }
    }
}
