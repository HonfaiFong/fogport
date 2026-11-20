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
 * 数据库迁移命令（封装 Phinx，webman 官方推荐方案）。
 *
 * 之所以封装：webman/console 不含 migrate 命令，直接调 vendor/bin/phinx 需要
 * 记完整路径与 --configuration 参数，且无法复用 webman 的 .env 加载流程。
 *
 * 用法：
 *   php webman migrate                      执行全部未执行迁移
 *   php webman migrate -t 20261121010000    迁移到指定版本
 *   php webman migrate:rollback             回滚最近一批
 *   php webman migrate:status               查看迁移状态
 */
#[AsCommand('migrate', '执行数据库迁移（Phinx）')]
class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED, '迁移到指定版本（时间戳）');
        $this->addOption('env', 'e', InputOption::VALUE_REQUIRED, '环境名', 'dev');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, '仅打印将要执行的 SQL，不实际执行');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Config(require base_path() . '/phinx.php');
        $manager = new Manager($config, $input, $output);

        $env = $input->getOption('env');
        $target = $input->getOption('target');
        // Phinx 的 dry-run 通过 pretend 实现
        $manager->migrate($env, $target, (bool) $input->getOption('dry-run'));

        return Command::SUCCESS;
    }
}