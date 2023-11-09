<?php
/**
 * Created for plugin-component-queue
 * Date: 13.10.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Components\Queue\Commands;

use Khill\Duration\Duration;
use SalesRender\Plugin\Components\Db\ModelInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use XAKEPEHOK\Path\Path;

abstract class QueueCommand extends Command
{

    protected int $started;
    protected int $limit;
    protected int $handled = 0;
    protected int $maxMemoryInMb;
    /** @var Process[] */
    protected array $processes = [];
    protected string $name;
    private int $lastWriteUsedMemory;

    public function __construct(string $name, int $limit, int $maxMemoryInMb = 25)
    {
        parent::__construct($name . ':queue');
        $this->name = $name;
        $this->limit = $limit;
        $this->maxMemoryInMb = $maxMemoryInMb * 1024 * 1024;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isMutexEnabled = !$input->getOption('disable-mutex');

        if ($isMutexEnabled) {
            $mutexFile = Path::root()->down('runtime')->down("{$this->name}.mutex");
            $mutex = fopen((string) $mutexFile, 'c');

            if (!$mutex) {
                throw new RuntimeException("Can not create mutex file '{$mutexFile}'. No permissions?");
            }

            if (!flock($mutex, LOCK_EX|LOCK_NB)) {
                fclose($mutex);
                throw new RuntimeException("Command '{$this->getName()}' already running");
            }
        }

        try {
            $this->started = time();
            $this->writeUsedMemory($output);

            do {
                $this->writeUsedMemory($output);
                sleep(1);

                foreach ($this->processes as $key => $process) {
                    if (!$process->isTerminated()) {
                        continue;
                    }

                    if ($process->isSuccessful()) {
                        $output->writeln("<fg=green>[FINISHED]</> Process '{$key}' was finished.");
                    } else {
                        $output->writeln("<fg=red>[FAILED]</> Process '{$key}' with code '{$process->getExitCode()}' and message '{$process->getExitCodeText()}'.");
                    }

                    unset($this->processes[$key]);
                }

                if ($this->limit > 0 && count($this->processes) >= $this->limit) {
                    continue;
                }

                $models = $this->findModels();
                foreach ($models as $model) {
                    if ($this->handleQueue($model)) {
                        $this->startedLog($model, $output);
                    }
                }

            } while (memory_get_usage(true) < $this->maxMemoryInMb);

            $output->writeln('<info> -- High memory usage. Stopped -- </info>');
        } finally {
            if ($isMutexEnabled) {
                flock($mutex, LOCK_UN);
                fclose($mutex);
            }
        }

        return 0;
    }

    /**
     * @return ModelInterface[]
     */
    abstract protected function findModels(): array;

    protected function startedLog(ModelInterface $model, OutputInterface $output): void
    {
        $output->writeln("<info>[STARTED]</info> Process '{$model->getId()}'");
    }

    protected function handleQueue(ModelInterface $model): bool
    {
        if (isset($this->processes[$model->getId()])) {
            return false;
        }

        $this->processes[$model->getId()] = new Process([
            $_ENV['LV_PLUGIN_PHP_BINARY'],
            (string) Path::root()->down('console.php'),
            "{$this->name}:handle",
            $model->getId(),
        ]);

        $this->processes[$model->getId()]->start();

        $this->handled++;

        return true;
    }

    protected function writeUsedMemory(OutputInterface $output): void
    {
        if (!isset($this->lastWriteUsedMemory)) {
            $this->lastWriteUsedMemory = time();
        }

        if ($this->lastWriteUsedMemory > (time() - 5 )) {
            return;
        }

        $this->lastWriteUsedMemory = time();

        $used = round(memory_get_usage(true) / 1024 / 1024, 2);
        $max = round($this->maxMemoryInMb / 1024 / 1024, 2);
        $uptime = (new Duration(max(time() - $this->started, 1)))->humanize();
        $output->writeln("<info> -- Handled: {$this->handled}; Used {$used} MB of {$max} MB; Uptime: {$uptime} -- </info>");
    }

    protected function configure()
    {
        parent::configure();
        $this->addOption(
            'disable-mutex',
            'dm',
            InputOption::VALUE_NONE,
            'Disable mutex for single run'
        );
    }

}