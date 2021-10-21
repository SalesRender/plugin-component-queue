<?php
/**
 * Created for LeadVertex
 * Date: 10/14/21 7:10 PM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Queue\Models\Task;

use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Db\Helpers\ReflectionHelper;
use Leadvertex\Plugin\Components\Db\Helpers\UuidHelper;
use Leadvertex\Plugin\Components\Db\Model;

abstract class Task extends Model
{

    protected ?string $companyId = null;

    protected ?string $pluginAlias = null;

    protected ?string $pluginId = null;

    protected int $createdAt;

    protected TaskAttempt $attempt;

    public function __construct(TaskAttempt $attempt)
    {
        $this->id = UuidHelper::getUuid();
        if (Connector::hasReference()) {
            $this->companyId = Connector::getReference()->getCompanyId();
            $this->pluginAlias = Connector::getReference()->getCompanyId();
            $this->pluginId = Connector::getReference()->getId();
        }
        $this->createdAt = time();
        $this->attempt = $attempt;
    }

    public function getPluginReference(): ?PluginReference
    {
        if ($this->companyId && $this->pluginAlias && $this->pluginId) {
            return new PluginReference(
                $this->companyId,
                $this->pluginAlias,
                $this->pluginId
            );
        }
        return null;
    }

    protected static function beforeWrite(array $data): array
    {
        /** @var TaskAttempt $attempt */
        $attempt = $data['attempt'];

        $data['attemptLastTime'] = $attempt->getLastTime();
        $data['attemptNumber'] = $attempt->getNumber();
        $data['attemptLimit'] = $attempt->getLimit();
        $data['attemptInterval'] = $attempt->getInterval();
        $data['attemptLog'] = $attempt->getLog();

        unset($data['attempt']);
        return $data;
    }

    protected static function afterRead(array $data): array
    {
        $attempt = ReflectionHelper::newWithoutConstructor(TaskAttempt::class);

        ReflectionHelper::setProperty($attempt, 'lastTime', $data['attemptLastTime']);
        ReflectionHelper::setProperty($attempt, 'number', $data['attemptNumber']);
        ReflectionHelper::setProperty($attempt, 'limit', $data['attemptLimit']);
        ReflectionHelper::setProperty($attempt, 'interval', $data['attemptInterval']);
        ReflectionHelper::setProperty($attempt, 'log', $data['attemptLog']);

        $data['attempt'] = $attempt;
        return $data;
    }

    protected static function getSerializeFields(): array
    {
        $fields = parent::getSerializeFields();
        $fields[] = 'attempt';
        return array_filter($fields, function ($value) {
            $exclude = [
                'attemptLastTime',
                'attemptNumber',
                'attemptLimit',
                'attemptInterval',
                'attemptLog',
            ];
            return !in_array($value, $exclude);
        });
    }

    public static function schema(): array
    {
        return [
            'companyId' => ['VARCHAR(255)'],
            'pluginAlias' => ['VARCHAR(255)'],
            'pluginId' => ['VARCHAR(255)'],
            'createdAt' => ['INT', 'NOT NULL'],

            'attemptLastTime' => ['INT'],
            'attemptNumber' => ['INT', 'NOT NULL'],
            'attemptLimit' => ['INT', 'NOT NULL'],
            'attemptInterval' => ['INT', 'NOT NULL'],
            'attemptLog' => ['VARCHAR(500)'],
        ];
    }
}