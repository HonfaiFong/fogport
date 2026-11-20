<?php

namespace app\command;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 查看迁移状态：哪些已执行、哪些待执行。
 *
 * 用法：php webman migrate:status
 */
#[AsCommand('migrate:status', '查看数据库迁移状态')]
class MigrateStatusCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('env', 'e', InputOption::VALUE_REQUIRED, '环境名', 'dev');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Config(require base_path() . '/phinx.php');
        $manager = new Manager($config, $input, $output);

        $manager->printStatus($input->getOption('env'));

        return Command::SUCCESS;
    }
}