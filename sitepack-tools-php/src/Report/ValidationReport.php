<?php

declare(strict_types=1);

namespace SitePack\Report;

class ValidationReport
{
    /** @var array<string, string> */
    private array $tool;

    private string $startedAt;

    private ?string $finishedAt;

    /** @var array{type:string,path:string} */
    private array $target;

    /** @var array{errors:int,warnings:int,artifactsTotal:int,artifactsValidated:int,artifactsSkipped:int,ndjsonLinesValidated:int} */
    private array $summary;

    /** @var array<int, ArtifactResult> */
    private array $artifacts = [];

    /** @var array<int, Message> */
    private array $messages = [];

    /**
     * @param array<string, string> $tool
     * @param string $targetType
     * @param string $targetPath
     * @return void
     */
    public function __construct(array $tool, string $targetType, string $targetPath)
    {
        $this->tool = $tool;
        $this->startedAt = gmdate('c');
        $this->finishedAt = null;
        $this->target = [
            'type' => $targetType,
            'path' => $targetPath,
        ];
        $this->summary = [
            'errors' => 0,
            'warnings' => 0,
            'artifactsTotal' => 0,
            'artifactsValidated' => 0,
            'artifactsSkipped' => 0,
            'ndjsonLinesValidated' => 0,
        ];
    }

    /**
     * @param string $level
     * @param string $code
     * @param string $message
     * @param array<string, string> $context
     * @return void
     */
    public function addMessage(string $level, string $code, string $message, array $context = []): void
    {
        $this->messages[] = new Message($level, $code, $message, $context);
        $this->incrementLevel($level);
    }

    /**
     * @param ArtifactResult $artifact
     * @return void
     */
    public function addArtifact(ArtifactResult $artifact): void
    {
        $this->artifacts[] = $artifact;
    }

    /**
     * @param int $count
     * @return void
     */
    public function setArtifactsTotal(int $count): void
    {
        $this->summary['artifactsTotal'] = $count;
    }

    /**
     * @param int $count
     * @return void
     */
    public function incrementArtifactsValidated(int $count = 1): void
    {
        $this->summary['artifactsValidated'] += $count;
    }

    /**
     * @param int $count
     * @return void
     */
    public function incrementArtifactsSkipped(int $count = 1): void
    {
        $this->summary['artifactsSkipped'] += $count;
    }

    /**
     * @param int $count
     * @return void
     */
    public function incrementNdjsonLinesValidated(int $count): void
    {
        $this->summary['ndjsonLinesValidated'] += $count;
    }

    /**
     * @return void
     */
    public function incrementError(): void
    {
        $this->summary['errors'] += 1;
    }

    /**
     * @return void
     */
    public function incrementWarning(): void
    {
        $this->summary['warnings'] += 1;
    }

    /**
     * @return void
     */
    public function markFinished(): void
    {
        $this->finishedAt = gmdate('c');
    }

    /**
     * @param string $type
     * @param string $path
     * @return void
     */
    public function setTarget(string $type, string $path): void
    {
        $this->target = [
            'type' => $type,
            'path' => $path,
        ];
    }

    /**
     * @return int
     */
    public function getErrorCount(): int
    {
        return $this->summary['errors'];
    }

    /**
     * @return int
     */
    public function getWarningCount(): int
    {
        return $this->summary['warnings'];
    }

    /**
     * @return array{
     *   tool:array<string,string>,
     *   startedAt:string,
     *   finishedAt:?string,
     *   target:array{type:string,path:string},
     *   summary:array{
     *     errors:int,
     *     warnings:int,
     *     artifactsTotal:int,
     *     artifactsValidated:int,
     *     artifactsSkipped:int,
     *     ndjsonLinesValidated:int
     *   },
     *   artifacts:list<array{
     *     id:string,
     *     mediaType:?string,
     *     path:?string,
     *     sizeExpected:?int,
     *     sizeActual:?int,
     *     digestExpected:?string,
     *     digestActual:?string,
     *     status:string,
     *     details:array<int, array{level:string,code:string,message:string,line:int|null}>
     *   }>,
     *   messages:list<array{level:string,code:string,message:string,context:array<string,string>}>
     * }
     */
    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'startedAt' => $this->startedAt,
            'finishedAt' => $this->finishedAt,
            'target' => $this->target,
            'summary' => $this->summary,
            'artifacts' => array_map(
                static function (ArtifactResult $artifact): array {
                    return $artifact->toArray();
                },
                $this->artifacts
            ),
            'messages' => array_map(
                static function (Message $message): array {
                    return $message->toArray();
                },
                $this->messages
            ),
        ];
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{}';
        }

        return $json;
    }

    /**
     * @param string $level
     * @return void
     */
    private function incrementLevel(string $level): void
    {
        if ($level === 'error') {
            $this->summary['errors'] += 1;
            return;
        }

        if ($level === 'warning') {
            $this->summary['warnings'] += 1;
        }
    }
}
