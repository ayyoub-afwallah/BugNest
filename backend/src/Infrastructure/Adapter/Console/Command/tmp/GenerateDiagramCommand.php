<?php

namespace App\Infrastructure\Adapter\Console\Command\tmp;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;

/**
 * Basic usage
 * php bin/console app:generate-mermaid-structure
 *
 * With options
 * php bin/console app:generate-mermaid-structure project-structure.md \
 * --max-depth=3 \
 * --include-files \
 * --show-relationships \
 * --diagram-type=flowchart \
 * --filter-extensions=php,twig,yaml
 */
#[AsCommand(
    name: 'app:generate-mermaid-structure',
    description: 'Generate a Mermaid diagram of the project structure',
)]
class GenerateDiagramCommand extends Command
{
    private ParameterBagInterface $parameterBag;
    private array $ignoredDirs = [
        'vendor', 'node_modules', '.git', '.idea', 'var/cache', 'var/log',
        'var/sessions', 'public/build', '.vscode', 'tests/coverage'
    ];
    private array $fileTypeIcons = [
        'php' => '🐘',
        'js' => '📜',
        'ts' => '🔷',
        'css' => '🎨',
        'scss' => '🎨',
        'twig' => '🌿',
        'yaml' => '⚙️',
        'yml' => '⚙️',
        'json' => '📋',
        'xml' => '📄',
        'md' => '📖',
        'sql' => '🗃️',
        'env' => '🔧',
    ];

