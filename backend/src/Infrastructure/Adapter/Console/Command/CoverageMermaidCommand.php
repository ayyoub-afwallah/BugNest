<?php

namespace App\Infrastructure\Adapter\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'coverage:mermaid',
    description: 'Generate Mermaid diagram from PHPUnit coverage data',
)]
class CoverageMermaidCommand extends Command
{
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('coverage-file', InputArgument::REQUIRED, 'Path to the PHPUnit coverage PHP file')
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Output file path', 'coverage-diagram.mmd')
            ->addOption('max-depth', null, InputOption::VALUE_OPTIONAL, 'Maximum folder depth to display', 5)
            ->addOption('min-coverage', null, InputOption::VALUE_OPTIONAL, 'Minimum coverage percentage to include', 0)
            ->addOption('base-path', null, InputOption::VALUE_OPTIONAL, 'Base path to strip from file paths', 'src/')
            ->addOption('src-only', null, InputOption::VALUE_NONE, 'Show only src directories (filter out tests, vendor, etc.)')
            ->addOption('exclude-paths', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Paths to exclude (e.g., tests, vendor)', [])
            ->addOption('chart-type', null, InputOption::VALUE_OPTIONAL, 'Chart type: pie, tree, bar, folder-pies', 'bar')
            ->addOption('separate-files', null, InputOption::VALUE_NONE, 'Generate separate diagram files for each folder')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Show debug information about the coverage file format')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $coverageFile = $input->getArgument('coverage-file');
        $outputFile = $input->getOption('output');
        $maxDepth = (int) $input->getOption('max-depth');
        $minCoverage = (float) $input->getOption('min-coverage');
        $basePath = $input->getOption('base-path');
        $srcOnly = $input->getOption('src-only');
        $excludePaths = $input->getOption('exclude-paths');
        $chartType = strtolower($input->getOption('chart-type'));
        $separateFiles = $input->getOption('separate-files');
        $debug = $input->getOption('debug');

        // Default exclude paths when src-only is enabled
        if ($srcOnly && empty($excludePaths)) {
            $excludePaths = ['tests', 'test', 'vendor', 'var', 'public', 'bin', 'config'];
        }

        if (!$this->filesystem->exists($coverageFile)) {
            $io->error("Coverage file not found: {$coverageFile}");
            return Command::FAILURE;
        }

        try {
            // Debug file information
            if ($debug) {
                $this->debugCoverageFile($io, $coverageFile);
            }

            // Load the coverage data
            $coverageData = $this->loadCoverageData($coverageFile);

            if ($debug) {
                $io->note('Successfully parsed coverage data with ' . count($coverageData) . ' files');
                if (count($coverageData) > 0) {
                    $firstFile = array_key_first($coverageData);
                    $io->note("Sample file: {$firstFile} with " . count($coverageData[$firstFile]) . " lines");
                }
            }

            // Process coverage data by folders
            $folderCoverage = $this->processFolderCoverage($coverageData, $basePath, $maxDepth, $excludePaths, $srcOnly);

            // Filter by minimum coverage
            $folderCoverage = array_filter($folderCoverage, fn($data) => $data['coverage'] >= $minCoverage);

            if ($chartType === 'folder-pies' || $separateFiles) {
                // Generate individual pie charts for each folder
                $this->generateFolderPieCharts($folderCoverage, $coverageData, $outputFile, $separateFiles, $basePath, $excludePaths, $srcOnly);
                $io->success("Individual folder pie charts generated successfully!");
            } else {
                // Generate single Mermaid diagram
                $mermaidContent = $this->generateMermaidDiagram($folderCoverage, $chartType);

                // Write to output file
                $this->filesystem->dumpFile($outputFile, $mermaidContent);
                $io->success("Mermaid diagram generated successfully: {$outputFile}");

                // Display summary
                $this->displaySummary($io, $folderCoverage);
            }

            // Display summary
            $this->displaySummary($io, $folderCoverage);

            // Display summary
            $this->displaySummary($io, $folderCoverage);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Error processing coverage data: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function loadCoverageData(string $coverageFile): array
    {
        $content = file_get_contents($coverageFile);

        // Try different parsing methods based on file extension and content

        // 1. Try XML (Clover format)
        if (str_ends_with($coverageFile, '.xml') || str_contains($content, '<?xml')) {
            return $this->parseCloverXml($content);
        }

        // 2. Try JSON format
        if (str_ends_with($coverageFile, '.json') || str_starts_with(trim($content), '{')) {
            $jsonData = json_decode($content, true);
            if ($jsonData !== null) {
                return $this->parseJsonCoverage($jsonData);
            }
        }

        // 3. Try PHP include (coverage data as PHP array)
        if (str_ends_with($coverageFile, '.php') || str_ends_with($coverageFile, '.cov')) {
            try {
                $data = include $coverageFile;

                // Handle SebastianBergmann\CodeCoverage\CodeCoverage object
                if (is_object($data) && method_exists($data, 'getData')) {
                    $rawData = $data->getData();
                    return $this->parsePhpUnitData($rawData);
                }

                // Handle different PHPUnit coverage array formats
                if (isset($data['coverage'])) {
                    return $data['coverage'];
                } elseif (is_array($data)) {
                    return $this->parsePhpUnitData($data);
                }
            } catch (\Throwable $e) {
                // Continue to next parsing method
            }
        }

        // 4. Try unserialized data (legacy format)
        try {
            $unserialized = unserialize($content);
            if ($unserialized !== false) {
                if (is_object($unserialized) && method_exists($unserialized, 'getData')) {
                    return $this->parsePhpUnitData($unserialized->getData());
                } elseif (is_array($unserialized)) {
                    return $this->parsePhpUnitData($unserialized);
                }
            }
        } catch (\Throwable $e) {
            // Continue to next parsing method
        }

        // 5. Try to detect and parse custom serialized format
        if (str_contains($content, 'a:') || str_contains($content, 'O:')) {
            try {
                // Sometimes the file has additional metadata, try to extract just the coverage data
                if (preg_match('/s:\d+:"coverage";(.*?)(?:s:\d+:|$)/', $content, $matches)) {
                    $coverageData = unserialize($matches[1]);
                    if ($coverageData !== false) {
                        return $this->parsePhpUnitData($coverageData);
                    }
                }
            } catch (\Throwable $e) {
                // Continue
            }
        }

        throw new \InvalidArgumentException(
            'Unable to parse coverage file format. Supported formats: Clover XML (.xml), JSON (.json), PHP arrays (.php), and serialized PHPUnit data (.cov)'
        );
    }

    private function parseCloverXml(string $xmlContent): array
    {
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new \InvalidArgumentException('Invalid XML format');
        }

        $coverage = [];

        foreach ($xml->xpath('//file') as $file) {
            $filename = (string) $file['name'];
            $coverage[$filename] = [];

            foreach ($file->line as $line) {
                $lineNum = (int) $line['num'];
                $count = (int) $line['count'];
                $type = (string) $line['type'];

                // Only include executable lines (stmt = statement)
                if ($type === 'stmt') {
                    $coverage[$filename][$lineNum] = $count;
                }
            }
        }

        return $coverage;
    }

    private function parseJsonCoverage(array $jsonData): array
    {
        // Handle different JSON formats
        if (isset($jsonData['files'])) {
            // Format: {"files": {"file1.php": {"lines": {...}}}}
            $coverage = [];
            foreach ($jsonData['files'] as $filename => $fileData) {
                if (isset($fileData['lines'])) {
                    $coverage[$filename] = $fileData['lines'];
                }
            }
            return $coverage;
        }

        // Direct format: {"file1.php": {...}}
        return $jsonData;
    }

    private function parsePhpUnitData(array $data): array
    {
        // Handle nested coverage data structure
        $coverage = [];

        foreach ($data as $filename => $fileData) {
            if (is_array($fileData)) {
                // Modern PHPUnit format: filename => [line_num => [test_info, ...]]
                $lineCoverage = [];
                foreach ($fileData as $lineNum => $lineData) {
                    if (is_numeric($lineNum)) {
                        // Count how many times this line was executed
                        if (is_array($lineData)) {
                            $lineCoverage[$lineNum] = count($lineData);
                        } else {
                            $lineCoverage[$lineNum] = $lineData ? 1 : 0;
                        }
                    }
                }
                $coverage[$filename] = $lineCoverage;
            } else {
                // Legacy format
                $coverage[$filename] = $fileData;
            }
        }

        return $coverage;
    }

    private function processFolderCoverage(array $coverageData, string $basePath, int $maxDepth, array $excludePaths = [], bool $srcOnly = false): array
    {
        $folderStats = [];

        foreach ($coverageData as $filePath => $lines) {
            $originalPath = $filePath;

            // Skip files that don't match our criteria
            if ($srcOnly && !$this->isSrcFile($filePath)) {
                continue;
            }

            // Skip excluded paths
            if (!empty($excludePaths) && $this->shouldExcludePath($filePath, $excludePaths)) {
                continue;
            }

            // Remove base path
            if ($basePath && str_starts_with($filePath, $basePath)) {
                $filePath = substr($filePath, strlen($basePath));
            }

            // Skip if file path is empty after base path removal
            if (empty($filePath) || $filePath === '/') {
                continue;
            }

            // Get folder path with max depth
            $pathParts = explode('/', dirname($filePath));
            $pathParts = array_filter($pathParts, fn($part) => $part !== '' && $part !== '.');

            if (empty($pathParts)) {
                $folderPath = 'src';
            } else {
                $pathParts = array_slice($pathParts, 0, $maxDepth);
                $folderPath = implode('/', $pathParts);
            }

            // Calculate coverage for this file
            $totalLines = 0;
            $coveredLines = 0;

            if (is_array($lines)) {
                foreach ($lines as $lineNumber => $executionCount) {
                    if ($executionCount !== null && is_numeric($lineNumber)) { // Line is executable
                        $totalLines++;
                        if ($executionCount > 0) {
                            $coveredLines++;
                        }
                    }
                }
            }

            // Skip files with no executable lines
            if ($totalLines === 0) {
                continue;
            }

            if (!isset($folderStats[$folderPath])) {
                $folderStats[$folderPath] = [
                    'totalLines' => 0,
                    'coveredLines' => 0,
                    'files' => 0,
                    'filesList' => []
                ];
            }

            $folderStats[$folderPath]['totalLines'] += $totalLines;
            $folderStats[$folderPath]['coveredLines'] += $coveredLines;
            $folderStats[$folderPath]['files']++;
            $folderStats[$folderPath]['filesList'][] = basename($originalPath);
        }

        // Calculate coverage percentages
        $result = [];
        foreach ($folderStats as $folder => $stats) {
            $coverage = $stats['totalLines'] > 0
                ? ($stats['coveredLines'] / $stats['totalLines']) * 100
                : 0;

            $result[$folder] = [
                'coverage' => round($coverage, 2),
                'totalLines' => $stats['totalLines'],
                'coveredLines' => $stats['coveredLines'],
                'files' => $stats['files'],
                'filesList' => $stats['filesList']
            ];
        }

        // Sort by coverage percentage (descending)
        uasort($result, fn($a, $b) => $b['coverage'] <=> $a['coverage']);

        return $result;
    }

    private function isSrcFile(string $filePath): bool
    {
        // Check if file is in src directory or has typical source patterns
        return str_starts_with($filePath, 'src/') ||
            str_starts_with($filePath, '/src/') ||
            str_contains($filePath, '/src/') ||
            (str_ends_with($filePath, '.php') &&
                !str_contains($filePath, '/tests/') &&
                !str_contains($filePath, '/test/') &&
                !str_contains($filePath, '/vendor/'));
    }

    private function shouldExcludePath(string $filePath, array $excludePaths): bool
    {
        foreach ($excludePaths as $excludePath) {
            if (str_starts_with($filePath, $excludePath . '/') ||
                str_contains($filePath, '/' . $excludePath . '/') ||
                str_starts_with($filePath, $excludePath)) {
                return true;
            }
        }
        return false;
    }

    private function generateMermaidDiagram(array $folderCoverage, string $chartType = 'pie'): string
    {
        switch ($chartType) {
            case 'pie':
                return $this->generatePieChart($folderCoverage);
            case 'bar':
                return $this->generateBarChart($folderCoverage);
            case 'tree':
            default:
                return $this->generateTreeDiagram($folderCoverage);
        }
    }

    private function generatePieChart(array $folderCoverage): string
    {
        $mermaid = "%%{init: {'pie': {'textPosition': 0.5}, 'themeVariables': {'pieStrokeWidth': '2px'}}}%%\n";
        $mermaid .= "pie title Code Coverage by Directory\n";

        // Calculate total lines for percentage calculation
        $totalProjectLines = array_sum(array_column($folderCoverage, 'totalLines'));

        // If we have too many directories, group smaller ones
        $maxSlices = 8;
        $sortedFolders = $folderCoverage;

        if (count($sortedFolders) > $maxSlices) {
            $mainFolders = array_slice($sortedFolders, 0, $maxSlices - 1, true);
            $otherFolders = array_slice($sortedFolders, $maxSlices - 1, null, true);

            $otherTotalLines = array_sum(array_column($otherFolders, 'totalLines'));
            $otherCoveredLines = array_sum(array_column($otherFolders, 'coveredLines'));
            $otherFiles = array_sum(array_column($otherFolders, 'files'));

            if ($otherTotalLines > 0) {
                $mainFolders['Others'] = [
                    'coverage' => round(($otherCoveredLines / $otherTotalLines) * 100, 2),
                    'totalLines' => $otherTotalLines,
                    'coveredLines' => $otherCoveredLines,
                    'files' => $otherFiles
                ];
            }

            $sortedFolders = $mainFolders;
        }

        foreach ($sortedFolders as $folder => $data) {
            $percentage = $totalProjectLines > 0
                ? round(($data['totalLines'] / $totalProjectLines) * 100, 1)
                : 0;

            $label = $this->sanitizeLabel($folder);
            $coverage = $data['coverage'];

            // Format: "Directory (85.5%)" : percentage_of_codebase
            $mermaid .= "    \"{$label} ({$coverage}%)\" : {$percentage}\n";
        }

        return $mermaid;
    }

    private function generateFolderPieCharts(array $folderCoverage, array $originalCoverageData, string $outputFile, bool $separateFiles, string $basePath, array $excludePaths, bool $srcOnly): void
    {
        $baseFileName = pathinfo($outputFile, PATHINFO_FILENAME);
        $extension = pathinfo($outputFile, PATHINFO_EXTENSION) ?: 'mmd';
        $directory = dirname($outputFile);

        if ($separateFiles) {
            // Generate separate file for each folder
            foreach ($folderCoverage as $folder => $folderData) {
                $fileCoverage = $this->getFilesInFolder($originalCoverageData, $folder, $basePath, $excludePaths, $srcOnly);
                if (empty($fileCoverage)) continue;

                $pieChart = $this->generateFolderPieChart($folder, $fileCoverage);
                $fileName = $directory . '/' . $baseFileName . '_' . $this->sanitizeFilename($folder) . '.' . $extension;
                $this->filesystem->dumpFile($fileName, $pieChart);
            }
        } else {
            // Generate combined file with all folder pie charts
            $combinedContent = "# Code Coverage by Folder\n\n";

            foreach ($folderCoverage as $folder => $folderData) {
                $fileCoverage = $this->getFilesInFolder($originalCoverageData, $folder, $basePath, $excludePaths, $srcOnly);
                if (empty($fileCoverage)) continue;

                $combinedContent .= "## {$folder}\n\n";
                $combinedContent .= "```mermaid\n";
                $combinedContent .= $this->generateFolderPieChart($folder, $fileCoverage);
                $combinedContent .= "\n```\n\n";
            }

            $this->filesystem->dumpFile($outputFile, $combinedContent);
        }
    }

    private function getFilesInFolder(array $coverageData, string $targetFolder, string $basePath, array $excludePaths, bool $srcOnly): array
    {
        $fileCoverage = [];

        foreach ($coverageData as $filePath => $lines) {
            $originalPath = $filePath;

            // Apply same filtering as main processing
            if ($srcOnly && !$this->isSrcFile($filePath)) {
                continue;
            }

            if (!empty($excludePaths) && $this->shouldExcludePath($filePath, $excludePaths)) {
                continue;
            }

            // Remove base path
            if ($basePath && str_starts_with($filePath, $basePath)) {
                $filePath = substr($filePath, strlen($basePath));
            }

            if (empty($filePath) || $filePath === '/') {
                continue;
            }

            // Check if this file belongs to the target folder
            $fileFolder = dirname($filePath);
            $pathParts = explode('/', $fileFolder);
            $pathParts = array_filter($pathParts, fn($part) => $part !== '' && $part !== '.');

            if (empty($pathParts)) {
                $currentFolder = 'src';
            } else {
                $currentFolder = implode('/', $pathParts);
            }

            // Check if this file is in the target folder (exact match or subfolder)
            if ($currentFolder === $targetFolder || str_starts_with($currentFolder, $targetFolder . '/')) {
                // Calculate coverage for this file
                $totalLines = 0;
                $coveredLines = 0;

                if (is_array($lines)) {
                    foreach ($lines as $lineNumber => $executionCount) {
                        if ($executionCount !== null && is_numeric($lineNumber)) {
                            $totalLines++;
                            if ($executionCount > 0) {
                                $coveredLines++;
                            }
                        }
                    }
                }

                if ($totalLines > 0) {
                    $coverage = round(($coveredLines / $totalLines) * 100, 2);
                    $fileName = basename($originalPath, '.php');

                    $fileCoverage[$fileName] = [
                        'coverage' => $coverage,
                        'totalLines' => $totalLines,
                        'coveredLines' => $coveredLines,
                        'filePath' => $originalPath
                    ];
                }
            }
        }

        return $fileCoverage;
    }

    private function generateFolderPieChart(string $folder, array $fileCoverage): string
    {
        $mermaid = "%%{init: {'pie': {'textPosition': 0.5}, 'themeVariables': {'pieStrokeWidth': '2px'}}}%%\n";
        $mermaid .= "pie title \"{$folder} - File Coverage Distribution\"\n";

        // Sort files by coverage (descending)
        uasort($fileCoverage, fn($a, $b) => $b['coverage'] <=> $a['coverage']);

        // Group files by coverage ranges for better visualization
        $coverageRanges = [
            'Excellent (90-100%)' => ['min' => 90, 'max' => 100, 'count' => 0, 'lines' => 0],
            'Good (80-89%)' => ['min' => 80, 'max' => 89, 'count' => 0, 'lines' => 0],
            'Fair (70-79%)' => ['min' => 70, 'max' => 79, 'count' => 0, 'lines' => 0],
            'Poor (50-69%)' => ['min' => 50, 'max' => 69, 'count' => 0, 'lines' => 0],
            'Bad (1-49%)' => ['min' => 1, 'max' => 49, 'count' => 0, 'lines' => 0],
            'Untested (0%)' => ['min' => 0, 'max' => 0, 'count' => 0, 'lines' => 0],
        ];

        $totalLines = array_sum(array_column($fileCoverage, 'totalLines'));

        foreach ($fileCoverage as $fileName => $data) {
            $coverage = $data['coverage'];

            foreach ($coverageRanges as $range => &$rangeData) {
                if ($coverage >= $rangeData['min'] && $coverage <= $rangeData['max']) {
                    $rangeData['count']++;
                    $rangeData['lines'] += $data['totalLines'];
                    break;
                }
            }
        }

        // Add only ranges that have files
        foreach ($coverageRanges as $range => $rangeData) {
            if ($rangeData['count'] > 0) {
                $percentage = $totalLines > 0 ? round(($rangeData['lines'] / $totalLines) * 100, 1) : 0;
                $mermaid .= "    \"{$range} ({$rangeData['count']} files)\" : {$percentage}\n";
            }
        }

        return $mermaid;
    }

    private function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    }

