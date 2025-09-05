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
    public ?string $pluginRoot;

    public function __construct(
        string $type,
        string $message,
        string $file,
        int $line,
        string $severity = 'error',
        ?string $suggestion = null,
        array $context = [],
        ?string $pluginRoot = null
    ) {
        $this->type = $type;
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
        $this->severity = $severity;
        $this->suggestion = $suggestion;
        $this->context = $context;
        $this->pluginRoot = $pluginRoot;
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
            'pluginRoot' => $this->pluginRoot,
        ];
    }

    public function __toString(): string {
        $location = $this->getRelativeFilePath() . ':' . $this->line;
        return "[{$this->severity}] {$this->type}: {$this->message} ({$location})";
    }

    /**
     * Get the file path relative to the plugin root
     */
    public function getRelativeFilePath(): string {
        if ($this->pluginRoot && strpos($this->file, $this->pluginRoot) === 0) {
            $relativePath = substr($this->file, strlen($this->pluginRoot));
            return ltrim($relativePath, '/\\');
        }
        return basename($this->file);
    }

    /**
     * Get the absolute file path
     */
    public function getAbsoluteFilePath(): string {
        return $this->file;
    }
}
