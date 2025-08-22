<?php

namespace App\Infrastructure\Adapter\Console\Command\tmp;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:readme-generate',
    description: 'Generates the project README from a template, including a Mermaid diagram from docker-compose.',
)]
class GenerateReadmeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('compose-file', 'c', InputOption::VALUE_REQUIRED, 'Path to the docker-compose.yml file', 'docker-compose.yml')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Path to the README template file', 'README.template.md')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Path for the final README.md file', 'README.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $composeFile = $input->getOption('compose-file');
        $templateFile = $input->getOption('template');
        $outputFile = $input->getOption('output');

        // 1. Check that source files exist
        if (!file_exists($composeFile)) {
            $io->error(sprintf('The Docker Compose file "%s" does not exist.', $composeFile));
            return Command::FAILURE;
        }
        if (!file_exists($templateFile)) {
            $io->error(sprintf('The template file "%s" does not exist.', $templateFile));
            return Command::FAILURE;
        }

        // 2. Parse docker-compose file and generate Mermaid syntax
        try {
            $composeConfig = Yaml::parseFile($composeFile);
            $mermaidSyntax = $this->generateMermaidSyntax($composeConfig);
        } catch (ParseException $e) {
            $io->error(sprintf('Failed to parse YAML file "%s": %s', $composeFile, $e->getMessage()));
            return Command::FAILURE;
        }

        // 3. Prepare the final Mermaid block for Markdown
        $mermaidBlock = "```mermaid\n" . $mermaidSyntax . "\n```";

        // 4. Read the template and replace the placeholder
        $templateContent = file_get_contents($templateFile);
        $finalContent = str_replace('%%MERMAID_DIAGRAM%%', $mermaidBlock, $templateContent);

        // 5. Write the new README.md file
        file_put_contents($outputFile, $finalContent);

        $io->success(sprintf('The documentation file "%s" has been successfully generated!', $outputFile));

        return Command::SUCCESS;
    }

    private function generateMermaidSyntax(array $config): string
    {
        $services = $config['services'] ?? [];
        $volumes = $config['volumes'] ?? [];
        $networks = array_keys($config['networks'] ?? []);
        $networkName = $networks[0] ?? 'appnet';

        $mermaid = ["graph TD"];
        $mermaid[] = '    subgraph "User Interaction"';
        $mermaid[] = '        User/Browser';
        $mermaid[] = '    end';
        $mermaid[] = '';
        $mermaid[] = "    subgraph \"Docker Environment (" . ucfirst($networkName) . ")\"";
        $mermaid[] = '        subgraph "Services"';
        foreach ($services as $serviceName => $details) {
            $containerName = $details['container_name'] ?? $serviceName;
            $mermaid[] = "            {$serviceName}[\"{$serviceName} ({$containerName})\"]";
        }
        $mermaid[] = '        end';
        $mermaid[] = '';
        if (!empty($volumes)) {
            $mermaid[] = '        subgraph "Persistent Data"';
            foreach (array_keys($volumes) as $volumeName) {
                $mermaid[] = "            {$volumeName}(($volumeName))";
            }
            $mermaid[] = '        end';
            $mermaid[] = '';
        }
        $mermaid[] = '    end';
        $mermaid[] = '';

        foreach ($services as $serviceName => $details) {
            if (isset($details['ports'])) {
                foreach ($details['ports'] as $port) {
                    $hostPort = explode(":", $port)[0];
                    $mermaid[] = "    User/Browser -- \"Port {$hostPort}\" --> {$serviceName}";
                }
            }
            if (isset($details['depends_on'])) {
                foreach ($details['depends_on'] as $dependency) {
                    $mermaid[] = "    {$serviceName} -- depends on --> {$dependency}";
                }
            }
            if (isset($details['volumes'])) {
                foreach ($details['volumes'] as $volumeMap) {
                    if (is_string($volumeMap) && strpos($volumeMap, ':') !== false) {
                        $volumeName = explode(':', $volumeMap)[0];
                        if (array_key_exists($volumeName, $volumes)) {
                            $mermaid[] = "    {$serviceName} -- stores data in --> {$volumeName}";
                        }
                    }
                }
            }
        }
        return implode("\n", $mermaid);
    }
}
