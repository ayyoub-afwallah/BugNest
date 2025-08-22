<?php

namespace App\Infrastructure\Adapter\Console\Command;

use App\Domain\Util\StrHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:generate-mermaid',
    description: 'Generates a Mermaid.js diagram from a docker-compose.yml file.',
)]
class GenerateEnvDiagramCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to the docker-compose.yml file', 'docker-compose.yml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error(sprintf('The file "%s" does not exist.', $filePath));
            return Command::FAILURE;
        }

        try {
            $composeConfig = Yaml::parseFile($filePath);
        } catch (ParseException $exception) {
            $io->error(sprintf('Unable to parse the YAML string: %s', $exception->getMessage()));
            return Command::FAILURE;
        }

        $mermaid = $this->generateMermaidSyntax($composeConfig);

        $io->success('Mermaid diagram generated to env_diagram.md successfully!');
        file_put_contents('env_diagram.md', $mermaid);

        return Command::SUCCESS;
    }

    private function generateMermaidSyntax(array $config): string
    {
        $services = $config['services'] ?? [];
        $volumes = $config['volumes'] ?? [];
        $networks = array_keys($config['networks'] ?? []);
        $networkName = $networks[0] ?? 'appnet'; // Default to first network

        $mermaid = ["graph TD"];
        $mermaid[] = '    subgraph "User Interaction"';
        $mermaid[] = '        User/Browser';
        $mermaid[] = '    end';
        $mermaid[] = '';
        $mermaid[] = "    subgraph \"Docker Environment (" . ucfirst($networkName) . ")\"";

        // Define Service nodes
        $mermaid[] = '        subgraph "Services"';
        foreach ($services as $serviceName => $details) {
            $containerName = $details['container_name'] ?? $serviceName;
            $mermaid[] = "            {$serviceName}[\"{$serviceName}\"]";
        }
        $mermaid[] = '        end';
        $mermaid[] = '';

        // Define Volume nodes if they exist
        if (!empty($volumes)) {
            $mermaid[] = '        subgraph "Persistent Data"';
            foreach ($volumes as $volumeName => $details) {
                $mermaid[] = "            {$volumeName}(($volumeName))";
            }
            $mermaid[] = '        end';
            $mermaid[] = '';
        }

        $mermaid[] = '    end';
        $mermaid[] = '';


        // Create connections
        foreach ($services as $serviceName => $details) {

            $serviceName = StrHelper::toCamelCase($serviceName);

            // Port connections from User
            if (isset($details['ports'])) {
                foreach ($details['ports'] as $port) {
                    $portMapping = explode(":", $port)[0];
                    $mermaid[] = "    User/Browser -- \"Port {$portMapping}\" --> {$serviceName}";
                }
            }

            // Depends_on connections
            if (isset($details['depends_on'])) {
                foreach ($details['depends_on'] as $dependency) {
                    $mermaid[] = "    {$serviceName} -- depends on --> {$dependency}";
                }
            }

            // Volume connections
            if (isset($details['volumes'])) {
                foreach ($details['volumes'] as $volumeMap) {
                    $volumeParts = explode(':', $volumeMap);
                    $volumeName = $volumeParts[0];
                    if (array_key_exists($volumeName, $volumes)) {
                        $mermaid[] = "    {$serviceName} -- stores data in --> {$volumeName}";
                    }
                }
            }
        }

        return implode("\n", $mermaid);
    }
}
