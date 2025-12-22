<?php

declare(strict_types=1);

namespace SitePack\Report;

class ArtifactResult
{
    public string $id;

    public ?string $mediaType;

    public ?string $path;

    public ?int $sizeExpected;

    public ?int $sizeActual;

    public ?string $digestExpected;

    public ?string $digestActual;

    public string $status;

    /** @var array<int, array{level:string,code:string,message:string,line:int|null}> */
    public array $details = [];

    /**
     * @param string $id
     * @param string|null $mediaType
     * @param string|null $path
     * @param int|null $sizeExpected
     * @param string|null $digestExpected
     * @return void
     */
    public function __construct(
        string $id,
        ?string $mediaType,
        ?string $path,
        ?int $sizeExpected,
        ?string $digestExpected
    ) {
        $this->id = $id;
        $this->mediaType = $mediaType;
        $this->path = $path;
        $this->sizeExpected = $sizeExpected;
        $this->sizeActual = null;
        $this->digestExpected = $digestExpected;
        $this->digestActual = null;
        $this->status = 'ok';
    }

    /**
     * @param string $level
     * @param string $code
     * @param string $message
     * @param int|null $line
     * @return void
     */
    public function addDetail(string $level, string $code, string $message, ?int $line = null): void
    {
        $this->details[] = [
            'level' => $level,
            'code' => $code,
            'message' => $message,
            'line' => $line,
        ];
    }

    /**
     * @return void
     */
    public function finalizeStatus(): void
    {
        if ($this->status === 'skipped') {
            return;
        }

        $hasError = false;
        $hasWarning = false;

        foreach ($this->details as $detail) {
            if ($detail['level'] === 'error') {
                $hasError = true;
            } elseif ($detail['level'] === 'warning') {
                $hasWarning = true;
            }
        }

        if ($hasError) {
            $this->status = 'error';
        } elseif ($hasWarning) {
            $this->status = 'warning';
        } else {
            $this->status = 'ok';
        }
    }

    /**
     * @return array{
     *   id:string,
     *   mediaType:?string,
     *   path:?string,
     *   sizeExpected:?int,
     *   sizeActual:?int,
     *   digestExpected:?string,
     *   digestActual:?string,
     *   status:string,
     *   details:array<int, array{level:string,code:string,message:string,line:int|null}>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'mediaType' => $this->mediaType,
            'path' => $this->path,
            'sizeExpected' => $this->sizeExpected,
            'sizeActual' => $this->sizeActual,
            'digestExpected' => $this->digestExpected,
            'digestActual' => $this->digestActual,
            'status' => $this->status,
            'details' => $this->details,
        ];
    }
}
