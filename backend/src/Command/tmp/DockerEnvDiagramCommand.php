<?php

namespace App\Command\tmp;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:docker-env-diagram',
    description: 'Generate a Mermaid diagram of Docker environment from docker-compose files',
)]
class DockerEnvDiagramCommand extends Command
{
    private ParameterBagInterface $parameterBag;
    private array $serviceIcons = [
        'nginx' => '🌐',
        'apache' => '🌐',
        'php' => '🐘',
        'php-fpm' => '🐘',
        'mysql' => '🗄️',
        'postgresql' => '🐘',
        'postgres' => '🐘',
        'redis' => '🔴',
        'memcached' => '⚡',
        'elasticsearch' => '🔍',
        'rabbitmq' => '🐰',
        'mongodb' => '🍃',
        'mongo' => '🍃',
        'node' => '💚',
        'nodejs' => '💚',
        'mailhog' => '📧',
        'mailcatcher' => '📧',
        'traefik' => '🔀',
        'caddy' => '🔒',
        'docker' => '🐳',
        'workspace' => '💻',
        'worker' => '⚙️',
        'queue' => '📋',
        'cron' => '⏰',
        'scheduler' => '⏰',
    ];

    private array $portCategories = [
        'web' => [80, 443, 8080, 8000, 3000, 3001, 4200, 8888],
        'database' => [3306, 5432, 27017, 6379],
        'mail' => [25, 587, 465, 1025, 8025],
        'monitoring' => [9000, 9090, 3000, 8080],
        'other' => []
    ];

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('compose-file', InputArgument::OPTIONAL, 'Docker compose file path', 'docker-compose.yml')
            ->addArgument('output-file', InputArgument::OPTIONAL, 'Output file path', 'docker-environment.md')
            ->addOption('include-override', 'o1', InputOption::VALUE_NONE, 'Include docker-compose.override.yml')
            ->addOption('include-env', 'e1', InputOption::VALUE_NONE, 'Show environment variables')
            ->addOption('include-volumes', 'vv1', InputOption::VALUE_NONE, 'Show volume mappings')
            ->addOption('include-networks', 'n1', InputOption::VALUE_NONE, 'Show network connections')
            ->addOption('show-ports', 'p1', InputOption::VALUE_NONE, 'Show exposed ports')
            ->addOption('diagram-type', 't1', InputOption::VALUE_OPTIONAL, 'Diagram type (architecture, network, stack)', 'architecture')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $composeFile = $input->getArgument('compose-file');
        $outputFile = $input->getArgument('output-file');

        $includeOverride = $input->getOption('include-override');
        $includeEnv = $input->getOption('include-env');
        $includeVolumes = $input->getOption('include-volumes');
        $includeNetworks = $input->getOption('include-networks');
        $showPorts = $input->getOption('show-ports');
        $diagramType = $input->getOption('diagram-type');

        $io->title('Generating Docker Environment Diagram');
        $io->text("Project directory: {$projectDir}");
        $io->text("Compose file: {$composeFile}");
        $io->text("Diagram type: {$diagramType}");

        try {
            // Read docker-compose files
            $environment = $this->parseDockerCompose($projectDir, $composeFile, $includeOverride);

            if (empty($environment['services'])) {
                $io->warning('No services found in docker-compose file(s)');
                return Command::SUCCESS;
            }

            // Generate appropriate diagram based on type
            switch ($diagramType) {
                case 'network':
                    $mermaidContent = $this->generateNetworkDiagram($environment, $includeNetworks, $showPorts);
                    break;
                case 'stack':
                    $mermaidContent = $this->generateStackDiagram($environment, $includeVolumes);
                    break;
                case 'architecture':
                default:
                    $mermaidContent = $this->generateArchitectureDiagram($environment, $includeEnv, $includeVolumes, $showPorts);
                    break;
            }

            $fullContent = $this->generateEnvironmentMarkdown($mermaidContent, $environment, $diagramType, [
                'includeEnv' => $includeEnv,
                'includeVolumes' => $includeVolumes,
                'includeNetworks' => $includeNetworks,
                'showPorts' => $showPorts
            ]);

            file_put_contents($outputFile, $fullContent);

            $io->success("Docker environment diagram generated: {$outputFile}");
            $io->table(['Service', 'Image', 'Ports', 'Networks'], $this->formatServicesTable($environment['services']));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error generating diagram: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function parseDockerCompose(string $projectDir, string $composeFile, bool $includeOverride): array
    {
        $environment = [
            'services' => [],
            'networks' => [],
            'volumes' => [],
            'secrets' => [],
            'configs' => []
        ];

        // Parse main compose file
        $mainFile = $projectDir . '/' . $composeFile;
        if (!file_exists($mainFile)) {
            throw new \RuntimeException("Docker compose file not found: {$mainFile}");
        }

        $mainConfig = Yaml::parseFile($mainFile);
        $environment = array_merge_recursive($environment, $mainConfig);

        // Parse override file if requested
        if ($includeOverride) {
            $overrideFile = $projectDir . '/docker-compose.override.yml';
            if (file_exists($overrideFile)) {
                $overrideConfig = Yaml::parseFile($overrideFile);
                $environment = $this->mergeComposeConfigs($environment, $overrideConfig);
            }
        }

        // Parse environment files
        foreach ($environment['services'] as $serviceName => &$service) {
            if (isset($service['env_file'])) {
                $service['parsed_env'] = $this->parseEnvFiles($projectDir, $service['env_file']);
            }
        }

        return $environment;
    }

    private function mergeComposeConfigs(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if ($key === 'services' && isset($base['services'])) {
                foreach ($value as $serviceName => $serviceConfig) {
                    if (isset($base['services'][$serviceName])) {
                        $base['services'][$serviceName] = array_merge_recursive($base['services'][$serviceName], $serviceConfig);
                    } else {
                        $base['services'][$serviceName] = $serviceConfig;
                    }
                }
            } else {
                $base[$key] = array_merge_recursive($base[$key] ?? [], $value);
            }
        }
        return $base;
    }

