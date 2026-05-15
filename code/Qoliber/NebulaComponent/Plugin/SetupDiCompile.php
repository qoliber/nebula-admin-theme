<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Plugin;

use Magento\Setup\Console\Command\DiCompileCommand;
use Qoliber\NebulaComponent\Console\Command\CompileCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run nebula:compile automatically after setup:di:compile so production deploys pick up
 * the compiled manifest without a second invocation.
 */
class SetupDiCompile
{
    public function __construct(
        private readonly CompileCommand $compileCommand
    ) {
    }

    /**
     * @param \Magento\Setup\Console\Command\DiCompileCommand $subject
     * @param int $result
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     */
    public function afterExecute(
        DiCompileCommand $subject,
        int $result,
        InputInterface $input,
        OutputInterface $output
    ): int {
        // If DI compile itself failed, don't paper over the problem.
        if ($result !== 0) {
            return $result;
        }

        $output->writeln('');
        $output->writeln('<info>Nebula: compiling definitions manifest...</info>');

        try {
            $exit = $this->compileCommand->run(new ArrayInput([]), $output);
        } catch (\Throwable $e) {
            $output->writeln('<fg=red>Nebula compile failed: ' . $e->getMessage() . '</>');
            return 1;
        }

        return $exit === 0 ? $result : $exit;
    }
}