    private function generateBarChart(array $folderCoverage): string
    {
        $mermaid = "%%{init: {'theme':'dark', 'themeVariables': { 'primaryColor': '#4CAF50', 'primaryTextColor': '#fff', 'primaryBorderColor': '#388E3C', 'lineColor': '#F57C00'}}}%%\n";
        $mermaid .= "xychart-beta\n";
        $mermaid .= "    title \"Code Coverage by Directory\"\n";
        $mermaid .= "    x-axis [";

        $directories = [];
        $coverageValues = [];

        foreach ($folderCoverage as $folder => $data) {
            $directories[] = '"' . $this->sanitizeLabel($folder) . '"';
            $coverageValues[] = $data['coverage'];
        }

        $mermaid .= implode(', ', $directories) . "]\n";
        $mermaid .= "    y-axis \"Coverage %\" 0 --> 100\n";
        $mermaid .= "    bar [" . implode(', ', $coverageValues) . "]\n";

        return $mermaid;
    }

    private function generateTreeDiagram(array $folderCoverage): string
    {
        $mermaid = "graph TD\n";
        $mermaid .= "    classDef high fill:#4CAF50,stroke:#2E7D32,stroke-width:2px,color:#fff\n";
        $mermaid .= "    classDef medium fill:#FF9800,stroke:#E65100,stroke-width:2px,color:#fff\n";
        $mermaid .= "    classDef low fill:#F44336,stroke:#C62828,stroke-width:2px,color:#fff\n";
        $mermaid .= "    classDef none fill:#9E9E9E,stroke:#424242,stroke-width:2px,color:#fff\n\n";

        $nodeId = 0;
        $nodeMap = [];

        // Create nodes
        foreach ($folderCoverage as $folder => $data) {
            $nodeId++;
            $nodeMap[$folder] = "node{$nodeId}";

            $label = $this->sanitizeLabel($folder);
            $coverage = $data['coverage'];
            $files = $data['files'];

            $displayText = "{$label}\\n{$coverage}%\\n({$files} files)";
            $mermaid .= "    {$nodeMap[$folder]}[\"{$displayText}\"]\n";
        }

        $mermaid .= "\n";

        // Create hierarchy connections
        $processed = [];
        foreach (array_keys($folderCoverage) as $folder) {
            if ($folder === 'src') continue;

            $parts = explode('/', $folder);
            $parentPath = '';

            for ($i = 0; $i < count($parts); $i++) {
                $currentPath = implode('/', array_slice($parts, 0, $i + 1));

                if ($i === 0) {
                    // Connect to src if it exists
                    if (isset($nodeMap['src']) && isset($nodeMap[$currentPath])) {
                        $connectionKey = "src->{$currentPath}";
                        if (!in_array($connectionKey, $processed)) {
                            $mermaid .= "    {$nodeMap['src']} --> {$nodeMap[$currentPath]}\n";
                            $processed[] = $connectionKey;
                        }
                    }
                } else {
                    // Connect to parent
                    $parentPath = implode('/', array_slice($parts, 0, $i));
                    if (isset($nodeMap[$parentPath]) && isset($nodeMap[$currentPath])) {
                        $connectionKey = "{$parentPath}->{$currentPath}";
                        if (!in_array($connectionKey, $processed)) {
                            $mermaid .= "    {$nodeMap[$parentPath]} --> {$nodeMap[$currentPath]}\n";
                            $processed[] = $connectionKey;
                        }
                    }
                }
            }
        }

        $mermaid .= "\n";

        // Apply styling based on coverage
        foreach ($folderCoverage as $folder => $data) {
            $coverage = $data['coverage'];
            $class = $this->getCoverageClass($coverage);
            $mermaid .= "    class {$nodeMap[$folder]} {$class}\n";
        }

        return $mermaid;
    }

