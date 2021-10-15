<?php
/**
 * Created for LeadVertex
 * Date: 10/14/21 7:17 PM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Queue\Models\Task;

class TaskAttempt
{

    protected ?int $lastTime = null;

    protected int $number = 0;

    protected int $limit;

    protected int $interval;

    protected string $log = '';

    public function __construct(int $limit, int $interval)
    {
        $this->limit = $limit;
        $this->interval = $interval;
    }

    public function getLastTime(): ?int
    {
        return $this->lastTime;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    public function getLog(): string
    {
        return $this->log;
    }

    public function attempt(string $log): void
    {
        $this->lastTime = time();
        $this->number++;
        $this->log = $log;
    }

    public function isSpent(): bool
    {
        return $this->number >= $this->limit;
    }

}