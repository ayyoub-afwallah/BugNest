<?php

namespace App\Tests\App\Unit\Infrastructure;

use App\Infrastructure\Adapter\Console\Command\GenerateEnvDiagramCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateEnvDiagramCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private string $tempInputFile;
    private string $outputFile;

    protected function setUp(): void
    {
        // Define paths for our temporary files
        $this->tempInputFile = sys_get_temp_dir() . '/docker-compose.test.yml';
        $this->outputFile = getcwd() . '/env_diagram.md'; // The command writes to the current dir

        $application = new Application();
        $application->add(new GenerateEnvDiagramCommand());
        $command = $application->find('app:generate-mermaid');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        // Crucial cleanup step: always run after each test
        if (file_exists($this->tempInputFile)) {
            unlink($this->tempInputFile);
        }
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    public function testExecuteSuccessfullyGeneratesDiagram(): void
    {
        $dockerComposeContent = <<<YAML
services:
    web:
        container_name: my-web-app
        ports:
            - "8080:80"
        depends_on:
            - db
        volumes:
            - db_data:/var/lib/mysql
networks:
    app_net:
volumes:
    db_data:
YAML;

        // Create the temporary input file
        file_put_contents($this->tempInputFile, $dockerComposeContent);

        // Execute the command using the temp file
        $this->commandTester->execute(['file' => $this->tempInputFile]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Mermaid diagram generated to env_diagram.md successfully!', $output);
        $this->assertTrue(file_exists($this->outputFile));

        $mermaidContent = file_get_contents($this->outputFile);
        $this->assertStringContainsString('graph TD', $mermaidContent);
        $this->assertStringContainsString('subgraph "Docker Environment (App_net)"', $mermaidContent);
        $this->assertStringContainsString('User/Browser -- "Port 8080" --> web', $mermaidContent);
        $this->assertStringContainsString('web -- depends on --> db', $mermaidContent);
    }

    public function testExecuteFailsIfFileNotFound(): void
    {
        $this->commandTester->execute(['file' => 'non_existent_file.yml']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('The file "non_existent_file.yml" does not exist.', $output);
    }

    public function testExecuteFailsOnInvalidYaml(): void
    {
        $invalidYamlContent = <<<YAML
services:
  web:
    image: nginx
  - invalid
YAML;
        // Create the temporary invalid input file
        file_put_contents($this->tempInputFile, $invalidYamlContent);

        $this->commandTester->execute(['file' => $this->tempInputFile]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Unable to parse the YAML string', $output);
    }
}