    private function parseEnvFiles(string $projectDir, $envFiles): array
    {
        $envVars = [];
        $files = is_array($envFiles) ? $envFiles : [$envFiles];

        foreach ($files as $envFile) {
            $envPath = $projectDir . '/' . $envFile;
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                        [$key, $value] = explode('=', $line, 2);
                        $envVars[trim($key)] = trim($value);
                    }
                }
            }
        }

        return $envVars;
    }

    private function generateArchitectureDiagram(array $environment, bool $includeEnv, bool $includeVolumes, bool $showPorts): string
    {
        $mermaid = [];
        $mermaid[] = "graph TB";
        $mermaid[] = "";
        $mermaid[] = "    %% Docker Environment Architecture";
        $mermaid[] = "";

        $nodeCounter = 0;
        $nodeIds = [];

        // Group services by type
        $serviceGroups = $this->groupServicesByType($environment['services']);

        foreach ($serviceGroups as $groupName => $services) {
            // Create group subgraph
            $groupId = "group_" . strtolower($groupName);
            $mermaid[] = "    subgraph {$groupId} [\"{$groupName} Services\"]";

            foreach ($services as $serviceName => $service) {
                $serviceId = "service_" . $nodeCounter++;
                $nodeIds[$serviceName] = $serviceId;

                $icon = $this->getServiceIcon($serviceName, $service);
                $image = $service['image'] ?? 'custom';
                $label = "{$icon} {$serviceName}";

                if ($showPorts && isset($service['ports'])) {
                    $ports = $this->formatPorts($service['ports']);
                    $label .= "<br/>Ports: {$ports}";
                }

                $mermaid[] = "        {$serviceId}[\"{$label}<br/>{$image}\"]";
            }

            $mermaid[] = "    end";
            $mermaid[] = "";
        }

        // Add dependencies between services
        $this->addServiceDependencies($mermaid, $environment['services'], $nodeIds);

        // Add volume connections if requested
        if ($includeVolumes && !empty($environment['volumes'])) {
            $this->addVolumeConnections($mermaid, $environment, $nodeIds, $nodeCounter);
        }

        // Styling
        $mermaid[] = "";
        $mermaid[] = "    %% Styling";
        $mermaid[] = "    classDef webService fill:#e3f2fd,stroke:#1976d2,stroke-width:2px";
        $mermaid[] = "    classDef dbService fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px";
        $mermaid[] = "    classDef cacheService fill:#fff3e0,stroke:#f57c00,stroke-width:2px";
        $mermaid[] = "    classDef appService fill:#e8f5e8,stroke:#388e3c,stroke-width:2px";

        return implode("\n", $mermaid);
    }

    private function generateNetworkDiagram(array $environment, bool $includeNetworks, bool $showPorts): string
    {
        $mermaid = [];
        $mermaid[] = "graph LR";
        $mermaid[] = "";
        $mermaid[] = "    %% Docker Network Topology";
        $mermaid[] = "";

        // External access
        $mermaid[] = "    Internet([🌐 Internet])";
        $mermaid[] = "";

        $nodeCounter = 0;
        $nodeIds = [];

        // Add services with port mappings
        foreach ($environment['services'] as $serviceName => $service) {
            $serviceId = "service_" . $nodeCounter++;
            $nodeIds[$serviceName] = $serviceId;

            $icon = $this->getServiceIcon($serviceName, $service);
            $label = "{$icon} {$serviceName}";

            $mermaid[] = "    {$serviceId}[\"{$label}\"]";

            // Connect services with exposed ports to internet
            if (isset($service['ports'])) {
                $ports = $this->extractExposedPorts($service['ports']);
                if (!empty($ports)) {
                    $portsStr = implode(',', $ports);
                    $mermaid[] = "    Internet -->|\":{$portsStr}\"| {$serviceId}";
                }
            }
        }

        // Add internal service connections
        $this->addServiceDependencies($mermaid, $environment['services'], $nodeIds, true);

        return implode("\n", $mermaid);
    }

    private function generateStackDiagram(array $environment, bool $includeVolumes): string
    {
        $mermaid = [];
        $mermaid[] = "graph TB";
        $mermaid[] = "";
        $mermaid[] = "    %% Docker Stack Layers";
        $mermaid[] = "";

        // Create layers
        $layers = [
            'frontend' => ['nginx', 'apache', 'traefik', 'caddy'],
            'application' => ['php', 'php-fpm', 'app', 'web', 'api'],
            'cache' => ['redis', 'memcached'],
            'database' => ['mysql', 'postgresql', 'postgres', 'mongodb', 'mongo'],
            'queue' => ['rabbitmq', 'worker', 'queue'],
            'monitoring' => ['mailhog', 'mailcatcher']
        ];

        $nodeCounter = 0;
        foreach ($layers as $layerName => $layerServices) {
            $servicesInLayer = [];

            foreach ($environment['services'] as $serviceName => $service) {
                if ($this->isServiceInLayer($serviceName, $service, $layerServices)) {
                    $servicesInLayer[$serviceName] = $service;
                }
            }

            if (!empty($servicesInLayer)) {
                $layerId = strtolower($layerName);
                $mermaid[] = "    subgraph {$layerId} [\"{$layerName} Layer\"]";

                foreach ($servicesInLayer as $serviceName => $service) {
                    $serviceId = "service_" . $nodeCounter++;
                    $icon = $this->getServiceIcon($serviceName, $service);
                    $mermaid[] = "        {$serviceId}[\"{$icon} {$serviceName}\"]";
                }

                $mermaid[] = "    end";
                $mermaid[] = "";
            }
        }

        return implode("\n", $mermaid);
    }

    private function groupServicesByType(array $services): array
    {
        $groups = [
            'Web' => [],
            'Application' => [],
            'Database' => [],
            'Cache' => [],
            'Queue' => [],
            'Utility' => []
        ];

        foreach ($services as $serviceName => $service) {
            $image = strtolower($service['image'] ?? $serviceName);

            if (preg_match('/(nginx|apache|traefik|caddy)/', $image)) {
                $groups['Web'][$serviceName] = $service;
            } elseif (preg_match('/(mysql|postgres|mongo|mariadb)/', $image)) {
                $groups['Database'][$serviceName] = $service;
            } elseif (preg_match('/(redis|memcached)/', $image)) {
                $groups['Cache'][$serviceName] = $service;
            } elseif (preg_match('/(rabbitmq|worker|queue)/', $image)) {
                $groups['Queue'][$serviceName] = $service;
            } elseif (preg_match('/(mailhog|mailcatcher|phpmyadmin)/', $image)) {
                $groups['Utility'][$serviceName] = $service;
            } else {
                $groups['Application'][$serviceName] = $service;
            }
        }

        // Remove empty groups
        return array_filter($groups, fn($group) => !empty($group));
    }

    private function getServiceIcon(string $serviceName, array $service): string
    {
        $name = strtolower($serviceName);
        $image = strtolower($service['image'] ?? '');

        // Check service name first
        foreach ($this->serviceIcons as $pattern => $icon) {
            if (str_contains($name, $pattern) || str_contains($image, $pattern)) {
                return $icon;
            }
        }

        return '🐳'; // Default Docker icon
    }

    private function formatPorts(array $ports): string
    {
        $formatted = [];
        foreach ($ports as $port) {
            if (is_string($port) && str_contains($port, ':')) {
                $parts = explode(':', $port);
                $formatted[] = $parts[0]; // External port
            } else {
                $formatted[] = (string)$port;
            }
        }
        return implode(', ', $formatted);
    }

    private function extractExposedPorts(array $ports): array
    {
        $exposed = [];
        foreach ($ports as $port) {
            if (is_string($port) && str_contains($port, ':')) {
                $parts = explode(':', $port);
                $exposed[] = $parts[0];
            }
        }
        return $exposed;
    }

    private function addServiceDependencies(array &$mermaid, array $services, array $nodeIds, bool $isNetwork = false): void
    {
        foreach ($services as $serviceName => $service) {
            if (isset($service['depends_on'])) {
                $dependencies = is_array($service['depends_on']) ? $service['depends_on'] : [$service['depends_on']];

                foreach ($dependencies as $dependency) {
                    if (isset($nodeIds[$dependency]) && isset($nodeIds[$serviceName])) {
                        $arrow = $isNetwork ? ' -.-> ' : ' --> ';
                        $mermaid[] = "    {$nodeIds[$dependency]}{$arrow}{$nodeIds[$serviceName]}";
                    }
                }
            }
        }
    }

    private function addVolumeConnections(array &$mermaid, array $environment, array $nodeIds, int &$nodeCounter): void
    {
        if (!empty($environment['volumes'])) {
            $mermaid[] = "    %% Volumes";

            foreach ($environment['volumes'] as $volumeName => $volumeConfig) {
                $volumeId = "volume_" . $nodeCounter++;
                $mermaid[] = "    {$volumeId}[\"💾 {$volumeName}\"]";

                // Find services using this volume
                foreach ($environment['services'] as $serviceName => $service) {
                    if (isset($service['volumes'])) {
                        foreach ($service['volumes'] as $volume) {
                            if (str_contains($volume, $volumeName) && isset($nodeIds[$serviceName])) {
                                $mermaid[] = "    {$volumeId} -.-> {$nodeIds[$serviceName]}";
                            }
                        }
                    }
                }
            }
        }
    }

    private function isServiceInLayer(string $serviceName, array $service, array $layerServices): bool
    {
        $name = strtolower($serviceName);
        $image = strtolower($service['image'] ?? '');

        foreach ($layerServices as $layerService) {
            if (str_contains($name, $layerService) || str_contains($image, $layerService)) {
                return true;
            }
        }

        return false;
    }

    private function formatServicesTable(array $services): array
    {
        $table = [];

        foreach ($services as $serviceName => $service) {
            $image = $service['image'] ?? 'N/A';
            $ports = isset($service['ports']) ? $this->formatPorts($service['ports']) : 'N/A';
            $networks = isset($service['networks']) ? implode(', ', array_keys($service['networks'])) : 'default';

            $table[] = [$serviceName, $image, $ports, $networks];
        }

        return $table;
    }

    private function generateEnvironmentMarkdown(string $mermaidContent, array $environment, string $diagramType, array $options): string
    {
        $markdown = [];

        $markdown[] = "# Docker Environment Diagram";
        $markdown[] = "";
        $markdown[] = "Generated on: " . date('Y-m-d H:i:s');
        $markdown[] = "Diagram type: " . ucfirst($diagramType);
        $markdown[] = "";

        // Environment overview
        $serviceCount = count($environment['services']);
        $volumeCount = count($environment['volumes'] ?? []);
        $networkCount = count($environment['networks'] ?? []);

        $markdown[] = "## Environment Overview";
        $markdown[] = "";
        $markdown[] = "- **Services**: {$serviceCount}";
        $markdown[] = "- **Volumes**: {$volumeCount}";
        $markdown[] = "- **Networks**: {$networkCount}";
        $markdown[] = "";

        // Services breakdown
        $markdown[] = "## Services";
        $markdown[] = "";
        foreach ($environment['services'] as $serviceName => $service) {
            $icon = $this->getServiceIcon($serviceName, $service);
            $image = $service['image'] ?? 'custom build';
            $markdown[] = "### {$icon} {$serviceName}";
            $markdown[] = "- **Image**: `{$image}`";

            if (isset($service['ports'])) {
                $ports = $this->formatPorts($service['ports']);
                $markdown[] = "- **Ports**: {$ports}";
            }

            if (isset($service['environment']) && $options['includeEnv']) {
                $markdown[] = "- **Environment Variables**: " . count($service['environment']);
            }

            if (isset($service['volumes']) && $options['includeVolumes']) {
                $markdown[] = "- **Volumes**: " . count($service['volumes']);
            }

            $markdown[] = "";
        }

        // Diagram
        $markdown[] = "## {$diagramType} Diagram";
        $markdown[] = "";
        $markdown[] = "```mermaid";
        $markdown[] = $mermaidContent;
        $markdown[] = "```";

        // Additional sections based on options
        if ($options['includeVolumes'] && !empty($environment['volumes'])) {
            $markdown[] = "";
            $markdown[] = "## Volumes";
            $markdown[] = "";
            foreach ($environment['volumes'] as $volumeName => $volumeConfig) {
                $markdown[] = "- **{$volumeName}**: " . ($volumeConfig['driver'] ?? 'local');
            }
        }

        if ($options['includeNetworks'] && !empty($environment['networks'])) {
            $markdown[] = "";
            $markdown[] = "## Networks";
            $markdown[] = "";
            foreach ($environment['networks'] as $networkName => $networkConfig) {
                $driver = $networkConfig['driver'] ?? 'bridge';
                $markdown[] = "- **{$networkName}**: {$driver}";
            }
        }

        return implode("\n", $markdown);
    }
}