    private array $hexagonalLayers = [
        // Core/Domain Layer (center of hexagon)
        'domain' => ['Domain', 'Entity', 'ValueObject', 'DomainService', 'Model'],
        'application' => ['Application', 'UseCase', 'Command', 'Query', 'Handler', 'Service'],

        // Ports (interfaces)
        'ports' => ['Port', 'Interface', 'Contract', 'Repository'],

        // Adapters (outer layer)
        'infrastructure' => ['Infrastructure', 'Adapter', 'Gateway', 'Client'],
        'ui' => ['Controller', 'Console', 'Api', 'Web'],
        'persistence' => ['Repository', 'Doctrine', 'Database', 'Migration'],
        'external' => ['External', 'ThirdParty', 'Integration', 'Http']
    ];

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('output-file', InputArgument::OPTIONAL, 'Output file path', 'project-structure.md')
            ->addOption('max-depth', 'd', InputOption::VALUE_OPTIONAL, 'Maximum directory depth', 4)
            ->addOption('include-files', 'f', InputOption::VALUE_NONE, 'Include individual files in the diagram')
            ->addOption('diagram-type', 't', InputOption::VALUE_OPTIONAL, 'Mermaid diagram type (graph, flowchart)', 'graph')
            ->addOption('hexagonal', 'x', InputOption::VALUE_NONE, 'Generate hexagonal architecture diagram')
            ->addOption('show-dependencies', null, InputOption::VALUE_NONE, 'Analyze and show dependencies between layers')
            ->addOption('filter-extensions', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of file extensions to include', 'php,twig,yaml,yml')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $outputFile = $input->getArgument('output-file');
        $maxDepth = (int) $input->getOption('max-depth');
        $includeFiles = $input->getOption('include-files');
        $diagramType = $input->getOption('diagram-type');
        $showRelationships = true;
        $filterExtensions = array_filter(explode(',', $input->getOption('filter-extensions') ?? ''));

        $io->title('Generating Mermaid Project Structure Diagram');
        $io->text("Project directory: {$projectDir}");
        $io->text("Output file: {$outputFile}");

        try {
            $structure = $this->scanProjectStructure($projectDir, $maxDepth, $includeFiles, $filterExtensions);
            $mermaidContent = $this->generateMermaidDiagram($structure, $diagramType, $showRelationships);

            $fullContent = $this->generateMarkdownDocument($mermaidContent, $structure);

            file_put_contents($outputFile, $fullContent);

            $io->success("Mermaid diagram generated successfully: {$outputFile}");
            $io->note('You can view this diagram by copying the Mermaid code to https://mermaid.live/ or using a Mermaid-compatible viewer.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error generating diagram: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function scanProjectStructure(string $projectDir, int $maxDepth, bool $includeFiles, array $filterExtensions): array
    {
        $structure = [
            'directories' => [],
            'files' => [],
            'stats' => [
                'total_dirs' => 0,
                'total_files' => 0,
                'file_types' => []
            ]
        ];

        $finder = new Finder();
        $finder->in($projectDir)
            ->depth("< {$maxDepth}")
            ->sortByName();

        // Filter out ignored directories
        foreach ($this->ignoredDirs as $ignoredDir) {
            $finder->notPath($ignoredDir);
        }

        // Scan directories
        $dirFinder = clone $finder;
        $dirFinder->directories();

        foreach ($dirFinder as $dir) {
            $relativePath = $dir->getRelativePathname();
            $structure['directories'][] = [
                'path' => $relativePath,
                'name' => $dir->getFilename(),
                'depth' => substr_count($relativePath, '/'),
                'parent' => dirname($relativePath) === '.' ? null : dirname($relativePath)
            ];
            $structure['stats']['total_dirs']++;
        }

        // Scan files if requested
        if ($includeFiles) {
            $fileFinder = clone $finder;
            $fileFinder->files();

            if (!empty($filterExtensions)) {
                $pattern = '/\.(' . implode('|', array_map('preg_quote', $filterExtensions)) . ')$/i';
                $fileFinder->name($pattern);
            }

            foreach ($fileFinder as $file) {
                $relativePath = $file->getRelativePathname();
                $extension = strtolower($file->getExtension());

                $structure['files'][] = [
                    'path' => $relativePath,
                    'name' => $file->getFilename(),
                    'extension' => $extension,
                    'size' => $file->getSize(),
                    'parent' => dirname($relativePath) === '.' ? null : dirname($relativePath),
                    'icon' => $this->fileTypeIcons[$extension] ?? '📄'
                ];

                $structure['stats']['total_files']++;
                $structure['stats']['file_types'][$extension] = ($structure['stats']['file_types'][$extension] ?? 0) + 1;
            }
        }

        return $structure;
    }

    private function generateMermaidDiagram(array $structure, string $diagramType, bool $showRelationships): string
    {
        $mermaid = [];

        // Diagram header
        switch ($diagramType) {
            case 'flowchart':
                $mermaid[] = "flowchart TD";
                break;
            case 'graph':
            default:
                $mermaid[] = "graph TD";
                break;
        }

        $mermaid[] = "";

        // Generate unique node IDs
        $nodeIds = [];
        $nodeCounter = 0;

        // Add root node
        $rootId = "root" . $nodeCounter++;
        $nodeIds[''] = $rootId;
        $mermaid[] = "    {$rootId}[\"🏠 Project Root\"]";

        // Add directory nodes
        foreach ($structure['directories'] as $dir) {
            $nodeId = "dir" . $nodeCounter++;
            $nodeIds[$dir['path']] = $nodeId;

            $icon = $this->getDirectoryIcon($dir['name']);
            $label = $icon . " " . $dir['name'];

            $mermaid[] = "    {$nodeId}[\"{$label}\"]";

            // Add relationships
            if ($showRelationships) {
                $parentId = $nodeIds[$dir['parent'] ?? ''] ?? $rootId;
                $mermaid[] = "    {$parentId} --> {$nodeId}";
            }
        }

        // Add file nodes if included
        foreach ($structure['files'] as $file) {
            $nodeId = "file" . $nodeCounter++;
            $nodeIds[$file['path']] = $nodeId;

            $label = $file['icon'] . " " . $file['name'];
            $mermaid[] = "    {$nodeId}[\"{$label}\"]";

            // Add relationships
            if ($showRelationships) {
                $parentPath = $file['parent'] ?? '';
                $parentId = $nodeIds[$parentPath] ?? $rootId;
                $mermaid[] = "    {$parentId} --> {$nodeId}";
            }
        }

        // Add styling
        $mermaid[] = "";
        $mermaid[] = "    %% Styling";
        $mermaid[] = "    classDef directoryNode fill:#e1f5fe,stroke:#01579b,stroke-width:2px";
        $mermaid[] = "    classDef fileNode fill:#f3e5f5,stroke:#4a148c,stroke-width:1px";
        $mermaid[] = "    classDef rootNode fill:#e8f5e8,stroke:#1b5e20,stroke-width:3px";

        // Apply classes
        $mermaid[] = "    class {$rootId} rootNode";

        foreach ($structure['directories'] as $i => $dir) {
            $nodeId = "dir" . ($i + 1);
            if (isset($nodeIds[$dir['path']])) {
                $mermaid[] = "    class {$nodeIds[$dir['path']]} directoryNode";
            }
        }

        return implode("\n", $mermaid);
    }

    private function generateMarkdownDocument(string $mermaidContent, array $structure): string
    {
        $markdown = [];

        $markdown[] = "# Project Structure Diagram";
        $markdown[] = "";
        $markdown[] = "Generated on: " . date('Y-m-d H:i:s');
        $markdown[] = "";

        // Statistics
        $markdown[] = "## Project Statistics";
        $markdown[] = "";
        $markdown[] = "- **Total Directories**: " . $structure['stats']['total_dirs'];
        $markdown[] = "- **Total Files**: " . $structure['stats']['total_files'];

        if (!empty($structure['stats']['file_types'])) {
            $markdown[] = "";
            $markdown[] = "### File Types Distribution";
            $markdown[] = "";
            foreach ($structure['stats']['file_types'] as $type => $count) {
                $icon = $this->fileTypeIcons[$type] ?? '📄';
                $markdown[] = "- **{$icon} .{$type}**: {$count} files";
            }
        }

        $markdown[] = "";
        $markdown[] = "## Structure Diagram";
        $markdown[] = "";
        $markdown[] = "```mermaid";
        $markdown[] = $mermaidContent;
        $markdown[] = "```";

        // Key directories explanation
        $markdown[] = "";
        $markdown[] = "## Directory Overview";
        $markdown[] = "";

        $keyDirectories = [
            'src' => 'Application source code',
            'config' => 'Configuration files',
            'templates' => 'Twig templates',
            'public' => 'Publicly accessible files',
            'assets' => 'Frontend assets',
            'migrations' => 'Database migrations',
            'tests' => 'Test files',
            'translations' => 'Translation files',
            'var' => 'Variable data (cache, logs, sessions)',
            'bin' => 'Executable files'
        ];

        foreach ($structure['directories'] as $dir) {
            $dirName = $dir['name'];
            if (isset($keyDirectories[$dirName]) && $dir['depth'] === 0) {
                $markdown[] = "- **{$dirName}/**: " . $keyDirectories[$dirName];
            }
        }

        return implode("\n", $markdown);
    }

    private function getDirectoryIcon(string $dirName): string
    {
        $icons = [
            'src' => '🔧',
            'config' => '⚙️',
            'templates' => '🌿',
            'public' => '🌐',
            'assets' => '🎨',
            'migrations' => '🗄️',
            'tests' => '🧪',
            'translations' => '🌍',
            'var' => '📁',
            'bin' => '⚡',
            'vendor' => '📦',
            'node_modules' => '📦',
            'Entity' => '🏛️',
            'Controller' => '🎮',
            'Repository' => '🗃️',
            'Service' => '🔧',
            'Form' => '📋',
            'Command' => '⚡',
            'EventListener' => '👂',
            'Security' => '🔐',
        ];

        return $icons[$dirName] ?? '📂';
    }
}
