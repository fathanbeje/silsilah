<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

class DeploySyncController extends Controller
{
    private string $syncCommand = '/usr/local/bin/silsilah-sync';
    private string $syncLogPath = '/var/log/silsilah-sync.log';

    public function index(): View
    {
        return view('deploy-sync.index', [
            'gitStatus' => $this->collectGitStatus(),
            'syncLog' => $this->readSyncLog(),
        ]);
    }

    public function run(): RedirectResponse
    {
        $process = new Process(['sudo', $this->syncCommand], base_path());
        $process->setTimeout(600);
        $process->run();

        return redirect()
            ->route('deploy-sync.index')
            ->with($process->isSuccessful() ? 'success' : 'error', trim($process->getOutput() . "\n" . $process->getErrorOutput()));
    }

    private function collectGitStatus(): array
    {
        return [
            'branch' => $this->runGitCommand(['git', 'branch', '--show-current']),
            'commit' => $this->runGitCommand(['git', 'rev-parse', '--short', 'HEAD']),
            'remote_commit' => $this->runGitCommand(['git', 'rev-parse', '--short', 'origin/master']),
            'dirty' => $this->runGitCommand(['git', 'status', '--short']),
        ];
    }

    private function runGitCommand(array $command): ?string
    {
        $process = new Process($command, base_path());
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }

    private function readSyncLog(): ?string
    {
        if (!is_readable($this->syncLogPath)) {
            return null;
        }

        $lines = @file($this->syncLogPath, FILE_IGNORE_NEW_LINES) ?: [];
        $tail = array_slice($lines, -80);

        return implode(PHP_EOL, $tail);
    }
}
