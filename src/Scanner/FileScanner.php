<?php
namespace NHRROB\WPFatalTester\Scanner;

class FileScanner {
    
    private array $excludedDirectories = [
        'node_modules',
        'vendor',
        '.git',
        '.svn',
        '.hg',
        'tests',
        'test',
        '__tests__',
        'spec',
        'docs',
        'documentation',
        'assets/js',
        'assets/css',
        'dist',
        'build',
        '.github',
        '.vscode',
        '.idea',
        'wp-fatal-tester', // Exclude wp-fatal-tester package directory
    ];
    
    private array $excludedFiles = [
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'yarn.lock',
        'webpack.config.js',
        'gulpfile.js',
        'gruntfile.js',
        '.gitignore',
        '.gitattributes',
        'README.md',
        'CHANGELOG.md',
        'LICENSE',
        'LICENSE.txt',
    ];
    
    private array $allowedExtensions = [
        'php',
    ];

    public function scanDirectory(string $directory): array {
        if (!is_dir($directory) || !is_readable($directory)) {
            return [];
        }

        $files = [];
        $this->scanDirectoryRecursive($directory, $files);
        
        return $files;
    }

    private function scanDirectoryRecursive(string $directory, array &$files): void {
        $iterator = new \DirectoryIterator($directory);
        
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            
            $filename = $fileInfo->getFilename();
            $filepath = $fileInfo->getPathname();
            
            if ($fileInfo->isDir()) {
                // Skip excluded directories
                if (in_array($filename, $this->excludedDirectories)) {
                    continue;
                }

                // Skip wp-fatal-tester package directories (additional check)
                if ($this->isWpFatalTesterDirectory($filepath)) {
                    continue;
                }

                // Skip hidden directories
                if (strpos($filename, '.') === 0) {
                    continue;
                }

                // Recursively scan subdirectory
                $this->scanDirectoryRecursive($filepath, $files);
            } elseif ($fileInfo->isFile()) {
                // Skip excluded files
                if (in_array($filename, $this->excludedFiles)) {
                    continue;
                }
                
                // Skip hidden files
                if (strpos($filename, '.') === 0) {
                    continue;
                }
                
                // Check file extension
                $extension = strtolower($fileInfo->getExtension());
                if (in_array($extension, $this->allowedExtensions)) {
                    $files[] = $filepath;
                }
            }
        }
    }

    public function isPhpFile(string $filePath): bool {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'php') {
            return true;
        }
        
        // Check if file starts with PHP opening tag
        $handle = fopen($filePath, 'r');
        if ($handle) {
            $firstLine = fgets($handle);
            fclose($handle);
            
            if (strpos($firstLine, '<?php') === 0 || strpos($firstLine, '<?') === 0) {
                return true;
            }
        }
        
        return false;
    }

    public function getFileInfo(string $filePath): array {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $fileInfo = [
            'path' => $filePath,
            'name' => basename($filePath),
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
            'readable' => is_readable($filePath),
            'writable' => is_writable($filePath),
        ];
        
        if ($this->isPhpFile($filePath)) {
            $fileInfo['type'] = 'php';
            $fileInfo['lines'] = $this->countLines($filePath);
            $fileInfo['functions'] = $this->extractFunctions($filePath);
            $fileInfo['classes'] = $this->extractClasses($filePath);
        }
        
        return $fileInfo;
    }

    private function countLines(string $filePath): int {
        $lines = 0;
        $handle = fopen($filePath, 'r');
        
        if ($handle) {
            while (fgets($handle) !== false) {
                $lines++;
            }
            fclose($handle);
        }
        
        return $lines;
    }

    private function extractFunctions(string $filePath): array {
        $functions = [];
        $content = file_get_contents($filePath);
        
        if (preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/i', $content, $matches)) {
            $functions = array_unique($matches[1]);
        }
        
        return $functions;
    }

    private function extractClasses(string $filePath): array {
        $classes = [];
        $content = file_get_contents($filePath);
        
        // Extract class names
        if (preg_match_all('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $content, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }
        
        // Extract interface names
        if (preg_match_all('/interface\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $content, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }
        
        // Extract trait names
        if (preg_match_all('/trait\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $content, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }
        
        return array_unique($classes);
    }

    public function setExcludedDirectories(array $directories): void {
        $this->excludedDirectories = $directories;
    }

    public function addExcludedDirectory(string $directory): void {
        if (!in_array($directory, $this->excludedDirectories)) {
            $this->excludedDirectories[] = $directory;
        }
    }

    public function setExcludedFiles(array $files): void {
        $this->excludedFiles = $files;
    }

    public function addExcludedFile(string $file): void {
        if (!in_array($file, $this->excludedFiles)) {
            $this->excludedFiles[] = $file;
        }
    }

    public function setAllowedExtensions(array $extensions): void {
        $this->allowedExtensions = array_map('strtolower', $extensions);
    }

    public function addAllowedExtension(string $extension): void {
        $extension = strtolower($extension);
        if (!in_array($extension, $this->allowedExtensions)) {
            $this->allowedExtensions[] = $extension;
        }
    }

    public function getExcludedDirectories(): array {
        return $this->excludedDirectories;
    }

    public function getExcludedFiles(): array {
        return $this->excludedFiles;
    }

    public function getAllowedExtensions(): array {
        return $this->allowedExtensions;
    }

    private function isWpFatalTesterDirectory(string $path): bool {
        // Check for wp-fatal-tester package indicators
        $indicators = [
            'fataltest',
            'src/FatalTester.php',
            'composer.json'
        ];

        foreach ($indicators as $indicator) {
            if (!file_exists($path . '/' . $indicator)) {
                return false;
            }
        }

        // Additional check: look for our specific namespace in composer.json
        $composerFile = $path . '/composer.json';
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            if (isset($composerData['name']) && $composerData['name'] === 'nhrrob/wp-fatal-tester') {
                return true;
            }
        }

        return false;
    }
}
