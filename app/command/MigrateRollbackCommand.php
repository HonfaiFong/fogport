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
 * 回滚数据库迁移。
 *
 * 用法：
 *   php webman migrate:rollback              回滚最近一批
 *   php webman migrate:rollback -t 0         回滚全部
 *   php webman migrate:rollback -t 20261121010000  回滚到指定版本
 */
#[AsCommand('migrate:rollback', '回滚数据库迁移')]
class MigrateRollbackCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED, '回滚到指定版本');
        $this->addOption('env', 'e', InputOption::VALUE_REQUIRED, '环境名', 'dev');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Config(require base_path() . '/phinx.php');
        $manager = new Manager($config, $input, $output);

        $manager->rollback(
            $input->getOption('env'),
            $input->getOption('target'),
            false
        );

        return Command::SUCCESS;
    }
}