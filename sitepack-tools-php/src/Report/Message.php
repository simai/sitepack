<?php

declare(strict_types=1);

namespace SitePack\Report;

class Message
{
    public string $level;

    public string $code;

    public string $message;

    /** @var array<string, string> */
    public array $context;

    /**
     * @param string $level
     * @param string $code
     * @param string $message
     * @param array<string, string> $context
     * @return void
     */
    public function __construct(string $level, string $code, string $message, array $context = [])
    {
        $this->level = $level;
        $this->code = $code;
        $this->message = $message;
        $this->context = $context;
    }

    /**
     * @return array{level:string,code:string,message:string,context:array<string,string>}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
