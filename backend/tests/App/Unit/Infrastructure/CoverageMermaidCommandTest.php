<?php

namespace App\Tests\Unit\Infrastructure\Adapter\Console\Command;

use App\Infrastructure\Adapter\Console\Command\CoverageMermaidCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class CoverageMermaidCommandTest extends TestCase
{
    private MockObject|Filesystem $filesystem;
    private CommandTester $commandTester;
    private string $tempCoverageFile;
    private string $outputFile;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
        $command = new CoverageMermaidCommand($this->filesystem);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($application->find('coverage:mermaid'));

        // Define paths for temporary files
        $this->tempCoverageFile = sys_get_temp_dir() . '/coverage.php';
        $this->outputFile = 'coverage-diagram.mmd';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempCoverageFile)) {
            unlink($this->tempCoverageFile);
        }
        // Unset properties
        unset($this->filesystem, $this->commandTester, $this->tempCoverageFile, $this->outputFile);
    }

    /**
     * Helper to create a temporary coverage file for a test.
     */
    private function createTempCoverageFile(string $content): void
    {
        file_put_contents($this->tempCoverageFile, $content);
    }

    // --- Test Cases ---

    public function testExecuteFailsIfFileDoesNotExist(): void
    {
        $this->filesystem->expects($this->once())
            ->method('exists')
            ->with('nonexistent.cov')
            ->willReturn(false);

        $exitCode =  $this->commandTester->execute([
            'coverage-file' => 'nonexistent.cov',
        ]);

        $this->assertNotSame(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Coverage file not found: nonexistent.cov', $output);
    }

    public function testExecuteFailsOnUnparseableFile(): void
    {
        $this->createTempCoverageFile('This is not a valid coverage file content.');
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $exitCode = $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
        ]);

        $this->assertNotSame(0, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Error processing coverage data: Unable to parse coverage file format.', $output);
    }

    public function testExecuteWithDebugOption(): void
    {
        $coverageContent = "<?php return ['src/Dummy.php' => [10 => 1]];";
        $this->createTempCoverageFile($coverageContent);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--debug' => true,
        ]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Debug Information', $output);
        $this->assertStringContainsString('Successfully parsed coverage data', $output);
    }

    /**
     * @dataProvider chartTypeProvider
     */
    public function testExecuteWithDifferentChartTypes(string $chartType, string $expectedMermaidStart): void
    {
        $this->createTempCoverageFile("<?php return ['src/File.php' => [1 => 1, 2 => 0]];");
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($this->outputFile, $this->callback(function ($content) use ($expectedMermaidStart) {
                return str_starts_with(trim($content), $expectedMermaidStart);
            }));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--chart-type' => $chartType,
        ]);

        $this->commandTester->assertCommandIsSuccessful();
    }

    public static function chartTypeProvider(): array
    {
        return [
            'pie chart' => ['pie', '%%{init:'],
            'bar chart' => ['bar', '%%{init:'],
            'tree chart' => ['tree', 'graph TD'],
        ];
    }

    /**
     * @dataProvider coverageFileProvider
     */
    public function testExecuteWithDifferentFileFormats(string $content, string $filename): void
    {
        $this->tempCoverageFile = sys_get_temp_dir() . '/' . $filename;
        $this->createTempCoverageFile($content);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())->method('dumpFile');

        $this->commandTester->execute(['coverage-file' => $this->tempCoverageFile]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Mermaid diagram generated successfully', $this->commandTester->getDisplay());
    }

    public static function coverageFileProvider(): array
    {
        $phpArray = "<?php return ['src/Domain/User.php' => [10 => 1, 11 => null, 12 => 0]];";
        $cloverXml = '<?xml version="1.0"?><coverage><project><file name="src/Service.php"><line num="5" type="stmt" count="1"/><line num="6" type="stmt" count="0"/></file></project></coverage>';
        $json = '{"files": {"src/Controller/Home.php": {"lines": {"20": 1, "22": 0}}}}';
        // A serialized representation of ['src/Utils.php' => [5 => [1], 6 => []]]
        $serialized = 'a:1:{s:13:"src/Utils.php";a:2:{i:5;a:1:{i:0;i:1;}i:6;a:0:{}}}';
        // Mock a CodeCoverage object
        $codeCoverageObject = '<?php $obj = new stdClass; $obj->data = ["src/Legacy.php" => [1 => [1]]]; return $obj;';

        return [
            'PHP Array' => [$phpArray, 'coverage.php'],
            'Clover XML' => [$cloverXml, 'clover.xml'],
            'JSON' => [$json, 'coverage.json'],
            'Serialized' => [$serialized, 'coverage.cov'],
            'PHP CodeCoverage Object' => [$codeCoverageObject, 'coverage_object.php'],
        ];
    }

    public function testExecuteWithSrcOnlyOptionFiltersResults(): void
    {
        $coverage = "<?php return [
            'src/Domain/Entity.php' => [1 => 1],
            'tests/Domain/EntityTest.php' => [1 => 1],
            'vendor/autoload.php' => [1 => 1]
        ];";
        $this->createTempCoverageFile($coverage);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($this->anything(), $this->callback(function ($content) {
                // Should only contain 'Domain', not 'tests' or 'vendor'
                return str_contains($content, 'Domain')
                    && !str_contains($content, 'tests')
                    && !str_contains($content, 'vendor');
            }));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--src-only' => true,
        ]);

        $this->commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteWithExcludePathsOption(): void
    {
        $coverage = "<?php return [
            'src/Domain/Entity.php' => [1 => 1],
            'src/Infrastructure/Adapter.php' => [1 => 1]
        ];";
        $this->createTempCoverageFile($coverage);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($this->anything(), $this->callback(function ($content) {
                // Should contain 'Domain' but exclude 'Infrastructure'
                return str_contains($content, 'Domain')
                    && !str_contains($content, 'Infrastructure');
            }));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--exclude-paths' => ['src/Infrastructure'],
        ]);

        $this->commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteWithMaxDepthOption(): void
    {
        $coverage = "<?php return ['src/Domain/User/Entity.php' => [1 => 1]];";
        $this->createTempCoverageFile($coverage);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($this->anything(), $this->callback(function ($content) {
                // Depth 1 means we should only see 'Domain', not 'User'
                return str_contains($content, 'Domain') && !str_contains($content, 'User');
            }));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--max-depth' => 1,
            '--chart-type' => 'tree' // tree chart shows hierarchy best
        ]);

        $this->commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteWithMinCoverageOption(): void
    {
        $coverage = "<?php return [
            'src/Good/File.php' => [1 => 1, 2 => 1], // 100%
            'src/Bad/File.php' => [1 => 1, 2 => 0]  // 50%
        ];";
        $this->createTempCoverageFile($coverage);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($this->anything(), $this->callback(function ($content) {
                // 'Good' should be in the output, 'Bad' should be filtered out
                return str_contains($content, 'Good') && !str_contains($content, 'Bad');
            }));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--min-coverage' => 60,
        ]);

        $this->commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteWithSeparateFilesOption(): void
    {
        $coverage = "<?php return [
            'src/Domain/File.php' => [1 => 1],
            'src/Application/File.php' => [1 => 1]
        ];";
        $this->createTempCoverageFile($coverage);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        // Expect dumpFile to be called twice with different filenames
        $this->filesystem->expects($this->exactly(2))
            ->method('dumpFile')
            ->with($this->logicalOr(
                $this->stringContains('coverage-diagram_Domain.mmd'),
                $this->stringContains('coverage-diagram_Application.mmd')
            ));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--separate-files' => true,
        ]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Individual folder pie charts generated successfully!', $this->commandTester->getDisplay());
    }

    public function testExecuteWithFolderPiesChartType(): void
    {
        $coverage = "<?php return ['src/Domain/File.php' => [1 => 1]];";
        $this->createTempCoverageFile($coverage);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($this->outputFile, $this->callback(function ($content) {
                // Should contain markdown and a mermaid block
                return str_contains($content, '# Code Coverage by Folder') && str_contains($content, '```mermaid');
            }));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--chart-type' => 'folder-pies',
        ]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Individual folder pie charts generated successfully!', $this->commandTester->getDisplay());
    }

    public function testTreeDiagramGeneratesCorrectClasses(): void
    {
        $coverage = "<?php return [
            'src/High/File.php' => [1=>1, 2=>1, 3=>1, 4=>1, 5=>1, 6=>1, 7=>1, 8=>1], // 100%
            'src/Medium/File.php' => [1=>1, 2=>1, 3=>1, 4=>1, 5=>1, 6=>1, 7=>0, 8=>0], // 75%
            'src/Low/File.php' => [1=>1, 2=>0, 3=>0, 4=>0, 5=>0], // 20%
            'src/None/File.php' => [1=>0, 2=>0, 3=>0, 4=>0, 5=>0], // 0%
        ];";
        $this->createTempCoverageFile($coverage);
        $this->filesystem->expects($this->once())->method('exists')->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($this->anything(), $this->callback(function ($content) {
                return preg_match('/class .*? high/', $content)
                    && preg_match('/class .*? medium/', $content)
                    && preg_match('/class .*? low/', $content)
                    && preg_match('/class .*? none/', $content);
            }));

        $this->commandTester->execute([
            'coverage-file' => $this->tempCoverageFile,
            '--chart-type' => 'tree'
        ]);
        $this->commandTester->assertCommandIsSuccessful();
    }
}
