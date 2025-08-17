<?php
namespace NHRROB\WPFatalTester\Models;

class FatalError {
    public string $type;
    public string $message;
    public string $file;
    public int $line;
    public string $severity;
    public ?string $suggestion;
    public array $context;

    public function __construct(
        string $type,
        string $message,
        string $file,
        int $line,
        string $severity = 'error',
        ?string $suggestion = null,
        array $context = []
    ) {
        $this->type = $type;
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
        $this->severity = $severity;
        $this->suggestion = $suggestion;
        $this->context = $context;
    }

    public function toArray(): array {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'severity' => $this->severity,
            'suggestion' => $this->suggestion,
            'context' => $this->context,
        ];
    }

    public function __toString(): string {
        $location = basename($this->file) . ':' . $this->line;
        return "[{$this->severity}] {$this->type}: {$this->message} ({$location})";
    }
}