    private function sanitizeLabel(string $label): string
    {
        return basename($label);
    }

    private function getCoverageClass(float $coverage): string
    {
        if ($coverage >= 80) {
            return 'high';
        } elseif ($coverage >= 60) {
            return 'medium';
        } elseif ($coverage > 0) {
            return 'low';
        } else {
            return 'none';
        }
    }

    private function displaySummary(SymfonyStyle $io, array $folderCoverage): void
    {
        $io->section('Source Directory Coverage Summary');

        $tableData = [];
        foreach ($folderCoverage as $folder => $data) {
            $sampleFiles = implode(', ', array_slice($data['filesList'], 0, 3));
            if (count($data['filesList']) > 3) {
                $sampleFiles .= ', +' . (count($data['filesList']) - 3) . ' more';
            }

            $tableData[] = [
                $folder,
                $data['coverage'] . '%',
                $data['coveredLines'] . '/' . $data['totalLines'],
                $data['files'],
                $sampleFiles
            ];
        }

        $io->table(['Directory', 'Coverage', 'Lines (Covered/Total)', 'Files', 'Sample Files'], $tableData);

        // Overall statistics
        $totalFiles = array_sum(array_column($folderCoverage, 'files'));
        $totalLines = array_sum(array_column($folderCoverage, 'totalLines'));
        $totalCovered = array_sum(array_column($folderCoverage, 'coveredLines'));
        $overallCoverage = $totalLines > 0 ? round(($totalCovered / $totalLines) * 100, 2) : 0;

        $io->info("Overall: {$overallCoverage}% coverage ({$totalCovered}/{$totalLines} lines in {$totalFiles} files)");
    }

    private function debugCoverageFile(SymfonyStyle $io, string $coverageFile): void
    {
        $io->section('Debug Information');

        $fileSize = filesize($coverageFile);
        $content = file_get_contents($coverageFile, false, null, 0, 1000); // First 1000 bytes

        $io->table(
            ['Property', 'Value'],
            [
                ['File', $coverageFile],
                ['Size', number_format($fileSize) . ' bytes'],
                ['Extension', pathinfo($coverageFile, PATHINFO_EXTENSION)],
                ['Starts with', substr($content, 0, 50) . '...'],
                ['Contains XML?', str_contains($content, '<?xml') ? 'Yes' : 'No'],
                ['Contains JSON?', (str_starts_with(trim($content), '{') || str_starts_with(trim($content), '[')) ? 'Yes' : 'No'],
                ['Contains PHP?', str_contains($content, '<?php') ? 'Yes' : 'No'],
                ['Contains serialized?', (str_contains($content, 'a:') || str_contains($content, 'O:')) ? 'Yes' : 'No'],
            ]
        );
    }
}
